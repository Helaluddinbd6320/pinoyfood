<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        $category = Category::query()->first() ?? Category::factory()->create(['is_active' => true]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'is_active' => true,
            'track_stock' => true,
            'stock' => 3,
            'price' => '20.00',
        ], $attrs));
    }

    #[Test]
    public function adding_the_same_product_raises_the_quantity(): void
    {
        $product = $this->product();
        $cart = app(CartService::class);

        $this->assertSame('added', $cart->add($product->id));
        $this->assertSame('added', $cart->add($product->id));
        $this->assertSame([$product->id => 2], $cart->items());
        $this->assertSame(2, $cart->count());
    }

    #[Test]
    public function quantity_cannot_go_above_stock(): void
    {
        $product = $this->product(['stock' => 2]);
        $cart = app(CartService::class);

        $cart->add($product->id);
        $cart->add($product->id);

        $this->assertSame('max', $cart->add($product->id));
        $this->assertSame([$product->id => 2], $cart->items());
    }

    #[Test]
    public function sold_out_products_cannot_be_added(): void
    {
        $product = $this->product(['stock' => 0]);

        $this->assertSame('sold_out', app(CartService::class)->add($product->id));
        $this->assertSame([], app(CartService::class)->items());
    }

    #[Test]
    public function products_without_stock_tracking_have_no_limit(): void
    {
        $product = $this->product(['track_stock' => false, 'stock' => 0]);
        $cart = app(CartService::class);

        foreach (range(1, 5) as $unused) {
            $this->assertSame('added', $cart->add($product->id));
        }

        $this->assertSame([$product->id => 5], $cart->items());
    }

    #[Test]
    public function inactive_and_unknown_products_are_refused(): void
    {
        $inactive = $this->product(['is_active' => false]);
        $cart = app(CartService::class);

        $this->assertSame('unavailable', $cart->add($inactive->id));
        $this->assertSame('unavailable', $cart->add(999999));
    }

    #[Test]
    public function decrement_removes_the_line_at_zero(): void
    {
        $product = $this->product();
        $cart = app(CartService::class);

        $cart->add($product->id);
        $cart->add($product->id);
        $cart->decrement($product->id);
        $this->assertSame([$product->id => 1], $cart->items());

        $cart->decrement($product->id);
        $this->assertSame([], $cart->items());
    }

    #[Test]
    public function a_stored_cart_is_cleaned_when_stock_or_availability_changes(): void
    {
        $keep = $this->product(['stock' => 5]);
        $shrunk = $this->product(['stock' => 5]);
        $gone = $this->product(['stock' => 5]);

        session()->put(CartService::SESSION_KEY, [$keep->id => 2, $shrunk->id => 4, $gone->id => 1, 999999 => 1]);

        $shrunk->update(['stock' => 1]);
        $gone->update(['is_active' => false]);

        $this->assertSame([$keep->id => 2, $shrunk->id => 1], app(CartService::class)->items());
    }

    #[Test]
    public function bad_session_values_are_ignored(): void
    {
        $product = $this->product();

        session()->put(CartService::SESSION_KEY, [$product->id => -3, 0 => 2, 'x' => 'y']);

        $this->assertSame([], app(CartService::class)->items());
    }
}