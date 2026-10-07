<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use App\Services\PricingService;
use App\Services\SettingsService;
use App\Support\ProductLook;
use Livewire\Attributes\On;
use Livewire\Component;

class CartDrawer extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // Listening is enough: Livewire re-renders after the event and render() re-reads the session.
    }

    public function increment(int $productId): void
    {
        if (app(CartService::class)->add($productId) === 'max') {
            $this->dispatch('cart-toast', message: 'That is all we have in stock');
        }
    }

    public function decrement(int $productId): void
    {
        app(CartService::class)->decrement($productId);
    }

    public function remove(int $productId): void
    {
        app(CartService::class)->remove($productId);
    }

    public function render()
    {
        $items = app(CartService::class)->items();

        $products = $items === []
            ? collect()
            : Product::query()->with('category')->whereKey(array_keys($items))->get()->keyBy('id');

        $lines = [];
        foreach ($items as $id => $qty) {
            $product = $products->get($id);

            if (! $product) {
                continue;
            }

            $lines[] = [
                'id' => $id,
                'name' => $product->name,
                'qty' => $qty,
                'unit' => $product->effective_price,
                'total' => bcmul((string) $product->effective_price, (string) $qty, 2),
                'on_offer' => $product->is_on_offer,
                ...ProductLook::for($product),
            ];
        }

        // The area is not known yet, so this uses the global fee. Checkout (step 7) re-quotes with the area.
        $quote = app(PricingService::class)->quote($items, null, null, false);
        $settings = app(SettingsService::class);

        $freeOn = (bool) $settings->get('free_delivery_enabled');
        $freeMin = (string) $settings->get('free_delivery_min');
        $progress = 0;

        if ($freeOn && bccomp($freeMin, '0', 2) === 1) {
            $ratio = bcdiv(bcmul((string) $quote->subtotal, '100', 2), $freeMin, 2);
            $progress = (int) min(100, floor((float) $ratio));
        }

        return view('livewire.cart-drawer', [
            'lines' => $lines,
            'count' => array_sum($items),
            'quote' => $quote,
            'freeOn' => $freeOn,
            'remaining' => (string) ($quote->amountToFreeDelivery ?? '0'),
            'progress' => $progress,
            'minOrder' => (string) $settings->get('min_order_amount'),
        ]);
    }
}