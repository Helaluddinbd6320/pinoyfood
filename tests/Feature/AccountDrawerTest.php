<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Livewire\AccountDrawer;
use App\Livewire\Checkout;
use App\Livewire\WelcomeStrip;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CustomerLookupService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDrawerTest extends TestCase
{
    use RefreshDatabase;

    private function area(): Area
    {
        return Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'price' => '28.00', 'sale_price' => null, 'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function phoneCustomer(string $phone = '0501230142'): Customer
    {
        return app(CustomerLookupService::class)->findOrCreate($phone, ['name' => 'Maria S.']);
    }

    private function googleOnly(): Customer
    {
        return Customer::factory()->create([
            'google_id' => 'g-1', 'email' => 'maria@example.com', 'name' => 'Maria Santos',
            'phone_raw' => null, 'phone_e164' => null, 'phone_search' => null,
        ]);
    }

    /** @return array{0: Customer, 1: \App\Models\Order, 2: Product} */
    private function customerWithOrder(int $qty = 2): array
    {
        $product = $this->product();
        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$product->id => $qty],
            name: 'Maria S.',
            phone: '0501230142',
            areaId: $this->area()->id,
        ));

        return [$order->customer, $order, $product];
    }

    #[Test]
    public function a_guest_sees_the_sign_in_button(): void
    {
        Livewire::test(AccountDrawer::class)->assertSee('Sign in');
    }

    #[Test]
    public function a_signed_in_customer_sees_their_account(): void
    {
        [$customer, $order] = $this->customerWithOrder();

        Livewire::actingAs($customer, 'customer')->test(AccountDrawer::class)
            ->assertSee('Maria')
            ->assertSee($order->order_no)
            ->assertSee('Sign out');
    }

    #[Test]
    public function order_again_fills_the_cart_with_the_customers_own_order(): void
    {
        [$customer, $order, $product] = $this->customerWithOrder(2);

        Livewire::actingAs($customer, 'customer')->test(AccountDrawer::class)
            ->call('reorder', $order->id);

        $this->assertSame([$product->id => 2], app(CartService::class)->items());
    }

    #[Test]
    public function another_customers_order_cannot_be_reordered(): void
    {
        [, $order] = $this->customerWithOrder();
        $stranger = $this->phoneCustomer('0559998888');

        Livewire::actingAs($stranger, 'customer')->test(AccountDrawer::class)
            ->call('reorder', $order->id);

        $this->assertSame([], app(CartService::class)->items());
    }

    #[Test]
    public function the_offers_switch_logs_consent(): void
    {
        $customer = $this->phoneCustomer();

        Livewire::actingAs($customer, 'customer')->test(AccountDrawer::class)
            ->call('toggleOffers')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $customer->fresh()->whatsapp_opt_in);
        $this->assertDatabaseHas('consent_logs', ['customer_id' => $customer->id, 'action' => 'opt_in']);
    }

    #[Test]
    public function offers_cannot_be_switched_on_without_a_number(): void
    {
        $customer = $this->googleOnly();

        Livewire::actingAs($customer, 'customer')->test(AccountDrawer::class)
            ->call('toggleOffers')
            ->assertHasErrors(['offers']);

        $this->assertFalse((bool) $customer->fresh()->whatsapp_opt_in);
    }

    #[Test]
    public function the_welcome_strip_asks_for_a_number_once(): void
    {
        Livewire::actingAs($this->googleOnly(), 'customer')->test(WelcomeStrip::class)
            ->assertSee('Your WhatsApp number')
            ->call('skipPhone')
            ->assertDontSee('Your WhatsApp number');
    }

    #[Test]
    public function the_welcome_strip_saves_a_number(): void
    {
        $customer = $this->googleOnly();

        Livewire::actingAs($customer, 'customer')->test(WelcomeStrip::class)
            ->set('phone', '0501230142')
            ->call('savePhone')
            ->assertHasNoErrors();

        $this->assertSame('+966501230142', $customer->fresh()->phone_e164);
    }

    #[Test]
    public function the_welcome_strip_rejects_a_short_number(): void
    {
        Livewire::actingAs($this->googleOnly(), 'customer')->test(WelcomeStrip::class)
            ->set('phone', '123')
            ->call('savePhone')
            ->assertHasErrors(['phone']);
    }

    #[Test]
    public function checkout_is_prefilled_for_a_signed_in_customer(): void
    {
        $area = $this->area();
        $customer = $this->phoneCustomer();
        $customer->forceFill(['area_id' => $area->id])->save();

        Livewire::actingAs($customer->fresh(), 'customer')->test(Checkout::class)
            ->assertSet('name', 'Maria S.')
            ->assertSet('phone', $customer->phone_raw)
            ->assertSet('areaId', (string) $area->id);
    }
}