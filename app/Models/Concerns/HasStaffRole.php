<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;

trait HasStaffRole
{
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isRider(): bool
    {
        return $this->role === UserRole::Rider;
    }

    /** Admin or staff: people who may see customer data and all orders. */
    public function isBackOffice(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function scopeRiders(Builder $query): Builder
    {
        return $query->where('role', UserRole::Rider->value)->where('is_active', true);
    }
}