<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName(),
            'delivery_fee_override' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}