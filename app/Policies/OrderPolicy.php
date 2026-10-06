<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // the resource query limits riders to their own orders
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isBackOffice()
            || ($user->isRider() && (int) $order->rider_id === $user->id);
    }

    public function create(User $user): bool
    {
        return false; // step 8 uses its own page and OrderService
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isBackOffice();
    }

    // Orders are cancelled, never deleted.
    public function delete(User $user, Order $order): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, Order $order): bool { return false; }
    public function forceDelete(User $user, Order $order): bool { return false; }

    /** Custom ability: may this user move the order to status $to? */
    public function changeStatus(User $user, Order $order, string $to): bool
    {
        if ($user->isBackOffice()) {
            return true;
        }

        return $user->isRider()
            && (int) $order->rider_id === $user->id
            && in_array($to, ['out_for_delivery', 'delivered'], true);
    }

    public function assignRider(User $user, Order $order): bool
    {
        return $user->isBackOffice();
    }
}