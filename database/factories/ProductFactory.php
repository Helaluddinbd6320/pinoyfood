<?php

namespace Database\Factories;

use App\Enums\StorageType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->numberBetween(10, 60);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'storage_type' => StorageType::Ambient,
            'price' => number_format($price, 2, '.', ''),
            'cost' => number_format($price / 2, 2, '.', ''),
            'stock' => 20,
            'track_stock' => true,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function onOffer(string $salePrice, int $days = 7): static
    {
        return $this->state(fn () => [
            'sale_price' => $salePrice,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDays($days),
        ]);
    }
}