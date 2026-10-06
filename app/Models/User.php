<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;   // added
use Filament\Panel;                            // added
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser   // added
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // added. Step 5 replaces this with role and is_active checks.
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}