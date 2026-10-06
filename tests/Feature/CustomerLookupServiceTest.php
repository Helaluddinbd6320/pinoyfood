<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\CustomerLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CustomerLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerLookupService::class);
    }

    public function test_it_creates_a_new_customer_with_normalized_fields(): void
    {
        $customer = $this->service->findOrCreate('050 123 4567', ['name' => 'Maria S.']);

        $this->assertTrue($customer->wasRecentlyCreated);
        $this->assertSame('Maria S.', $customer->name);
        $this->assertSame('050 123 4567', $customer->phone_raw);
        $this->assertSame('+966501234567', $customer->phone_e164);
        $this->assertSame('501234567', $customer->phone_search);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $customer->referral_code);
    }

    public function test_different_formats_of_the_same_number_find_one_customer(): void
    {
        $first = $this->service->findOrCreate('0501234567');
        $arabic = "\u{0660}\u{0665}\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}";

        foreach (['+966 50 123 4567', '501234567', '00966501234567', $arabic] as $format) {
            $this->assertTrue($this->service->findOrCreate($format)->is($first), "Format: {$format}");
        }

        $this->assertSame(1, Customer::count());
    }

    public function test_saudi_and_philippine_numbers_are_different_customers(): void
    {
        $this->service->findOrCreate('0501234567');
        $this->service->findOrCreate('0917 123 4567');

        $this->assertSame(2, Customer::count());
    }

    public function test_search_fallback_finds_a_customer_whose_stored_number_was_unusable(): void
    {
        $old = Customer::factory()->create([
            'phone_raw' => '0501234567',
            'phone_e164' => null,
            'phone_search' => '501234567',
        ]);

        $found = $this->service->findOrCreate('+966 50 123 4567');

        $this->assertTrue($found->is($old));
        $this->assertSame('+966501234567', $old->fresh()->phone_e164);
        $this->assertSame(1, Customer::count());
    }

    public function test_search_fallback_is_skipped_when_two_customers_match(): void
    {
        foreach ([1, 2] as $i) {
            Customer::factory()->create(['phone_e164' => null, 'phone_search' => '501234567']);
        }

        $this->assertNull($this->service->find('0501234567'));

        $created = $this->service->findOrCreate('0501234567');

        $this->assertSame(3, Customer::count());
        $this->assertSame('+966501234567', $created->phone_e164);
    }

    public function test_search_fallback_ignores_a_customer_with_a_different_country_code(): void
    {
        Customer::factory()->create([
            'phone_raw' => '0501234567',
            'phone_e164' => '+971501234567',
            'phone_search' => '501234567',
        ]);

        $created = $this->service->findOrCreate('0501234567');

        $this->assertSame(2, Customer::count());
        $this->assertSame('+966501234567', $created->phone_e164);
    }

    public function test_an_unusable_number_still_creates_and_is_found_again(): void
    {
        $first = $this->service->findOrCreate('12345');

        $this->assertNull($first->phone_e164);
        $this->assertSame('12345', $first->phone_raw);

        $this->assertTrue($this->service->findOrCreate('12345')->is($first));
        $this->assertSame(1, Customer::count());
    }

    public function test_a_soft_deleted_customer_is_restored_instead_of_duplicated(): void
    {
        $customer = $this->service->findOrCreate('0501234567');
        $customer->delete();

        $this->assertNull($this->service->find('0501234567'));

        $again = $this->service->findOrCreate('0501234567');

        $this->assertTrue($again->is($customer));
        $this->assertNull($again->fresh()->deleted_at);
        $this->assertSame(1, Customer::withTrashed()->count());
    }

    public function test_a_blank_number_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->findOrCreate('abc');
    }

    public function test_only_allowed_attributes_are_used_and_existing_data_is_not_overwritten(): void
    {
        $customer = $this->service->findOrCreate('0501234567', [
            'name' => 'Maria S.',
            'orders_count' => 99,
            'requires_prepay' => true,
            'whatsapp_opt_in' => true,
        ]);

        $saved = $customer->fresh();

        $this->assertSame('Maria S.', $saved->name);
        $this->assertSame(0, $saved->orders_count);
        $this->assertFalse($saved->requires_prepay);
        $this->assertFalse($saved->whatsapp_opt_in);

        $again = $this->service->findOrCreate('0501234567', ['name' => 'Someone else']);

        $this->assertSame('Maria S.', $again->name);
    }

    public function test_find_returns_null_for_an_unknown_number(): void
    {
        $this->assertNull($this->service->find('0509999999'));
        $this->assertNull($this->service->find(null));
    }
}