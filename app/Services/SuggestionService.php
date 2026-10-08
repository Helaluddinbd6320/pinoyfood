<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Spec 6.6. Phase 1: hand-made product_pairs. Phase 2: nightly co-purchases
 * plus a boost for what the signed-in customer bought before. Guests with no
 * history get best sellers. The input is only product ids, never prices.
 */
class SuggestionService
{
    public const LIMIT = 3;

    /** Orders older than this are ignored by suggestions and by the nightly rebuild. */
    public const WINDOW_DAYS = 180;

    /** Added to the score of a product the customer bought before. A guess: tune with real data. */
    public const HISTORY_BOOST = 6;

    /**
     * "Pairs well with": up to LIMIT hand-made pairs for the cart items.
     *
     * @param  array<int|string, int|string>  $cart  product id => qty
     * @return Collection<int, Product>
     */
    public function pairs(array $cart): Collection
    {
        $cartIds = $this->cartIds($cart);

        if ($cartIds === []) {
            return collect();
        }

        $rows = DB::table('product_pairs')
            ->whereIn('product_id', $cartIds)
            ->whereNotIn('paired_product_id', $cartIds)
            ->get(['paired_product_id', 'sort_order']);

        $rank = [];
        foreach ($rows as $row) {
            $id = (int) $row->paired_product_id;
            $rank[$id]['n'] = ($rank[$id]['n'] ?? 0) + 1;
            $rank[$id]['s'] = min($rank[$id]['s'] ?? 999, (int) $row->sort_order);
        }

        // Paired with more cart items first, then the admin's sort order.
        uasort($rank, fn (array $a, array $b) => [$b['n'], $a['s']] <=> [$a['n'], $b['s']]);

        $products = $this->available(array_keys($rank));

        return collect(array_keys($rank))
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * "Picked for you": co-purchase score, plus a boost for the customer's own
     * history, filled up with best sellers.
     *
     * @param  array<int|string, int|string>  $cart
     * @param  array<int, int>  $exclude  product ids already shown elsewhere
     * @return Collection<int, array{product: Product, reason: string}>
     */
    public function picked(array $cart, ?Customer $customer = null, array $exclude = []): Collection
    {
        $cartIds = $this->cartIds($cart);

        if ($cartIds === []) {
            return collect();
        }

        $skip = array_values(array_unique([...$cartIds, ...$exclude]));

        $score = [];
        $reason = [];

        $co = DB::table('product_co_purchases')
            ->whereIn('product_id', $cartIds)
            ->whereNotIn('other_product_id', $skip)
            ->groupBy('other_product_id')
            ->selectRaw('other_product_id, SUM(times_together) as n')
            ->get();

        foreach ($co as $row) {
            $id = (int) $row->other_product_id;
            $score[$id] = (int) $row->n;
            $reason[$id] = 'Often ordered together';
        }

        if ($customer !== null) {
            foreach ($this->history($customer) as $id) {
                if (in_array($id, $skip, true)) {
                    continue;
                }

                $score[$id] = ($score[$id] ?? 0) + self::HISTORY_BOOST;
                $reason[$id] = 'You ordered this before';
            }
        }

        // Highest score first, ties by id so the order is stable.
        uksort($score, fn (int $a, int $b) => [$score[$b], $a] <=> [$score[$a], $b]);

        $order = $score;

        foreach ($this->bestSellers() as $id) {
            if (! in_array($id, $skip, true) && ! isset($order[$id])) {
                $order[$id] = 0;
                $reason[$id] = 'Popular right now';
            }
        }

        $products = $this->available(array_keys($order));

        return collect(array_keys($order))
            ->map(fn (int $id) => $products->has($id)
                ? ['product' => $products->get($id), 'reason' => $reason[$id]]
                : null)
            ->filter()
            ->take(self::LIMIT)
            ->values();
    }

    /** @return array<int, int> */
    private function cartIds(array $cart): array
    {
        $ids = [];

        foreach ($cart as $id => $qty) {
            if ((int) $id > 0 && (int) $qty > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Same rules as CartService::available(), and not sold out. */
    private function available(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->with('category')
            ->whereKey($ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->where('track_stock', false)->orWhere('stock', '>', 0))
            ->get()
            ->keyBy('id');
    }

    /** @return array<int, int> products the customer bought before (cancelled orders ignored) */
    private function history(Customer $customer): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customer->id)
            ->where('orders.status', '!=', 'cancelled')
            ->whereNotNull('order_items.product_id')
            ->distinct()
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    private function bestSellers(): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.qty) DESC')
            ->limit(10)
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}