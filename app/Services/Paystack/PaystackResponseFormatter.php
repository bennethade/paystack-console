<?php

namespace App\Services\Paystack;

use App\Services\Paystack\Data\PaystackResponse;
use App\Support\SensitiveDataMasker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a PaystackResponse into the structure the response viewer renders.
 */
class PaystackResponseFormatter
{
    /**
     * Status meanings quoted from https://paystack.com/docs/api/errors/ and
     * https://paystack.com/docs/api/rate-limits/.
     */
    private const PAYSTACK_STATUS_NOTES = [
        200 => 'Request was successful and intended action was carried out. Paystack always sends a 200 if a charge or verify request was made; check the data object to know how the charge went.',
        201 => 'A resource has successfully been created.',
        400 => 'A validation or client side error occurred and the request wasn\'t fulfilled.',
        401 => 'The request wasn\'t authorized. This can be triggered by passing an invalid secret key in the authorization header or the lack of one. Check that the key configured for this country and environment is correct.',
        404 => 'Request couldn\'t be fulfilled as the request resource doesn\'t exist.',
        429 => 'Rate limit exceeded. Paystack limits requests per endpoint (test mode: 100 requests per 60 seconds). Read x-ratelimit-reset to know when you can retry.',
    ];

    private const RESPONSE_HEADER_ALLOWLIST = [
        'content-type', 'content-length', 'date', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset',
        'x-request-id', 'cache-control', 'etag', 'retry-after', 'access-control-allow-origin', 'vary', 'server',
    ];

    public function __construct(private readonly SensitiveDataMasker $masker) {}

    public function format(PaystackResponse $response): array
    {
        $json = $response->json();
        $status = $response->status;

        return [
            'status' => $status,
            'status_text' => $status ? (Response::$statusTexts[$status] ?? 'Unknown') : 'No response',
            'ok' => $response->successful() && (! is_array($json) || ($json['status'] ?? true) !== false),
            'duration_ms' => $response->durationMs,
            'size_bytes' => strlen($response->body),
            'is_mock' => $response->isMock,
            'transport_error' => $response->transportError,
            'headers' => $this->headers($response->headers),
            'is_json' => $json !== null,
            'body' => $json,
            'raw' => $json === null ? $this->masker->maskString(mb_substr($response->body, 0, 200_000)) : null,
            'paystack' => is_array($json) ? $this->paystackFields($json) : null,
            'hint' => $this->hint($status, $response->transportError),
            'pagination' => is_array($json) ? $this->pagination($json) : null,
            'links' => is_array($json) ? $this->links($json) : [],
            'reference' => is_array($json) ? $this->reference($json) : null,
        ];
    }

    private function headers(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            if (! in_array($lower, self::RESPONSE_HEADER_ALLOWLIST, true) && ! str_starts_with($lower, 'x-ratelimit')) {
                continue;
            }
            $out[$lower] = is_array($values) ? implode(', ', $values) : (string) $values;
        }
        ksort($out);

        return $out;
    }

    private function paystackFields(array $json): array
    {
        return array_filter([
            'status' => $json['status'] ?? null,
            'message' => $json['message'] ?? null,
            'type' => $json['type'] ?? null,
            'code' => $json['code'] ?? null,
            'next_step' => $json['meta']['nextStep'] ?? $json['meta']['next_step'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function hint(?int $status, ?string $transportError): ?array
    {
        if ($status === null) {
            return [
                'title' => 'No response received',
                'text' => $transportError ?? 'The request did not complete.',
                'source' => 'Explorer',
            ];
        }

        if (isset(self::PAYSTACK_STATUS_NOTES[$status])) {
            return [
                'title' => "{$status} ".(Response::$statusTexts[$status] ?? ''),
                'text' => self::PAYSTACK_STATUS_NOTES[$status],
                'source' => 'Paystack documentation',
            ];
        }

        if ($status >= 500) {
            return [
                'title' => "{$status} ".(Response::$statusTexts[$status] ?? 'Server error'),
                'text' => 'Request couldn\'t be fulfilled due to an error on Paystack\'s end.',
                'source' => 'Paystack documentation',
            ];
        }

        if ($status >= 400) {
            return [
                'title' => "{$status} ".(Response::$statusTexts[$status] ?? 'Client error'),
                'text' => 'Paystack does not document a specific meaning for this status. See the message in the response body.',
                'source' => 'HTTP specification',
            ];
        }

        return null;
    }

    /**
     * Pagination meta as documented at https://paystack.com/docs/api/pagination/.
     */
    private function pagination(array $json): ?array
    {
        $meta = $json['meta'] ?? null;
        if (! is_array($meta)) {
            return null;
        }

        if (array_key_exists('next', $meta) || array_key_exists('previous', $meta)) {
            return [
                'type' => 'cursor',
                'next' => $meta['next'] ?? null,
                'previous' => $meta['previous'] ?? null,
                'per_page' => isset($meta['perPage']) ? (int) $meta['perPage'] : null,
            ];
        }

        if (isset($meta['page']) || isset($meta['pageCount'])) {
            return [
                'type' => 'page',
                'page' => (int) ($meta['page'] ?? 1),
                'per_page' => (int) ($meta['perPage'] ?? 50),
                'page_count' => (int) ($meta['pageCount'] ?? 1),
                'total' => isset($meta['total']) ? (int) $meta['total'] : null,
                'total_volume' => $meta['total_volume'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Actionable URLs in the response (checkout, direct debit authorization,
     * subscription management). Only https URLs on Paystack's own domains are
     * offered as one-click actions.
     */
    private function links(array $json): array
    {
        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            return [];
        }

        $candidates = [
            'authorization_url' => 'Proceed to Payment',
            'redirect_url' => 'Open Authorization Page',
            'link' => 'Open Link',
            'url' => 'Open URL',
        ];

        $links = [];
        foreach ($candidates as $field => $label) {
            $url = $data[$field] ?? null;
            if (is_string($url) && $this->isTrustedUrl($url)) {
                $links[] = ['field' => $field, 'label' => $label, 'url' => $url, 'host' => parse_url($url, PHP_URL_HOST)];
            }
        }

        return $links;
    }

    public function isTrustedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        if ($parts['scheme'] === 'https' && ($host === 'paystack.com' || $host === 'paystack.co'
            || str_ends_with($host, '.paystack.com') || str_ends_with($host, '.paystack.co'))) {
            return true;
        }

        // Mock-mode checkout points back at this application.
        $ownHosts = array_filter([$appHost, strtolower((string) request()?->getHost())]);

        return in_array($host, $ownHosts, true) && in_array($parts['scheme'], ['http', 'https'], true);
    }

    private function reference(array $json): ?string
    {
        $data = $json['data'] ?? null;

        return is_array($data) && isset($data['reference']) && is_scalar($data['reference'])
            ? (string) $data['reference']
            : null;
    }
}
