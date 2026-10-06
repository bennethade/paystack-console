<?php

namespace App\Services\Paystack;

use App\Enums\PaystackEnvironment;

/**
 * Paystack signs webhook payloads with HMAC-SHA512 of the raw request body
 * using the integration's secret key, sent as `x-paystack-signature`.
 * https://paystack.com/docs/payments/webhooks/#signature-validation
 */
class WebhookSignatureVerifier
{
    public function __construct(private readonly PaystackCredentials $credentials) {}

    /**
     * @return array{valid: bool, note: string}
     */
    public function verify(string $rawBody, ?string $signature, string $countryCode, PaystackEnvironment $environment): array
    {
        if ($signature === null || $signature === '') {
            return ['valid' => false, 'note' => 'Missing x-paystack-signature header.'];
        }

        $secret = $this->credentials->secretKey($countryCode, $environment);
        if ($secret === null) {
            return ['valid' => false, 'note' => "No {$environment->label()} secret key configured for {$countryCode}; cannot verify."];
        }

        $expected = hash_hmac('sha512', $rawBody, $secret);

        return hash_equals($expected, strtolower(trim($signature)))
            ? ['valid' => true, 'note' => 'Signature matches HMAC-SHA512 of the raw body.']
            : ['valid' => false, 'note' => 'Signature does not match. The payload may have been altered or signed with a different key.'];
    }

    public function isAllowedIp(?string $ip): bool
    {
        return $ip !== null && in_array($ip, config('paystack.webhooks.allowed_ips', []), true);
    }
}
