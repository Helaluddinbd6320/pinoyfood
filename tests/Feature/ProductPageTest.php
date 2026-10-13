<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ProductDetail;
use App\Livewire\ProductGrid;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = [], bool $categoryActive = true): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['is_active' => $categoryActive])->id,
            'name' => 'Leche flan', 'price' => '18.00', 'sale_price' => null,
            'stock' => 10, 'track_stock' => true, 'is_active' => true,
            'description' => 'Silky caramel custard.',
        ], $attrs));
    }

    #[Test]
    public function an_active_product_has_a_page_with_share_preview_tags(): void
    {
        $p = $this->product();

        $this->get(route('product.show', $p->slug))
            ->assertOk()
            ->assertSee('Leche flan')
            ->assertSee('Silky caramel custard.')
            ->assertSee('property="og:title"', false)
            ->assertSee('Add to cart');
    }

    #[Test]
    public function unknown_inactive_and_hidden_category_products_give_404(): void
    {
        $this->get('/product/no-such-thing')->assertNotFound();
        $this->get(route('product.show', $this->product(['is_active' => false])->slug))->assertNotFound();
        $this->get(route('product.show', $this->product([], false)->slug))->assertNotFound();
    }

    #[Test]
    public function an_offer_shows_the_offer_price_and_end_date(): void
    {
        $p = $this->product(['sale_price' => '12.00', 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDays(3)]);

        $this->get(route('product.show', $p->slug))
            ->assertOk()
            ->assertSee('SAR 12')
            ->assertSee('Offer until');
    }

    #[Test]
    public function empty_allergens_and_ingredients_are_not_shown(): void
    {
        $p = $this->product(['allergens' => null, 'ingredients' => null]);

        $this->get(route('product.show', $p->slug))
            ->assertDontSee('Allergens')
            ->assertDontSee('Ingredients');
    }

    #[Test]
    public function allergens_and_ingredients_show_when_filled(): void
    {
        $p = $this->product(['allergens' => ['egg', 'milk'], 'ingredients' => 'Eggs, milk, sugar']);

        $this->get(route('product.show', $p->slug))
            ->assertSee('Allergens')
            ->assertSee('egg, milk')
            ->assertSee('Eggs, milk, sugar');
    }

    #[Test]
    public function add_to_cart_uses_the_server_and_sold_out_items_cannot_be_added(): void
    {
        $ok = $this->product();
        Livewire::test(ProductDetail::class, ['productId' => $ok->id])->call('add');
        $this->assertSame([$ok->id => 1], app(CartService::class)->items());

        $gone = $this->product(['name' => 'Gone', 'stock' => 0]);
        Livewire::test(ProductDetail::class, ['productId' => $gone->id])
            ->assertSee('Sold out')
            ->call('add');
        $this->assertArrayNotHasKey($gone->id, app(CartService::class)->items());
    }

    #[Test]
    public function the_product_id_is_locked(): void
    {
        $p = $this->product();
        $component = Livewire::test(ProductDetail::class, ['productId' => $p->id]);

        try {
            $component->set('productId', 999);
            $this->fail('A locked property must refuse to be set.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable) {
        }

        $component->assertSet('productId', $p->id);
    }

    #[Test]
    public function the_menu_cards_link_to_the_product_page(): void
    {
        $p = $this->product();

        Livewire::test(ProductGrid::class)->assertSee(route('product.show', $p->slug), false);
    }

        #[Test]
    public function the_page_shows_pairs_and_falls_back_to_the_same_category(): void
    {
        $a = $this->product(['name' => 'Smoked fish']);
        $b = $this->product(['name' => 'Sawsawan set', 'category_id' => $a->category_id]);
        \Illuminate\Support\Facades\DB::table('product_pairs')->insert([
            'product_id' => $a->id, 'paired_product_id' => $b->id, 'sort_order' => 0,
        ]);
        $c = $this->product(['name' => 'Longganisa', 'category_id' => $a->category_id]);

        $this->get(route('product.show', $a->slug))
            ->assertOk()
            ->assertSee('Pairs well with')
            ->assertSee('Sawsawan set')
            ->assertSee('Longganisa'); // category fallback fills the list
    }

    #[Test]
    public function a_translation_equal_to_the_name_is_hidden_and_related_add_uses_the_cart(): void
    {
        $a = $this->product(['name' => 'Leche flan', 'name_tl' => 'leche flan']);
        $b = $this->product(['name' => 'Ube halaya', 'category_id' => $a->category_id]);

        Livewire::test(ProductDetail::class, ['productId' => $a->id])
            ->assertViewHas('showTl', false)
            ->call('addRelated', $b->id);

        $this->assertSame([$b->id => 1], app(CartService::class)->items());
    }
}