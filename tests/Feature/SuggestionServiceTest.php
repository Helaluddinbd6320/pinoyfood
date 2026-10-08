<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\PlaceOrderData;
use App\Livewire\Suggestions;
use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\SuggestionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?Category $category = null;

    private function product(array $attrs = []): Product
    {
        $this->category ??= Category::factory()->create(['is_active' => true]);

        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'price' => '20.00', 'sale_price' => null, 'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ], $attrs));
    }

    private function pair(Product $from, Product $to, int $sort = 0): void
    {
        DB::table('product_pairs')->insert([
            'product_id' => $from->id, 'paired_product_id' => $to->id, 'sort_order' => $sort,
        ]);
    }

    private function together(Product $a, Product $b, int $times): void
    {
        DB::table('product_co_purchases')->insert([
            ['product_id' => $a->id, 'other_product_id' => $b->id, 'times_together' => $times, 'updated_at' => now()],
        ]);
    }

    private function order(array $products, string $phone = '0501230142'): \App\Models\Order
    {
        $area = Area::factory()->create(['is_active' => true, 'delivery_fee_override' => null]);
        $cart = [];
        foreach ($products as $p) {
            $cart[$p->id] = 1;
        }

        return app(OrderService::class)->place(new PlaceOrderData(
            cart: $cart, name: 'Maria S.', phone: $phone, areaId: $area->id,
        ));
    }

    #[Test]
    public function pairs_are_shown_in_sort_order_and_skip_items_in_the_cart(): void
    {
        [$a, $b, $c, $d] = [$this->product(), $this->product(), $this->product(), $this->product()];
        $this->pair($a, $c, 2);
        $this->pair($a, $b, 1);
        $this->pair($a, $d, 3);

        $result = app(SuggestionService::class)->pairs([$a->id => 1, $b->id => 1]);

        $this->assertSame([$c->id, $d->id], $result->pluck('id')->all());
    }

    #[Test]
    public function pairs_are_limited_to_three(): void
    {
        $a = $this->product();
        foreach (range(1, 5) as $i) {
            $this->pair($a, $this->product(), $i);
        }

        $this->assertCount(3, app(SuggestionService::class)->pairs([$a->id => 1]));
    }

    #[Test]
    public function unavailable_products_are_never_suggested(): void
    {
        $a = $this->product();
        $inactive = $this->product(['is_active' => false]);
        $soldOut = $this->product(['stock' => 0]);
        $hiddenCategory = $this->product(['category_id' => Category::factory()->create(['is_active' => false])->id]);
        $ok = $this->product();

        foreach ([$inactive, $soldOut, $hiddenCategory, $ok] as $i => $p) {
            $this->pair($a, $p, $i);
        }

        $result = app(SuggestionService::class)->pairs([$a->id => 1]);

        $this->assertSame([$ok->id], $result->pluck('id')->all());
    }

    #[Test]
    public function an_empty_cart_gets_nothing(): void
    {
        $service = app(SuggestionService::class);

        $this->assertCount(0, $service->pairs([]));
        $this->assertCount(0, $service->picked([]));
    }

    #[Test]
    public function picked_uses_co_purchases_and_skips_the_pairs_list(): void
    {
        [$a, $b, $c] = [$this->product(), $this->product(), $this->product()];
        $this->together($a, $b, 5);
        $this->together($a, $c, 9);

        $picked = app(SuggestionService::class)->picked([$a->id => 1], null, [$c->id]);

        $this->assertSame([$b->id], $picked->pluck('product.id')->all());
        $this->assertSame('Often ordered together', $picked->first()['reason']);
    }

    #[Test]
    public function the_customers_history_boosts_a_product(): void
    {
        [$a, $b, $c] = [$this->product(), $this->product(), $this->product()];
        $this->together($a, $b, 3);
        $past = $this->order([$c]);

        $picked = app(SuggestionService::class)->picked([$a->id => 1], $past->customer);

        $this->assertSame([$c->id, $b->id], $picked->pluck('product.id')->all());
        $this->assertSame('You ordered this before', $picked->first()['reason']);
    }

    #[Test]
    public function a_guest_with_no_co_purchases_gets_best_sellers(): void
    {
        [$a, $best] = [$this->product(), $this->product()];
        $this->order([$best]);

        $picked = app(SuggestionService::class)->picked([$a->id => 1]);

        $this->assertSame([$best->id], $picked->pluck('product.id')->all());
        $this->assertSame('Popular right now', $picked->first()['reason']);
    }

    #[Test]
    public function the_component_shows_a_pair_and_adds_it_to_the_cart(): void
    {
        [$a, $b] = [$this->product(), $this->product()];
        $this->pair($a, $b);
        app(CartService::class)->add($a->id);

        Livewire::test(Suggestions::class)
            ->assertSee('Pairs well with')
            ->assertSee($b->name)
            ->call('add', $b->id);

        $this->assertSame([$a->id => 1, $b->id => 1], app(CartService::class)->items());
    }

    #[Test]
    public function the_pair_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = DB::table('product_pairs')->count();
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, DB::table('product_pairs')->count());
    }
}