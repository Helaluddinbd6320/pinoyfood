<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'free_delivery_enabled' => true,
            'free_delivery_min' => 150,
            'delivery_fee' => 10,
            'allow_prepay_delivery_fee' => true,
            'require_deposit_for_free_delivery' => false,
            'deposit_amount' => 10,
            'min_order_amount' => 0,
            'prepay_instructions' => 'Payment instructions will be added before launch.',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}