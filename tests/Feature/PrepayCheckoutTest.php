<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Livewire\Checkout;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CustomerLookupService;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrepayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $price = '28.00'): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => $price, 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function area(): Area
    {
        return Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
    }

    private function place(Product $p, bool $prepay = false, string $phone = '0501230142'): Order
    {
        return app(OrderService::class)->place(new PlaceOrderData(
            cart: [$p->id => 1], name: 'Maria S.', phone: $phone, areaId: $this->area()->id, prepayDeliveryFee: $prepay,
        ));
    }

    #[Test]
    public function no_payment_is_created_unless_asked(): void
    {
        $order = $this->place($this->product());

        $this->assertCount(0, $order->payments);
    }

    #[Test]
    public function an_optional_prepay_creates_an_awaiting_proof_payment_for_the_fee(): void
    {
        $order = $this->place($this->product('28.00'), true);

        $payment = $order->payments->first();
        $this->assertNotNull($payment);
        $this->assertSame('delivery_fee_prepay', $payment->type->value);
        $this->assertSame('awaiting_proof', $payment->status->value);
        $this->assertSame('10.00', (string) $payment->amount);
        // Nothing is verified yet, so the rider still collects the full total.
        $this->assertSame('0.00', (string) $order->fresh()->paid_online_total);
        $this->assertSame((string) $order->total, (string) $order->fresh()->amount_due_on_delivery);
    }

    #[Test]
    public function free_delivery_orders_have_no_fee_to_prepay(): void
    {
        $order = $this->place($this->product('160.00'), true);

        $this->assertCount(0, $order->payments);
    }

    #[Test]
    public function the_admin_can_switch_advance_payment_off(): void
    {
        app(SettingsService::class)->set('allow_prepay_delivery_fee', false);

        $order = $this->place($this->product(), true);

        $this->assertCount(0, $order->payments);
    }

    #[Test]
    public function a_flagged_customer_must_prepay_even_without_choosing_it(): void
    {
        $customer = app(CustomerLookupService::class)->findOrCreate('0501230142', ['name' => 'Rey D.']);
        $customer->forceFill(['requires_prepay' => true])->save();

        $order = $this->place($this->product(), false);

        $this->assertCount(1, $order->payments);
    }

    #[Test]
    public function a_required_deposit_is_created_for_free_delivery_orders(): void
    {
        app(SettingsService::class)->setMany(['require_deposit_for_free_delivery' => true, 'deposit_amount' => '10']);

        $order = $this->place($this->product('160.00'), false);

        $payment = $order->payments->first();
        $this->assertNotNull($payment);
        $this->assertSame('deposit', $payment->type->value);
        $this->assertSame('10.00', (string) $payment->amount);
    }

    #[Test]
    public function the_checkout_page_passes_the_choice_to_the_order(): void
    {
        $product = $this->product();
        $area = $this->area();
        app(CartService::class)->add($product->id);

        Livewire::test(Checkout::class)
            ->set('name', 'Maria S.')
            ->set('phone', '0501230142')
            ->set('areaId', (string) $area->id)
            ->set('prepay', true)
            ->call('place')
            ->assertHasNoErrors();

        $this->assertSame(1, Order::query()->firstOrFail()->payments()->count());
    }
}