<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Admins cannot change their own role or status, so the last admin can
     * never lock everyone out by accident.
     */
    public function changeAccess(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->id !== $target->id;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->id !== $target->id;
    }
}
