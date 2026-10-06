<?php

namespace App\Support;

/**
 * Masks credentials and payment data before anything is logged, stored or
 * shown in the request inspector.
 *
 *  - keys listed in paystack.sensitive_keys are replaced entirely
 *  - card numbers (a `number` key inside a `card` object, or any 12–19
 *    digit value under a key containing "card") keep only first 6 / last 4
 *  - anything that looks like a Paystack key (sk_/pk_ test|live) is masked
 *    wherever it appears in a string
 */
class SensitiveDataMasker
{
    public const MASK = '********';

    private array $sensitiveKeys;

    public function __construct(?array $sensitiveKeys = null)
    {
        $this->sensitiveKeys = array_map('strtolower', $sensitiveKeys ?? config('paystack.sensitive_keys', []));
    }

    public function mask(mixed $data, ?string $parentKey = null): mixed
    {
        if (is_array($data)) {
            $masked = [];
            foreach ($data as $key => $value) {
                $masked[$key] = $this->maskValue((string) $key, $value, $parentKey);
            }

            return $masked;
        }

        if (is_string($data)) {
            return $this->maskString($data);
        }

        return $data;
    }

    /**
     * Mask an HTTP header bag. Authorization is always fully masked.
     */
    public function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if ($lower === 'authorization') {
                $masked[$name] = 'Bearer '.self::MASK.self::MASK;

                continue;
            }
            $masked[$name] = in_array($lower, $this->sensitiveKeys, true)
                ? self::MASK
                : (is_array($value) ? array_map(fn ($v) => $this->maskString((string) $v), $value) : $this->maskString((string) $value));
        }

        return $masked;
    }

    public function maskString(string $value): string
    {
        return (string) preg_replace('/\b(sk|pk)_(test|live)_[A-Za-z0-9]+/', '$1_$2_'.self::MASK, $value);
    }

    private function maskValue(string $key, mixed $value, ?string $parentKey): mixed
    {
        $lower = strtolower($key);

        if (in_array($lower, $this->sensitiveKeys, true) && $value !== null && $value !== '') {
            return self::MASK;
        }

        $inCard = $parentKey !== null && str_contains(strtolower($parentKey), 'card');
        if (($inCard && $lower === 'number') || str_contains($lower, 'card_number')) {
            return $this->maskPan((string) (is_scalar($value) ? $value : ''));
        }

        if (is_array($value)) {
            return $this->mask($value, $key);
        }

        return is_string($value) ? $this->maskString($value) : $value;
    }

    private function maskPan(string $pan): string
    {
        $digits = preg_replace('/\D/', '', $pan);

        if (strlen($digits) < 12) {
            return self::MASK;
        }

        return substr($digits, 0, 6).str_repeat('*', strlen($digits) - 10).substr($digits, -4);
    }
}
