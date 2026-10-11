<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductDetail extends Component
{
    #[Locked]
    public int $productId = 0;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function add(CartService $cart): void
    {
        $status = $cart->add($this->productId);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: match ($status) {
            'added' => 'Added to your cart',
            'max' => 'That is all we have in stock',
            'sold_out' => 'Sorry, this item is sold out',
            default => 'This item is not available',
        });
    }

    public function render()
    {
        $product = Product::query()
            ->with('category')
            ->whereKey($this->productId)
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->first();

        $allergens = $product && is_array($product->allergens)
            ? array_values(array_filter(array_map('trim', array_map('strval', $product->allergens))))
            : [];

        $url = $product ? route('product.show', $product->slug) : null;

        return view('livewire.product-detail', [
            'product' => $product,
            'allergens' => $allergens,
            'soldOut' => $product && $product->track_stock && (int) $product->stock <= 0,
            'until' => $product && $product->is_on_offer && $product->sale_ends_at
                ? $product->sale_ends_at->format('D j M')
                : null,
            'storage' => $product && $product->storage_type
                ? ucfirst((string) ($product->storage_type->value ?? $product->storage_type))
                : null,
            'share' => $product
                ? 'https://wa.me/?text='.rawurlencode("{$product->name}: SAR {$product->effective_price} at Pinoy Food KSA. Order here: {$url}")
                : null,
        ]);
    }
}