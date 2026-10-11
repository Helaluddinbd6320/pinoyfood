<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use App\Support\ProductLook;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProductGrid extends Component
{
    /** all, offers, or a category slug. Checked again on every render. */
    #[Url(as: 'filter', except: 'all')]
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function add(int $productId): void
    {
        $name = Product::query()->whereKey($productId)->value('name');
        $status = app(CartService::class)->add($productId);

        $message = match ($status) {
            'added' => ($name ?? 'Item') . ' added to cart',
            'max' => 'That is all we have in stock',
            'sold_out' => 'Sorry, this item is sold out',
            default => 'This item is not available',
        };

        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: $message);
    }

    public function render()
    {
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = $products->pluck('category')->unique('id')->sortBy('sort_order')->values();
        $hasOffers = $products->contains(fn(Product $product) => $product->is_on_offer);

        $filter = $this->filter;
        if ($filter === 'offers' && ! $hasOffers) {
            $filter = 'all';
        } elseif (! in_array($filter, ['all', 'offers'], true) && ! $categories->contains('slug', $filter)) {
            $filter = 'all';
        }

        $cards = $products
            ->filter(fn(Product $product) => match ($filter) {
                'all' => true,
                'offers' => $product->is_on_offer,
                default => $product->category->slug === $filter,
            })
            ->map(fn(Product $product) => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'effective' => $product->effective_price,
                'on_offer' => $product->is_on_offer,
                'discount' => $product->discount_percent,
                'until' => $product->is_on_offer && $product->sale_ends_at
                    ? $product->sale_ends_at->format('D j M')
                    : null,
                'sold_out' => $product->track_stock && (int) $product->stock <= 0,
                ...ProductLook::for($product),
            ])
            ->values();

        return view('livewire.product-grid', [
            'cards' => $cards,
            'categories' => $categories,
            'hasOffers' => $hasOffers,
            'active' => $filter,
        ]);
    }
}
