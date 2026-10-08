<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SuggestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildCoPurchases extends Command
{
    protected $signature = 'suggestions:rebuild';

    protected $description = 'Rebuild product_co_purchases from recent orders (spec 4.14)';

    public function handle(): int
    {
        $since = now()->subDays(SuggestionService::WINDOW_DAYS);
        $now = now();

        // The join is symmetric, so every pair is stored in both directions.
        $pairs = DB::table('order_items as a')
            ->join('order_items as b', fn ($join) => $join
                ->on('b.order_id', '=', 'a.order_id')
                ->whereColumn('b.product_id', '!=', 'a.product_id'))
            ->join('orders as o', 'o.id', '=', 'a.order_id')
            ->where('o.status', '!=', 'cancelled')
            ->where('o.created_at', '>=', $since)
            ->whereNotNull('a.product_id')
            ->whereNotNull('b.product_id')
            ->groupBy('a.product_id', 'b.product_id')
            ->selectRaw('a.product_id as product_id, b.product_id as other_product_id, COUNT(DISTINCT a.order_id) as times_together')
            ->get();

        DB::transaction(function () use ($pairs, $now): void {
            DB::table('product_co_purchases')->delete();

            foreach ($pairs->chunk(500) as $chunk) {
                DB::table('product_co_purchases')->insert(
                    $chunk->map(fn ($row) => [
                        'product_id' => (int) $row->product_id,
                        'other_product_id' => (int) $row->other_product_id,
                        'times_together' => (int) $row->times_together,
                        'updated_at' => $now,
                    ])->all(),
                );
            }
        });

        $this->info('Co-purchase rows: '.$pairs->count());

        return self::SUCCESS;
    }
}