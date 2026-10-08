<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\WhatsappOffers;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerLookupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function optedIn(string $phone = '0501230142', string $name = 'Maria S.'): Customer
    {
        $customer = app(CustomerLookupService::class)->findOrCreate($phone, ['name' => $name]);
        $customer->forceFill(['whatsapp_opt_in' => true, 'opted_in_at' => now(), 'opt_in_source' => 'checkout'])->save();

        return $customer;
    }

    #[Test]
    public function only_admin_and_staff_can_open_the_page(): void
    {
        $this->actingAs($this->user('rider'));
        $this->assertFalse(WhatsappOffers::canAccess());

        $this->actingAs($this->user('staff'));
        $this->assertTrue(WhatsappOffers::canAccess());
    }

    #[Test]
    public function only_opted_in_customers_are_listed_with_a_chat_link(): void
    {
        $in = $this->optedIn();
        app(CustomerLookupService::class)->findOrCreate('0559998888', ['name' => 'Jun R.']);

        Livewire::actingAs($this->user('staff'))->test(WhatsappOffers::class)
            ->assertSee('Maria S.')
            ->assertDontSee('Jun R.')
            ->assertSee('https://wa.me/'.ltrim($in->phone_e164, '+'), false);
    }

    #[Test]
    public function mark_sent_logs_the_message_with_the_stop_line(): void
    {
        $customer = $this->optedIn();

        Livewire::actingAs($this->user('staff'))->test(WhatsappOffers::class)
            ->set('text', 'Kumusta {name}! Pandesal is ready.')
            ->call('markSent', $customer->id);

        $this->assertDatabaseHas('whatsapp_messages', [
            'customer_id' => $customer->id, 'direction' => 'out', 'kind' => 'offer', 'status' => 'sent',
        ]);
        $this->assertStringContainsString(
            'Kumusta Maria!',
            (string) \DB::table('whatsapp_messages')->where('customer_id', $customer->id)->value('body'),
        );
        $this->assertStringContainsString(
            'Reply STOP to unsubscribe.',
            (string) \DB::table('whatsapp_messages')->where('customer_id', $customer->id)->value('body'),
        );
    }

    #[Test]
    public function record_stop_opts_the_customer_out_and_logs_consent(): void
    {
        $customer = $this->optedIn();

        Livewire::actingAs($this->user('staff'))->test(WhatsappOffers::class)
            ->call('recordStop', $customer->id);

        $fresh = $customer->fresh();
        $this->assertFalse((bool) $fresh->whatsapp_opt_in);
        $this->assertNotNull($fresh->opted_out_at);
        $this->assertDatabaseHas('consent_logs', ['customer_id' => $customer->id, 'action' => 'opt_out', 'source' => 'admin']);
        $this->assertDatabaseHas('whatsapp_messages', ['customer_id' => $customer->id, 'kind' => 'stop', 'direction' => 'in']);
    }

    #[Test]
    public function a_customer_who_is_not_opted_in_cannot_be_marked_sent(): void
    {
        $customer = app(CustomerLookupService::class)->findOrCreate('0559998888', ['name' => 'Jun R.']);

        Livewire::actingAs($this->user('staff'))->test(WhatsappOffers::class)
            ->call('markSent', $customer->id);

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }
}