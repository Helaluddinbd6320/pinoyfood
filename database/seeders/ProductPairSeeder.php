<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo pairings from the storefront demo. Products are found by name prefix
 * and missing ones are skipped. Check them against the real menu.
 */
class ProductPairSeeder extends Seeder
{
    private const PAIRS = [
        'Chicken adobo' => ['Sawsawan', 'Leche flan', 'Pandesal'],
        'Beef sinigang' => ['Sawsawan', 'Leche flan', 'Pandesal'],
        'Lumpia' => ['Sawsawan', 'Ube halaya'],
        'Chicken longganisa' => ['Pandesal', 'Sawsawan'],
        'Pandesal' => ['Chicken longganisa', 'Ube halaya'],
        'Ensaymada' => ['Ube halaya', 'Leche flan'],
        'Ube halaya' => ['Pandesal', 'Ensaymada'],
        'Leche flan' => ['Chicken adobo', 'Ensaymada'],
        'Sawsawan' => ['Chicken adobo', 'Lumpia'],
    ];

    public function run(): void
    {
        foreach (self::PAIRS as $from => $targets) {
            $product = $this->find($from);

            if ($product === null) {
                continue;
            }

            foreach ($targets as $sort => $to) {
                $paired = $this->find($to);

                if ($paired === null || $paired->id === $product->id) {
                    continue;
                }

                DB::table('product_pairs')->insertOrIgnore([
                    'product_id' => $product->id,
                    'paired_product_id' => $paired->id,
                    'sort_order' => $sort,
                ]);
            }
        }
    }

    private function find(string $prefix): ?Product
    {
        return Product::query()->where('name', 'like', $prefix.'%')->orderBy('id')->first();
    }
}