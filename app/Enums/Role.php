<?php

namespace App\Enums;

/**
 * Application roles and the permissions each one grants.
 *
 * Permissions are registered as Gates in AppServiceProvider, so views and
 * routes use the standard @can / can: middleware.
 */
enum Role: string
{
    case Admin = 'admin';
    case Developer = 'developer';
    case Viewer = 'viewer';

    public const PERMISSIONS = [
        'explorer.view',
        'explorer.execute',
        'explorer.execute-live',
        'webhooks.view',
        'admin.access',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Developer => 'Developer',
            self::Viewer => 'Viewer (read-only)',
        };
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => self::PERMISSIONS,
            // Live execution additionally requires users.can_use_live.
            self::Developer => ['explorer.view', 'explorer.execute', 'explorer.execute-live', 'webhooks.view'],
            self::Viewer => ['explorer.view'],
        };
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
