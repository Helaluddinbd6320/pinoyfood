<?php

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Area;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_no' => 'PF-'.fake()->unique()->numberBetween(1000, 999999),
            'customer_id' => Customer::factory(),
            'source' => OrderSource::Website,
            'status' => OrderStatus::New,
            'customer_name' => fake()->name(),
            'phone_raw' => '0501230142',
            'phone_e164' => '+966501230142',
            'area_id' => Area::factory(),
            'subtotal' => '100.00',
            'delivery_fee' => '10.00',
            'free_delivery_applied' => false,
            'discount_total' => '0.00',
            'total' => '110.00',
            'paid_online_total' => '0.00',
            'amount_due_on_delivery' => '110.00',
        ];
    }
}