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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoAnswerFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'price' => '28.00', 'sale_price' => null, 'stock' => 50, 'track_stock' => true, 'is_active' => true,
        ]);
        $this->area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
    }

    private function order(string $phone = '0501230142'): Order
    {
        return app(OrderService::class)->place(new PlaceOrderData(
            cart: [$this->product->id => 1], name: 'Rey D.', phone: $phone, areaId: $this->area->id,
        ));
    }

    #[Test]
    public function the_first_no_answer_is_counted_but_does_not_flag(): void
    {
        $order = $this->order();

        app(OrderStatusService::class)->move($order, 'no_answer');

        $customer = $order->customer->fresh();
        $this->assertSame(1, (int) $customer->no_answer_count);
        $this->assertFalse((bool) $customer->requires_prepay);
    }

    #[Test]
    public function the_second_no_answer_flags_prepay_required(): void
    {
        $first = $this->order();
        $second = $this->order();
        $status = app(OrderStatusService::class);

        $status->move($first, 'no_answer');
        $status->move($second, 'no_answer');

        $customer = $first->customer->fresh();
        $this->assertSame(2, (int) $customer->no_answer_count);
        $this->assertTrue((bool) $customer->requires_prepay);
    }

    #[Test]
    public function a_no_answer_that_is_confirmed_and_missed_again_counts_again(): void
    {
        $order = $this->order();
        $status = app(OrderStatusService::class);

        $status->move($order, 'no_answer');
        $status->move($order, 'confirmed');
        $status->move($order, 'no_answer');

        $this->assertSame(2, (int) $order->customer->fresh()->no_answer_count);
    }

    #[Test]
    public function delivery_adds_the_order_total_to_total_spent_once(): void
    {
        $order = $this->order();
        $status = app(OrderStatusService::class);

        $status->move($order, 'confirmed');
        $status->move($order, 'out_for_delivery');
        $status->move($order, 'delivered');

        $this->assertSame('38.00', (string) $order->customer->fresh()->total_spent);

        $this->expectException(\InvalidArgumentException::class);
        $status->move($order, 'delivered');
    }
}