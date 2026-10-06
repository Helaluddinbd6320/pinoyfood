<?php

namespace Database\Seeders;

use App\Enums\StorageType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $cat = Category::pluck('id', 'slug');

        // slug, name, Tagalog name, category, price, cost, stock, storage, description, sale price
        $rows = [
            ['chicken-adobo', 'Chicken adobo (500g)', 'Adobong manok', 'ulam', 28, 15, 40, StorageType::Chilled, 'Soy, vinegar, garlic. Heat and serve.', 24],
            ['beef-sinigang', 'Beef sinigang', 'Sinigang na baka', 'ulam', 32, 19, 22, StorageType::Chilled, 'Sour tamarind soup, ready to heat.', null],
            ['lumpiang-shanghai', 'Lumpiang shanghai (20 pcs)', 'Lumpiang shanghai', 'ulam', 30, 17, 8, StorageType::Frozen, 'Frozen. Fry in 8 minutes.', null],
            ['chicken-longganisa', 'Chicken longganisa (6 pcs)', 'Longganisa', 'pantry', 24, 13, 25, StorageType::Frozen, 'Sweet Pinoy-style breakfast sausage.', null],
            ['pandesal', 'Pandesal (12 pcs)', 'Pandesal', 'baked', 15, 6, 60, StorageType::Ambient, 'Baked fresh on Fridays.', 12],
            ['ensaymada', 'Ensaymada (4 pcs)', 'Ensaymada', 'baked', 22, 10, 15, StorageType::Ambient, 'Soft, buttery, topped with cheese.', null],
            ['ube-halaya', 'Ube halaya (jar)', 'Ube halaya', 'sweets', 26, 12, 3, StorageType::Chilled, 'Slow-cooked purple yam jam.', null],
            ['leche-flan', 'Leche flan', 'Leche flan', 'sweets', 18, 8, 9, StorageType::Chilled, 'Silky caramel custard.', null],
            ['sawsawan-set', 'Sawsawan set', 'Sawsawan', 'pantry', 20, 9, 30, StorageType::Ambient, 'Vinegar, soy-calamansi and bagoong.', null],
        ];

        foreach ($rows as $i => [$slug, $name, $tl, $category, $price, $cost, $stock, $storage, $desc, $sale]) {
            Product::updateOrCreate(['slug' => $slug], [
                'category_id' => $cat[$category],
                'name' => $name,
                'name_tl' => $tl,
                'description' => $desc,
                'storage_type' => $storage,
                'price' => $price,
                'cost' => $cost,
                'sale_price' => $sale,
                'sale_starts_at' => $sale ? now()->subDay() : null,
                'sale_ends_at' => $sale ? now()->addDays(7) : null,
                'stock' => $stock,
                'track_stock' => true,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }
}