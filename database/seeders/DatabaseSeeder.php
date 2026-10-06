<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AreaSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,   // needs categories
            SettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}