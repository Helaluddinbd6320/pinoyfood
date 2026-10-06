<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasStaffRole;
use App\Enums\UserRole;



class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasStaffRole;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    // Placeholder from step 1. Step 5 replaces it with role and is_active checks.
   public function canAccessPanel(Panel $panel): bool
{
    return (bool) $this->is_active && $this->role instanceof UserRole;
}

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'rider_id');
    }
}