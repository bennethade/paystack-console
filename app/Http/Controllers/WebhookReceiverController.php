<?php

namespace App\Http\Controllers;

use App\Enums\PaystackEnvironment;
use App\Models\WebhookEvent;
use App\Services\Paystack\PaystackCountryResolver;
use App\Services\Paystack\WebhookSignatureVerifier;
use App\Support\SensitiveDataMasker;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint Paystack posts events to:
 *   POST /webhooks/paystack/{environment}/{country}
 *
 * The URL identifies which secret key verifies the signature. Events are
 * stored (including ones that fail verification, flagged as such) so they can
 * be inspected in the explorer. CSRF is excluded for this route only.
 */
class WebhookReceiverController extends Controller
{
    private const STORED_HEADERS = [
        'content-type', 'user-agent', 'x-paystack-signature', 'x-forwarded-for', 'content-length',
    ];

    public function __invoke(
        Request $request,
        string $environment,
        string $country,
        Settings $settings,
        PaystackCountryResolver $countries,
        WebhookSignatureVerifier $verifier,
        SensitiveDataMasker $masker,
    ): JsonResponse {
        abort_unless($settings->webhooksEnabled(), 404);

        $env = PaystackEnvironment::tryFrom($environment);
        $market = $countries->find($country);
        abort_if($env === null || $market === null, 404);

        $raw = $request->getContent();
        if (strlen($raw) > config('paystack.webhooks.max_payload_kb') * 1024) {
            return response()->json(['status' => 'rejected', 'message' => 'Payload too large'], 413);
        }

        $ip = $request->ip();
        $ipAllowed = $verifier->isAllowedIp($ip);
        if (config('paystack.webhooks.enforce_ip_allowlist') && ! $ipAllowed) {
            Log::channel('paystack')->warning('Webhook rejected: IP not allow-listed', ['ip' => $ip, 'country' => $market->code]);

            return response()->json(['status' => 'rejected'], 403);
        }

        $signature = $request->header('x-paystack-signature');
        $verification = $verifier->verify($raw, $signature, $market->code, $env);

        $payload = json_decode($raw, true);
        $data = is_array($payload) ? ($payload['data'] ?? []) : [];

        $headers = [];
        foreach (self::STORED_HEADERS as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = $name === 'x-paystack-signature'
                    ? $request->header($name)
                    : $masker->maskString((string) $request->header($name));
            }
        }

        $event = WebhookEvent::create([
            'environment' => $env->value,
            'country_code' => $market->code,
            'event' => is_array($payload) ? mb_substr((string) ($payload['event'] ?? ''), 0, 191) ?: null : null,
            'reference' => is_array($data) ? mb_substr((string) ($data['reference'] ?? $data['transfer_code'] ?? ''), 0, 191) ?: null : null,
            'payload' => $raw,
            'headers' => $headers,
            'signature' => $signature ? mb_substr($signature, 0, 256) : null,
            'signature_valid' => $verification['valid'],
            'verification_note' => $verification['note'],
            'source_ip' => $ip,
            'ip_allowed' => $ipAllowed,
        ]);

        Log::channel('paystack')->info('Webhook received', [
            'uuid' => $event->uuid,
            'event' => $event->event,
            'country' => $market->code,
            'environment' => $env->value,
            'signature_valid' => $verification['valid'],
        ]);

        return $verification['valid']
            ? response()->json(['status' => 'ok'])
            : response()->json(['status' => 'invalid_signature'], 401);
    }
}
