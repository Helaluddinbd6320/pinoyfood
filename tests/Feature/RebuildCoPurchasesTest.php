<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebuildCoPurchasesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Product, 1: Product, 2: Order} */
    private function orderOfTwo(): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $make = fn () => Product::factory()->create([
            'category_id' => $category->id, 'price' => '20.00', 'sale_price' => null,
            'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ]);
        [$a, $b] = [$make(), $make()];
        $area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);

        $order = app(OrderService::class)->place(new PlaceOrderData(
            cart: [$a->id => 1, $b->id => 2], name: 'Jun R.', phone: '0559998888', areaId: $area->id,
        ));

        return [$a, $b, $order];
    }

    #[Test]
    public function it_stores_each_pair_in_both_directions(): void
    {
        [$a, $b] = $this->orderOfTwo();

        $this->artisan('suggestions:rebuild')->assertSuccessful();

        $this->assertSame(2, DB::table('product_co_purchases')->count());
        $this->assertDatabaseHas('product_co_purchases', ['product_id' => $a->id, 'other_product_id' => $b->id, 'times_together' => 1]);
        $this->assertDatabaseHas('product_co_purchases', ['product_id' => $b->id, 'other_product_id' => $a->id, 'times_together' => 1]);
    }

    #[Test]
    public function cancelled_orders_are_ignored(): void
    {
        [, , $order] = $this->orderOfTwo();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);

        $this->artisan('suggestions:rebuild')->assertSuccessful();

        $this->assertSame(0, DB::table('product_co_purchases')->count());
    }

    #[Test]
    public function old_orders_are_ignored(): void
    {
        [, , $order] = $this->orderOfTwo();
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subDays(200)]);

        $this->artisan('suggestions:rebuild')->assertSuccessful();

        $this->assertSame(0, DB::table('product_co_purchases')->count());
    }

    #[Test]
    public function running_it_twice_does_not_double_count(): void
    {
        [$a, $b] = $this->orderOfTwo();

        $this->artisan('suggestions:rebuild');
        $this->artisan('suggestions:rebuild');

        $this->assertDatabaseHas('product_co_purchases', ['product_id' => $a->id, 'other_product_id' => $b->id, 'times_together' => 1]);
        $this->assertSame(2, DB::table('product_co_purchases')->count());
    }
}