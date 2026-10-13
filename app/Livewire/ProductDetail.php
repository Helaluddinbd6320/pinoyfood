<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use App\Services\SuggestionService;
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
        $this->toast($cart->add($this->productId));
    }

    public function addRelated(int $id, CartService $cart): void
    {
        $this->toast($cart->add($id));
    }

    private function toast(string $status): void
    {
        $this->dispatch('cart-updated');
        $this->dispatch('cart-toast', message: match ($status) {
            'added' => 'Added to your cart',
            'max' => 'That is all we have in stock',
            'sold_out' => 'Sorry, this item is sold out',
            default => 'This item is not available',
        });
    }

    public function render(SuggestionService $suggestions)
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
            'related' => $product ? $this->related($product, $suggestions) : collect(),
            'showTl' => $product && filled($product->name_tl)
                && mb_strtolower(trim((string) $product->name_tl)) !== mb_strtolower(trim($product->name)),
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

    /** Hand-made pairs first, then other products from the same category. Never the product itself. */
    private function related(Product $product, SuggestionService $suggestions)
    {
        $items = $suggestions->pairs([$product->id => 1]);

        if ($items->count() < SuggestionService::LIMIT) {
            $skip = $items->pluck('id')->push($product->id)->all();

            $more = Product::query()
                ->where('category_id', $product->category_id)
                ->whereNotIn('id', $skip)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('track_stock', false)->orWhere('stock', '>', 0))
                ->orderBy('sort_order')->orderBy('name')
                ->limit(SuggestionService::LIMIT - $items->count())
                ->get();

            $items = $items->concat($more);
        }

        return $items->take(SuggestionService::LIMIT)->values();
    }
}