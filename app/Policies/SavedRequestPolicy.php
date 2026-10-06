<?php

namespace App\Policies;

use App\Models\SavedRequest;
use App\Models\User;

class SavedRequestPolicy
{
    public function view(User $user, SavedRequest $saved): bool
    {
        return $saved->user_id === $user->id;
    }

    public function delete(User $user, SavedRequest $saved): bool
    {
        return $saved->user_id === $user->id;
    }
}
