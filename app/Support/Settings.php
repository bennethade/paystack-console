<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runtime system settings and feature flags, editable by admins.
 *
 * Feature flags can only narrow what the environment allows: e.g. live mode
 * requires PAYSTACK_LIVE_ENABLED=true in .env AND the live_mode flag here.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /**
     * Known settings with their defaults, group and description.
     */
    public const DEFINITIONS = [
        'registration_enabled' => ['group' => 'features', 'default' => true, 'type' => 'bool', 'description' => 'Allow new users to register.'],
        'live_mode_enabled' => ['group' => 'features', 'default' => false, 'type' => 'bool', 'description' => 'Allow live (production) requests. Also requires PAYSTACK_LIVE_ENABLED=true.'],
        'mock_mode' => ['group' => 'features', 'default' => false, 'type' => 'bool', 'description' => 'Return labelled mock responses instead of calling Paystack.'],
        'webhooks_enabled' => ['group' => 'features', 'default' => true, 'type' => 'bool', 'description' => 'Accept incoming Paystack webhook events.'],
        'history_enabled' => ['group' => 'features', 'default' => true, 'type' => 'bool', 'description' => 'Record request history.'],
        'default_role' => ['group' => 'general', 'default' => 'developer', 'type' => 'string', 'description' => 'Role assigned to newly registered users (developer or viewer).'],
        'announcement' => ['group' => 'general', 'default' => '', 'type' => 'string', 'description' => 'Banner text shown to all users (optional).'],
        'history_retention_days' => ['group' => 'general', 'default' => 30, 'type' => 'int', 'description' => 'Request history older than this is pruned by paystack:prune-history.'],
    ];

    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default ?? (self::DEFINITIONS[$key]['default'] ?? null);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $definition = self::DEFINITIONS[$key] ?? ['group' => 'general', 'description' => null, 'type' => 'string'];

        $value = match ($definition['type'] ?? 'string') {
            'bool' => (bool) $value,
            'int' => (int) $value,
            default => (string) $value,
        };

        Setting::updateOrCreate(
            ['key' => $key],
            ['group' => $definition['group'], 'value' => $value, 'description' => $definition['description']]
        );

        $this->flush();
    }

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            $stored = [];
        }

        $defaults = array_map(fn ($d) => $d['default'], self::DEFINITIONS);

        return $this->values = array_merge($defaults, $stored);
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    // Convenience accessors --------------------------------------------------

    public function liveModeAvailable(): bool
    {
        return (bool) config('paystack.live_enabled') && $this->bool('live_mode_enabled');
    }

    public function mockMode(): bool
    {
        return (bool) config('paystack.mock_mode') || $this->bool('mock_mode');
    }

    public function webhooksEnabled(): bool
    {
        return (bool) config('paystack.webhooks.enabled') && $this->bool('webhooks_enabled');
    }
}
