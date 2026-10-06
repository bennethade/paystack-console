<?php

namespace App\Http\Controllers\Explorer;

use App\Enums\PaystackEnvironment;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Paystack\PaystackCountryResolver;
use App\Services\Paystack\PaystackCredentials;
use App\Services\Paystack\WebhookSignatureVerifier;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Webhook testing: endpoint URLs, received events and a signature checker.
 */
class WebhookController extends Controller
{
    public function index(Request $request, PaystackCountryResolver $countries, PaystackCredentials $credentials, Settings $settings): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:100'],
            'signature' => ['nullable', 'in:valid,invalid'],
            'environment' => ['nullable', 'in:test,live'],
        ]);

        $events = WebhookEvent::query()
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', 'like', '%'.addcslashes($v, '%_').'%'))
            ->when($filters['signature'] ?? null, fn ($q, $v) => $q->where('signature_valid', $v === 'valid'))
            ->when($filters['environment'] ?? null, fn ($q, $v) => $q->where('environment', $v))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $endpoints = [];
        foreach ($countries->enabled() as $country) {
            foreach (PaystackEnvironment::cases() as $env) {
                $endpoints[] = [
                    'country' => $country,
                    'environment' => $env,
                    'url' => route('webhooks.paystack', ['environment' => $env->value, 'country' => strtolower($country->code)]),
                    'configured' => $credentials->status($country->code, $env)['secret_configured'],
                ];
            }
        }

        return view('explorer.webhooks.index', [
            'events' => $events,
            'filters' => $filters,
            'endpoints' => $endpoints,
            'enabled' => $settings->webhooksEnabled(),
            'allowedIps' => config('paystack.webhooks.allowed_ips'),
            'enforceIps' => (bool) config('paystack.webhooks.enforce_ip_allowlist'),
            'isLocal' => in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true),
            'countries' => $countries->enabled(),
        ]);
    }

    public function show(WebhookEvent $event): View
    {
        $this->authorize('view', $event);

        return view('explorer.webhooks.show', ['event' => $event]);
    }

    /**
     * Re-run signature verification with the currently configured key.
     */
    public function reverify(WebhookEvent $event, WebhookSignatureVerifier $verifier): RedirectResponse
    {
        $this->authorize('view', $event);

        $environment = PaystackEnvironment::tryFrom($event->environment) ?? PaystackEnvironment::Test;
        $result = $verifier->verify($event->payload, $event->signature, (string) $event->country_code, $environment);

        $event->update(['signature_valid' => $result['valid'], 'verification_note' => $result['note']]);

        return back()->with('status', $result['note']);
    }

    /**
     * Check an arbitrary payload/signature pair against a configured key.
     * The key is used server-side only; the response is just valid/invalid.
     */
    public function verifySignature(Request $request, WebhookSignatureVerifier $verifier, PaystackCountryResolver $countries): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:'.(config('paystack.webhooks.max_payload_kb') * 1024)],
            'signature' => ['required', 'string', 'max:256'],
            'country' => ['required', 'string', 'size:2'],
            'environment' => ['required', 'in:test,live'],
        ]);

        abort_if($countries->find($data['country']) === null, 422, 'Unknown country.');

        return response()->json($verifier->verify(
            $data['payload'],
            $data['signature'],
            strtoupper($data['country']),
            PaystackEnvironment::from($data['environment']),
        ));
    }

    public function destroy(WebhookEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();

        return redirect()->route('explorer.webhooks.index')->with('status', 'Webhook event deleted.');
    }
}
