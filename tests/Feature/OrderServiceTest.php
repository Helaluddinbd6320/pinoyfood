<?php

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PricesChangedException;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
    }

    private function product(array $o = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => '30.00', 'sale_price' => null, 'sale_starts_at' => null, 'sale_ends_at' => null,
            'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ], $o));
    }

    private function data(array $cart, array $o = []): PlaceOrderData
    {
        return new PlaceOrderData(...array_merge([
            'cart' => $cart,
            'name' => 'Maria Santos',
            'phone' => '0501230142',
            'areaId' => $this->area->id,
        ], $o));
    }

    private function assertMoney(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp((string) $actual, $expected, 2), "Expected {$expected}, got {$actual}");
    }

    #[Test]
    public function it_creates_an_order_with_server_prices_and_snapshots(): void
    {
        $p = $this->product(['price' => '30.00', 'sale_price' => '20.00']);

        $order = app(OrderService::class)->place($this->data([$p->id => 3]));

        $this->assertMatchesRegularExpression('/^PF-\d+$/', $order->order_no);
        $this->assertSame('Maria Santos', $order->customer_name);
        $this->assertMoney('60.00', $order->subtotal);
        $this->assertMoney('10.00', $order->delivery_fee);
        $this->assertMoney('70.00', $order->total);
        $this->assertMoney('70.00', $order->amount_due_on_delivery);

        $item = $order->items->first();
        $this->assertMoney('30.00', $item->list_price);
        $this->assertMoney('20.00', $item->unit_price);
        $this->assertSame(3, (int) $item->qty);
    }

    #[Test]
    public function free_delivery_applies_above_the_threshold(): void
    {
        $p = $this->product(['price' => '80.00']);

        $order = app(OrderService::class)->place($this->data([$p->id => 2]));

        $this->assertMoney('0.00', $order->delivery_fee);
        $this->assertTrue((bool) $order->free_delivery_applied);
        $this->assertMoney('160.00', $order->total);
    }

    #[Test]
    public function it_decrements_stock(): void
    {
        $p = $this->product(['stock' => 10]);

        app(OrderService::class)->place($this->data([$p->id => 4]));

        $this->assertSame(6, $p->fresh()->stock);
    }

    #[Test]
    public function insufficient_stock_rolls_everything_back(): void
    {
        $a = $this->product(['stock' => 5]);
        $b = $this->product(['stock' => 1]);

        try {
            app(OrderService::class)->place($this->data([$a->id => 1, $b->id => 3]));
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(1, $e->available);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $a->fresh()->stock);
        $this->assertSame(0, Customer::count());
    }

    #[Test]
    public function a_stale_fingerprint_throws_and_creates_nothing(): void
    {
        $p = $this->product();

        try {
            app(OrderService::class)->place($this->data([$p->id => 1], ['expectedFingerprint' => 'stale']));
            $this->fail('Expected PricesChangedException');
        } catch (PricesChangedException $e) {
            $this->assertNotSame('stale', $e->newFingerprint);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $p->fresh()->stock);
    }

    #[Test]
    public function a_matching_fingerprint_places_the_order(): void
    {
        $p = $this->product();
        $cart = [$p->id => 1];
        $fp = app(PricingService::class)->quote($cart, $this->area, now(), false)->fingerprint();

        $order = app(OrderService::class)->place($this->data($cart, ['expectedFingerprint' => $fp]));

        $this->assertNotNull($order->id);
    }

    #[Test]
    public function different_phone_formats_reuse_one_customer(): void
    {
        $p = $this->product();
        $svc = app(OrderService::class);

        $svc->place($this->data([$p->id => 1], ['phone' => '0501234567']));
        $svc->place($this->data([$p->id => 1], ['phone' => '+966 50 123 4567']));

        $this->assertSame(1, Customer::count());
        $this->assertSame(2, (int) Customer::first()->orders_count);
    }

    #[Test]
    public function opt_in_is_logged_once(): void
    {
        $p = $this->product();
        $svc = app(OrderService::class);

        $svc->place($this->data([$p->id => 1], ['optIn' => true]));
        $svc->place($this->data([$p->id => 1], ['optIn' => true]));

        $this->assertTrue((bool) Customer::first()->whatsapp_opt_in);
        $this->assertSame(1, DB::table('consent_logs')->where('action', 'opt_in')->count());
    }

    #[Test]
    public function no_opt_in_means_no_consent_row(): void
    {
        $p = $this->product();

        app(OrderService::class)->place($this->data([$p->id => 1], ['optIn' => false]));

        $this->assertFalse((bool) Customer::first()->whatsapp_opt_in);
        $this->assertSame(0, DB::table('consent_logs')->count());
    }
}