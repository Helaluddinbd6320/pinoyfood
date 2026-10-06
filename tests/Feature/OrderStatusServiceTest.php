<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirming_sets_status_and_timestamp(): void
    {
        $order = Order::factory()->create(['status' => 'new']);

        $moved = app(OrderStatusService::class)->move($order, 'confirmed');

        $this->assertSame('confirmed', OrderStatusService::current($moved));
        $this->assertNotNull($moved->confirmed_at);
    }

    #[Test]
    public function delivered_orders_cannot_move_again(): void
    {
        $order = Order::factory()->create(['status' => 'delivered']);

        $this->expectException(InvalidArgumentException::class);
        app(OrderStatusService::class)->move($order, 'confirmed');
    }

    #[Test]
    public function new_orders_cannot_jump_to_delivered(): void
    {
        $order = Order::factory()->create(['status' => 'new']);

        $this->expectException(InvalidArgumentException::class);
        app(OrderStatusService::class)->move($order, 'delivered');
    }

    #[Test]
    public function cancelling_stores_the_reason_and_time(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);

        $moved = app(OrderStatusService::class)->move($order, 'cancelled', 'Customer changed mind');

        $this->assertSame('Customer changed mind', $moved->cancel_reason);
        $this->assertNotNull($moved->cancelled_at);
    }

    #[Test]
    public function no_answer_orders_can_be_confirmed_again(): void
    {
        $order = Order::factory()->create(['status' => 'no_answer']);

        $moved = app(OrderStatusService::class)->move($order, 'confirmed');

        $this->assertSame('confirmed', OrderStatusService::current($moved));
    }
}