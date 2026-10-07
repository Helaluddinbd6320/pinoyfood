<?php

namespace Tests\Feature;

use App\Livewire\CartDrawer;
use App\Livewire\ProductGrid;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(array $attrs = []): Product
    {
        $category = Category::query()->first() ?? Category::factory()->create(['is_active' => true]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'is_active' => true,
            'track_stock' => true,
            'stock' => 10,
            'price' => '20.00',
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ], $attrs));
    }

    #[Test]
    public function the_home_page_lists_products_and_mounts_the_components(): void
    {
        $this->product(['name' => 'Chicken adobo']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Lutong bahay')
            ->assertSee('Chicken adobo')
            ->assertSeeLivewire(ProductGrid::class)
            ->assertSeeLivewire(CartDrawer::class);
    }

    #[Test]
    public function inactive_products_are_not_listed(): void
    {
        $this->product(['name' => 'Hidden dish', 'is_active' => false]);
        $this->product(['name' => 'Visible dish']);

        Livewire::test(ProductGrid::class)
            ->assertSee('Visible dish')
            ->assertDontSee('Hidden dish');
    }

    #[Test]
    public function sold_out_products_show_sold_out_and_cannot_be_added(): void
    {
        $product = $this->product(['name' => 'Leche flan', 'stock' => 0]);

        Livewire::test(ProductGrid::class)
            ->assertSee('Sold out')
            ->call('add', $product->id);

        $this->assertSame([], app(CartService::class)->items());
    }

    #[Test]
    public function adding_stores_only_the_product_id_and_quantity(): void
    {
        $product = $this->product();

        Livewire::test(ProductGrid::class)
            ->call('add', $product->id)
            ->call('add', $product->id);

        $this->assertSame([$product->id => 2], session(CartService::SESSION_KEY));
    }

    #[Test]
    public function an_offer_shows_a_badge_and_the_crossed_out_price(): void
    {
        $this->product(['name' => 'Pandesal', 'price' => '20.00', 'sale_price' => '15.00']);

        Livewire::test(ProductGrid::class)
            ->assertSee('-25%')
            ->assertSee('SAR 15')
            ->assertSee('SAR 20')
            ->assertSee('Offers');
    }

    #[Test]
    public function the_offers_filter_shows_only_products_on_offer(): void
    {
        $this->product(['name' => 'Pandesal', 'price' => '20.00', 'sale_price' => '15.00']);
        $this->product(['name' => 'Ensaymada']);

        Livewire::test(ProductGrid::class)
            ->set('filter', 'offers')
            ->assertSee('Pandesal')
            ->assertDontSee('Ensaymada');
    }

    #[Test]
    public function offers_filter_and_chip_fall_back_when_nothing_is_on_offer(): void
    {
        $this->product(['name' => 'Ensaymada']);

        Livewire::test(ProductGrid::class)
            ->set('filter', 'offers')
            ->assertSee('Ensaymada')
            ->assertDontSee('>Offers<', false);
    }

    #[Test]
    public function an_unknown_filter_shows_everything(): void
    {
        $this->product(['name' => 'Ensaymada']);

        Livewire::test(ProductGrid::class)
            ->set('filter', 'nonsense')
            ->assertSee('Ensaymada');
    }

    #[Test]
    public function the_offers_banner_appears_only_while_something_is_on_offer(): void
    {
        $this->product(['name' => 'Ensaymada']);
        $this->get('/')->assertOk()->assertDontSee('Offers on now');

        $this->product(['name' => 'Pandesal', 'price' => '20.00', 'sale_price' => '15.00']);
        $this->get('/')->assertOk()->assertSee('Offers on now');
    }

    #[Test]
    public function the_cart_shows_how_much_is_left_for_free_delivery(): void
    {
        $product = $this->product(['price' => '100.00']);
        app(CartService::class)->add($product->id);

        Livewire::test(CartDrawer::class)
            ->assertSee('Add SAR 50 more for free delivery');
    }

    #[Test]
    public function the_cart_announces_free_delivery_once_the_minimum_is_reached(): void
    {
        $product = $this->product(['price' => '100.00']);
        $cart = app(CartService::class);
        $cart->add($product->id);
        $cart->add($product->id);

        Livewire::test(CartDrawer::class)
            ->assertSee('You got free delivery!');
    }

    #[Test]
    public function the_cart_total_uses_the_offer_price(): void
    {
        $product = $this->product(['price' => '20.00', 'sale_price' => '15.00']);
        app(CartService::class)->add($product->id);

        Livewire::test(CartDrawer::class)
            ->assertSee('SAR 15')
            ->assertSee('You save');
    }

    #[Test]
    public function the_drawer_buttons_change_quantities_on_the_server(): void
    {
        $product = $this->product(['stock' => 2]);
        $cart = app(CartService::class);
        $cart->add($product->id);

        Livewire::test(CartDrawer::class)
            ->call('increment', $product->id)
            ->call('increment', $product->id); // third unit is above stock

        $this->assertSame([$product->id => 2], $cart->items());

        Livewire::test(CartDrawer::class)->call('remove', $product->id);
        $this->assertSame([], $cart->items());
    }
}