<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The cart lives in the session as [productId => qty]. Nothing else is stored:
 * names and prices are always read from the database (spec 6.9).
 */
class CartService
{
    public const SESSION_KEY = 'cart';

    /** Cleaned cart: unknown, inactive or sold-out products are dropped, quantities are capped to stock. */
    public function items(): array
    {
        $raw = session()->get(self::SESSION_KEY, []);

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $wanted = [];
        foreach ($raw as $id => $qty) {
            if ((int) $id > 0 && (int) $qty > 0) {
                $wanted[(int) $id] = (int) $qty;
            }
        }

        $products = $this->available(array_keys($wanted));
        $clean = [];

        foreach ($wanted as $id => $qty) {
            $product = $products->get($id);

            if (! $product) {
                continue;
            }

            $max = $this->maxQty($product);
            if ($max !== null) {
                $qty = min($qty, $max);
            }

            if ($qty > 0) {
                $clean[$id] = $qty;
            }
        }

        if ($clean !== $raw) {
            $this->save($clean);
        }

        return $clean;
    }

    /** @return 'added'|'max'|'sold_out'|'unavailable' */
    public function add(int $productId): string
    {
        $product = $this->available([$productId])->get($productId);

        if (! $product) {
            return 'unavailable';
        }

        $cart = $this->items();
        $next = ($cart[$productId] ?? 0) + 1;
        $max = $this->maxQty($product);

        if ($max !== null && $next > $max) {
            return $max === 0 ? 'sold_out' : 'max';
        }

        $cart[$productId] = $next;
        $this->save($cart);

        return 'added';
    }

    public function decrement(int $productId): void
    {
        $cart = $this->items();

        if (! isset($cart[$productId])) {
            return;
        }

        if ($cart[$productId] <= 1) {
            unset($cart[$productId]);
        } else {
            $cart[$productId]--;
        }

        $this->save($cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->items();
        unset($cart[$productId]);
        $this->save($cart);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    /** null = unlimited (stock is not tracked). */
    private function maxQty(Product $product): ?int
    {
        return $product->track_stock ? max(0, (int) $product->stock) : null;
    }

    private function available(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->whereKey($ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()
            ->keyBy('id');
    }

    private function save(array $cart): void
    {
        $cart === []
            ? session()->forget(self::SESSION_KEY)
            : session()->put(self::SESSION_KEY, $cart);
    }
}