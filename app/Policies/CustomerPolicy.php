<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CustomerPolicy extends BackOfficePolicy
{
    // Customers are created by checkout and manual orders, never by hand.
    public function create(User $user): bool
    {
        return false;
    }

    // orders.customer_id is restrictOnDelete, so a hard delete would fail anyway.
    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
