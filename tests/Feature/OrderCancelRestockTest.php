<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCancelRestockTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ], $attrs));
    }

    /** @param array<int,int> $cart */
    private function order(array $cart): Order
    {
        return app(OrderService::class)->place(new PlaceOrderData(
            cart: $cart, name: 'Maria S.', phone: '0501230142',
            areaId: Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null])->id,
        ));
    }

    #[Test]
    public function cancelling_puts_the_stock_back(): void
    {
        $a = $this->product();
        $b = $this->product(['stock' => 10]);
        $order = $this->order([$a->id => 3, $b->id => 2]);

        $this->assertSame(17, (int) $a->fresh()->stock);
        $this->assertSame(8, (int) $b->fresh()->stock);

        app(OrderStatusService::class)->move($order, 'cancelled', 'Customer changed mind');

        $this->assertSame(20, (int) $a->fresh()->stock);
        $this->assertSame(10, (int) $b->fresh()->stock);
    }

    #[Test]
    public function a_no_answer_order_that_is_cancelled_is_restocked_once(): void
    {
        $a = $this->product();
        $order = $this->order([$a->id => 4]);
        $status = app(OrderStatusService::class);

        $status->move($order, 'no_answer');
        $this->assertSame(16, (int) $a->fresh()->stock); // no_answer alone does not restock

        $status->move($order, 'cancelled', 'No reply');
        $this->assertSame(20, (int) $a->fresh()->stock);
    }

    #[Test]
    public function an_order_cannot_be_restocked_twice(): void
    {
        $a = $this->product();
        $order = $this->order([$a->id => 2]);
        $status = app(OrderStatusService::class);

        $status->move($order, 'cancelled', 'x');

        try {
            $status->move($order, 'cancelled', 'again');
            $this->fail('A cancelled order must not move again.');
        } catch (InvalidArgumentException) {
            $this->assertSame(20, (int) $a->fresh()->stock);
        }
    }

    #[Test]
    public function products_that_do_not_track_stock_are_left_alone(): void
    {
        $a = $this->product();
        $order = $this->order([$a->id => 2]);
        $a->forceFill(['track_stock' => false, 'stock' => 5])->save();

        app(OrderStatusService::class)->move($order, 'cancelled', 'x');

        $this->assertSame(5, (int) $a->fresh()->stock);
    }

    #[Test]
    public function a_deleted_product_is_still_restocked(): void
    {
        $a = $this->product();
        $order = $this->order([$a->id => 3]);
        $a->delete();

        app(OrderStatusService::class)->move($order, 'cancelled', 'x');

        $this->assertSame(20, (int) Product::withTrashed()->find($a->id)->stock);
    }

    #[Test]
    public function delivered_orders_cannot_be_cancelled_so_stock_stays_sold(): void
    {
        $a = $this->product();
        $order = $this->order([$a->id => 3]);
        $status = app(OrderStatusService::class);

        $status->move($order, 'confirmed');
        $status->move($order, 'out_for_delivery');
        $status->move($order, 'delivered');

        $this->expectException(InvalidArgumentException::class);

        try {
            $status->move($order, 'cancelled', 'late');
        } finally {
            $this->assertSame(17, (int) $a->fresh()->stock);
        }
    }
}