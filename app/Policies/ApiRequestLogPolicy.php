<?php

namespace App\Policies;

use App\Models\ApiRequestLog;
use App\Models\User;

/**
 * Users only see their own history; admins see everything.
 */
class ApiRequestLogPolicy
{
    public function view(User $user, ApiRequestLog $log): bool
    {
        return $user->isAdmin() || $log->user_id === $user->id;
    }

    public function delete(User $user, ApiRequestLog $log): bool
    {
        return $user->isAdmin() || $log->user_id === $user->id;
    }
}
