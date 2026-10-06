<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => PaymentType::DeliveryFeePrepay,
            'method' => 'stc_pay',
            'amount' => '10.00',
            'status' => PaymentStatus::AwaitingProof,
        ];
    }
}