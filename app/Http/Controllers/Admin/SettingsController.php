<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * System settings and feature flags.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'definitions' => Settings::DEFINITIONS,
            'values' => $this->settings->all(),
            'environment' => [
                'PAYSTACK_LIVE_ENABLED' => (bool) config('paystack.live_enabled'),
                'PAYSTACK_MOCK_MODE' => (bool) config('paystack.mock_mode'),
                'PAYSTACK_WEBHOOKS_ENABLED' => (bool) config('paystack.webhooks.enabled'),
                'PAYSTACK_WEBHOOK_ENFORCE_IPS' => (bool) config('paystack.webhooks.enforce_ip_allowlist'),
                'APP_DEBUG' => (bool) config('app.debug'),
                'SESSION_SECURE_COOKIE' => (bool) config('session.secure'),
                'SESSION_ENCRYPT' => (bool) config('session.encrypt'),
            ],
            'webhookCount' => WebhookEvent::count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (Settings::DEFINITIONS as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'bool' => ['nullable', 'boolean'],
                'int' => ['required', 'integer', 'min:1', 'max:3650'],
                default => ['nullable', 'string', 'max:500'],
            };
        }
        $rules['default_role'] = ['required', Rule::in([Role::Developer->value, Role::Viewer->value])];

        $data = $request->validate($rules);

        $changes = [];
        foreach (Settings::DEFINITIONS as $key => $definition) {
            $value = $definition['type'] === 'bool' ? $request->boolean($key) : ($data[$key] ?? '');
            if ($this->settings->get($key) !== $value) {
                $changes[$key] = $value;
            }
            $this->settings->set($key, $value);
        }

        $this->audit->record('admin.settings_updated', meta: $changes);

        return back()->with('status', 'Settings saved.');
    }

    public function purgeWebhooks(Request $request): RedirectResponse
    {
        $count = WebhookEvent::query()->delete();
        $this->audit->record('admin.webhooks_purged', meta: ['deleted' => $count]);

        return back()->with('status', "Deleted {$count} webhook events.");
    }
}
