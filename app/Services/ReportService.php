<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Numbers for the dashboard widgets and the Reports page. Cancelled orders never count as sales. */
class ReportService
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function range(?string $from, ?string $to): array
    {
        $start = $this->day($from, now()->subDays(29))->startOfDay();
        $end = $this->day($to, now())->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    /** @return array{sales: string, orders: int, waiting: int, opted_in: int, to_verify: int} */
    public function today(): array
    {
        $from = now()->startOfDay();
        $to = now()->endOfDay();

        $live = fn () => DB::table('orders')->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled');

        return [
            'sales' => $this->money((float) $live()->sum('total')),
            'orders' => $live()->count(),
            'waiting' => DB::table('orders')->where('status', 'new')->count(),
            'opted_in' => Customer::query()->where('whatsapp_opt_in', true)->count(),
            'to_verify' => DB::table('payments')->where('status', 'awaiting_verification')->count(),
        ];
    }

    /** @return array<string, string|int> */
    public function summary(Carbon $from, Carbon $to): array
    {
        $orders = fn () => DB::table('orders')->whereBetween('created_at', [$from, $to]);
        $live = fn () => $orders()->where('status', '!=', 'cancelled');
        $items = fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled');

        $count = $live()->count();
        $sales = (float) $live()->sum('total');

        return [
            'orders' => $count,
            'sales' => $this->money($sales),
            'average' => $this->money($count > 0 ? $sales / $count : 0),
            'delivery_fees' => $this->money((float) $live()->sum('delivery_fee')),
            'delivered' => $orders()->where('status', 'delivered')->count(),
            'cancelled' => $orders()->where('status', 'cancelled')->count(),
            'no_answer' => $orders()->where('status', 'no_answer')->count(),
            'offer_savings' => $this->money((float) $items()
                ->selectRaw('COALESCE(SUM((order_items.list_price - order_items.unit_price) * order_items.qty), 0) as v')->value('v')),
            // Only items with a cost snapshot count toward margin.
            'margin' => $this->money((float) $items()->whereNotNull('order_items.unit_cost')
                ->selectRaw('COALESCE(SUM((order_items.unit_price - order_items.unit_cost) * order_items.qty), 0) as v')->value('v')),
        ];
    }

    /** @return Collection<int, object{source: string, orders: int, sales: string}> */
    public function bySource(Carbon $from, Carbon $to): Collection
    {
        return DB::table('orders')
            ->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled')
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) as orders, SUM(total) as sales')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => (object) ['source' => (string) $r->source, 'orders' => (int) $r->orders, 'sales' => $this->money((float) $r->sales)]);
    }

    /** @return Collection<int, object{area: string, orders: int, sales: string}> */
    public function byArea(Carbon $from, Carbon $to): Collection
    {
        return DB::table('orders')
            ->leftJoin('areas', 'areas.id', '=', 'orders.area_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')
            ->groupBy('areas.name')
            ->selectRaw('areas.name as area, COUNT(*) as orders, SUM(orders.total) as sales')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => (object) ['area' => (string) ($r->area ?? 'No area'), 'orders' => (int) $r->orders, 'sales' => $this->money((float) $r->sales)]);
    }

    /** @return Collection<int, object{name: string, qty: int, revenue: string}> */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name as name, SUM(order_items.qty) as qty, SUM(order_items.line_total) as revenue')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (object) ['name' => (string) $r->name, 'qty' => (int) $r->qty, 'revenue' => $this->money((float) $r->revenue)]);
    }

    /** Active, stock-tracked products at or below their own low-stock threshold. */
    public function lowStock(int $limit = 10): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->orderBy('stock')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    private function day(?string $value, Carbon $fallback): Carbon
    {
        if ($value === null || trim($value) === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}