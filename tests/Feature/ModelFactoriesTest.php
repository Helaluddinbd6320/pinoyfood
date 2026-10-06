<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Area;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WhatsappMessage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_factory_creates_a_record(): void
    {
        $this->assertModelExists(Area::factory()->create());
        $this->assertModelExists(Category::factory()->create());
        $this->assertModelExists(Product::factory()->create());
        $this->assertModelExists(Customer::factory()->create());
        $this->assertModelExists(Order::factory()->create());
        $this->assertModelExists(OrderItem::factory()->create());
        $this->assertModelExists(Payment::factory()->create());
        $this->assertModelExists(Broadcast::factory()->create());
        $this->assertModelExists(WhatsappMessage::factory()->create());
    }

    public function test_order_relationships_and_enum_casts(): void
    {
        $order = Order::factory()
            ->has(OrderItem::factory()->count(2), 'items')
            ->has(Payment::factory(), 'payments')
            ->create();

        $this->assertCount(2, $order->items);
        $this->assertCount(1, $order->payments);
        $this->assertInstanceOf(Customer::class, $order->customer);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('110.00', $order->total);
    }

    public function test_phone_e164_must_be_unique_but_nulls_are_allowed(): void
    {
        Customer::factory()->create(['phone_e164' => null]);
        Customer::factory()->create(['phone_e164' => null]);
        Customer::factory()->create(['phone_e164' => '+966501230142']);

        $this->expectException(QueryException::class);
        Customer::factory()->create(['phone_e164' => '+966501230142']);
    }

    public function test_customer_and_product_use_soft_deletes(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $customer->delete();
        $product->delete();

        $this->assertSoftDeleted($customer);
        $this->assertSoftDeleted($product);
    }

    public function test_customer_with_orders_cannot_be_force_deleted(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);
        $order->customer()->withTrashed()->first()->forceDelete();
    }

    public function test_product_offer_dates_are_cast_to_datetimes(): void
    {
        $product = Product::factory()->onOffer('8.00')->create(['price' => '10.00']);

        $this->assertSame('8.00', $product->sale_price);
        $this->assertTrue($product->sale_ends_at->isFuture());
    }
}