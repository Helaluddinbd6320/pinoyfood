<?php

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Livewire\Checkout;
use App\Models\Area;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
        $this->product = Product::factory()->create([
            'price' => '30.00', 'sale_price' => null, 'sale_starts_at' => null, 'sale_ends_at' => null,
            'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function fakeCart(int $qty = 2, int $clears = 0): void
    {
        $this->mock(CartService::class, function ($m) use ($qty, $clears) {
            $m->shouldReceive('items')->andReturn([$this->product->id => $qty]);
            $m->shouldReceive('clear')->times($clears);
        });
    }

    private function filled()
    {
        return Livewire::test(Checkout::class)
            ->set('name', 'Maria Santos')
            ->set('phone', '0501230142')
            ->set('areaId', (string) $this->area->id);
    }

    #[Test]
    public function required_fields_are_validated(): void
    {
        $this->fakeCart();

        Livewire::test(Checkout::class)
            ->call('place')
            ->assertHasErrors(['name', 'phone', 'areaId']);

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function a_bad_pin_link_is_rejected(): void
    {
        $this->fakeCart();

        $this->filled()->set('pinUrl', 'javascript:alert(1)')->call('place')->assertHasErrors(['pinUrl']);
    }

    #[Test]
    public function a_valid_checkout_creates_the_order_clears_the_cart_and_redirects(): void
    {
        $this->fakeCart(clears: 1);

        $this->filled()->call('place')->assertHasNoErrors()->assertRedirect();

        $this->assertSame(1, Order::count());
        $this->assertSame('website', (string) Order::first()->source->value ?? Order::first()->source);
    }

    #[Test]
    public function the_honeypot_blocks_bots_silently(): void
    {
        $this->fakeCart();

        $this->filled()->set('website', 'http://spam.example')->call('place');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function changed_prices_need_a_second_confirmation(): void
    {
        $this->fakeCart(clears: 1);

        $c = $this->filled()->set('seenFingerprint', 'stale')->call('place');

        $this->assertSame(0, Order::count());
        $c->assertNotSet('notice', null)->assertNoRedirect();

        $c->call('place')->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    #[Test]
    public function the_order_done_page_needs_a_signed_link(): void
    {
        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$this->product->id => 1],
            name: 'Maria Santos',
            phone: '0501230142',
            areaId: $this->area->id,
        ));

        $this->get('/order/'.$order->order_no)->assertForbidden();

        $url = URL::temporarySignedRoute('order.done', now()->addDay(), ['orderNo' => $order->order_no]);
        $this->get($url)->assertOk()->assertSee($order->order_no);
    }
}