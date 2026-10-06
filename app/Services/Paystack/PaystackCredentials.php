<?php

namespace App\Services\Paystack;

use App\Enums\PaystackEnvironment;

/**
 * The ONLY class that reads Paystack keys.
 *
 * Keys are returned exclusively to PaystackClient (for the Authorization
 * header) and WebhookSignatureVerifier (for HMAC verification). Everything
 * else, including every view, gets status() which reports whether a key is
 * configured and whether its prefix matches the environment, never the key.
 */
class PaystackCredentials
{
    public function secretKey(string $countryCode, PaystackEnvironment $environment): ?string
    {
        return $this->value($countryCode, $environment, 'secret');
    }

    public function publicKey(string $countryCode, PaystackEnvironment $environment): ?string
    {
        return $this->value($countryCode, $environment, 'public');
    }

    /**
     * Key for the given auth type: secret | public.
     */
    public function keyFor(string $auth, string $countryCode, PaystackEnvironment $environment): ?string
    {
        return $auth === 'public'
            ? $this->publicKey($countryCode, $environment)
            : $this->secretKey($countryCode, $environment);
    }

    /**
     * @return array{secret_configured: bool, public_configured: bool, secret_prefix_ok: bool, public_prefix_ok: bool, env_vars: array<string, string>}
     */
    public function status(string $countryCode, PaystackEnvironment $environment): array
    {
        $secret = $this->secretKey($countryCode, $environment);
        $public = $this->publicKey($countryCode, $environment);
        $code = strtoupper($countryCode);
        $env = strtoupper($environment->value);

        return [
            'secret_configured' => $secret !== null,
            'public_configured' => $public !== null,
            'secret_prefix_ok' => $secret !== null && str_starts_with($secret, $environment->secretPrefix()),
            'public_prefix_ok' => $public !== null && str_starts_with($public, $environment->publicPrefix()),
            'env_vars' => [
                'secret' => "PAYSTACK_{$code}_{$env}_SECRET_KEY",
                'public' => "PAYSTACK_{$code}_{$env}_PUBLIC_KEY",
            ],
        ];
    }

    /**
     * Ensures a test request can never be signed with a live key and vice
     * versa, which is the main guard against accidental production calls.
     */
    public function prefixMatches(string $key, string $auth, PaystackEnvironment $environment): bool
    {
        $prefix = $auth === 'public' ? $environment->publicPrefix() : $environment->secretPrefix();

        return str_starts_with($key, $prefix);
    }

    private function value(string $countryCode, PaystackEnvironment $environment, string $type): ?string
    {
        $value = config('paystack.credentials.'.strtoupper($countryCode).".{$environment->value}.{$type}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
