<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $digits = fake()->unique()->numerify('5########');   // Saudi mobile without the leading 0

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_raw' => '0'.$digits,
            'phone_e164' => '+966'.$digits,
            'phone_search' => $digits,
            'area_id' => Area::factory(),
            'whatsapp_opt_in' => false,
            'referral_code' => strtoupper(fake()->unique()->bothify('??####??')),
        ];
    }
}