<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BroadcastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'body' => "Kumusta! Fresh pandesal this Friday.\nReply STOP to unsubscribe.",
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}