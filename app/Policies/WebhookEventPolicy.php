<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEvent;

/**
 * Webhook events belong to the integration, not to a user: anyone with
 * webhooks.view can inspect them, only admins can delete.
 */
class WebhookEventPolicy
{
    public function view(User $user, WebhookEvent $event): bool
    {
        return $user->hasPermission('webhooks.view');
    }

    public function delete(User $user, WebhookEvent $event): bool
    {
        return $user->isAdmin();
    }
}
