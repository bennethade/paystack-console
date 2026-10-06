<?php

namespace App\Services\Paystack\Data;

/**
 * Normalised outcome of a request. status is null when no HTTP response was
 * received (connection failure / timeout).
 */
final class PaystackResponse
{
    public function __construct(
        public readonly ?int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly int $durationMs,
        public readonly ?string $transportError = null,
        public readonly bool $isMock = false,
    ) {}

    public function json(): mixed
    {
        if ($this->body === '') {
            return null;
        }

        $decoded = json_decode($this->body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function isJson(): bool
    {
        return $this->json() !== null;
    }

    public function successful(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300;
    }
}
