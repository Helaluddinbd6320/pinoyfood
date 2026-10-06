<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BackOfficePolicy
{
    public function viewAny(User $user): bool { return $user->isBackOffice(); }
    public function view(User $user, Model $record): bool { return $user->isBackOffice(); }
    public function create(User $user): bool { return $user->isBackOffice(); }
    public function update(User $user, Model $record): bool { return $user->isBackOffice(); }

    // Deleting is admin only. Staff can deactivate instead.
    public function delete(User $user, Model $record): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, Model $record): bool { return $user->isAdmin(); }
    public function restoreAny(User $user): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Model $record): bool { return $user->isAdmin(); }
    public function forceDeleteAny(User $user): bool { return $user->isAdmin(); }
}