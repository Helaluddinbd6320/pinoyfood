<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set. Admin user skipped.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->password = $password;   // hashed by the model cast
        }

        $user->name = env('ADMIN_NAME', 'Admin');
        $user->role = 'admin';
        $user->is_active = true;
        $user->save();
    }
}