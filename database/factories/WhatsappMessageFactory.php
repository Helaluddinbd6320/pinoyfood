<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsappMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'direction' => 'out',
            'kind' => 'offer',
            'body' => fake()->sentence(),
            'status' => 'queued',
        ];
    }
}