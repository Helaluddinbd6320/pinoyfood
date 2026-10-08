<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\CustomerAccountService;
use App\Services\CustomerLookupService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerMergeTest extends TestCase
{
    use RefreshDatabase;

    private function phoneCustomer(string $phone = '0501230142'): Customer
    {
        return app(CustomerLookupService::class)->findOrCreate($phone, ['name' => 'Maria S.']);
    }

    private function googleOnly(string $gid = 'g-1'): Customer
    {
        return Customer::factory()->create([
            'google_id' => $gid,
            'email' => 'maria@example.com',
            'avatar_url' => 'https://x.test/a.jpg',
            'name' => 'Maria Santos',
            'phone_raw' => null,
            'phone_e164' => null,
            'phone_search' => null,
        ]);
    }

    #[Test]
    public function a_new_number_is_saved_on_the_same_customer(): void
    {
        $customer = $this->googleOnly();

        $result = app(CustomerAccountService::class)->attachPhone($customer, '050 123 0142');

        $this->assertSame($customer->id, $result->id);
        $this->assertSame('+966501230142', $customer->fresh()->phone_e164);
        $this->assertSame('501230142', $customer->fresh()->phone_search);
    }

    #[Test]
    public function a_known_number_merges_into_the_existing_customer(): void
    {
        $survivor = $this->phoneCustomer();
        $loser = $this->googleOnly();
        Order::factory()->create(['customer_id' => $survivor->id]);
        Order::factory()->create(['customer_id' => $loser->id]);

        $result = app(CustomerAccountService::class)->attachPhone($loser, '+966 50 123 0142');

        $this->assertSame($survivor->id, $result->id);

        $fresh = $survivor->fresh();
        $this->assertSame('g-1', $fresh->google_id);
        $this->assertSame('maria@example.com', $fresh->email);
        $this->assertSame('https://x.test/a.jpg', $fresh->avatar_url);
        $this->assertSame('Maria S.', $fresh->name);
        $this->assertSame(2, (int) $fresh->orders_count);
        $this->assertSame(2, Order::query()->where('customer_id', $survivor->id)->count());
        $this->assertSoftDeleted('customers', ['id' => $loser->id]);
    }

    #[Test]
    public function a_number_linked_to_another_google_account_is_refused(): void
    {
        $survivor = $this->phoneCustomer();
        $survivor->forceFill(['google_id' => 'someone-else'])->save();
        $loser = $this->googleOnly();

        try {
            app(CustomerAccountService::class)->attachPhone($loser, '0501230142');
            $this->fail('Expected a DomainException.');
        } catch (DomainException) {
            $this->assertNotSoftDeleted('customers', ['id' => $loser->id]);
            $this->assertSame('someone-else', $survivor->fresh()->google_id);
        }
    }

    #[Test]
    public function a_number_without_digits_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CustomerAccountService::class)->attachPhone($this->googleOnly(), 'abc');
    }

    #[Test]
    public function offers_need_a_number_and_are_logged(): void
    {
        $accounts = app(CustomerAccountService::class);
        $customer = $this->phoneCustomer();

        $accounts->setOffers($customer, true, '127.0.0.1', 'test');
        $this->assertTrue((bool) $customer->fresh()->whatsapp_opt_in);
        $this->assertDatabaseHas('consent_logs', ['customer_id' => $customer->id, 'action' => 'opt_in', 'source' => 'account']);

        $accounts->setOffers($customer->fresh(), false);
        $this->assertFalse((bool) $customer->fresh()->whatsapp_opt_in);
        $this->assertNotNull($customer->fresh()->opted_out_at);
        $this->assertDatabaseHas('consent_logs', ['customer_id' => $customer->id, 'action' => 'opt_out']);

        $this->expectException(DomainException::class);
        $accounts->setOffers($this->googleOnly('g-2'), true);
    }

    #[Test]
    public function setting_offers_to_the_current_value_logs_nothing(): void
    {
        $customer = $this->phoneCustomer();

        app(CustomerAccountService::class)->setOffers($customer, false);

        $this->assertDatabaseCount('consent_logs', 0);
    }
}