<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'list_price' => '20.00',
            'unit_price' => '20.00',
            'unit_cost' => '10.00',
            'qty' => 1,
            'line_total' => '20.00',
        ];
    }
}