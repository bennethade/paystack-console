<?php

namespace App\Services\Paystack;

use RuntimeException;

/**
 * A request the explorer refuses to send (unavailable in the market, live
 * mode not confirmed, credentials missing...). Rendered as JSON with the
 * given HTTP status and a stable error code.
 */
class ExecutionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function unavailable(array $problems): self
    {
        return new self('This endpoint is not available for the selected country/currency.', 'endpoint_unavailable', 422, $problems);
    }

    public static function liveNotAllowed(string $reason): self
    {
        return new self($reason, 'live_mode_blocked', 403);
    }

    public static function missingCredentials(string $country, string $environment, string $auth, string $envVar): self
    {
        return new self(
            "No Paystack {$auth} key is configured for {$country} ({$environment}). Set {$envVar} (or the generic fallback) in .env.",
            'credentials_missing',
            422,
        );
    }

    public static function keyEnvironmentMismatch(string $environment): self
    {
        return new self(
            "The configured key does not look like a {$environment} key. The request was blocked to prevent sending to the wrong environment.",
            'credentials_mismatch',
            422,
        );
    }
}
