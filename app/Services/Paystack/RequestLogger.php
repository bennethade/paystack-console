<?php

namespace App\Services\Paystack;

use App\Models\ApiRequestLog;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\Paystack\Data\PaystackRequest;
use App\Services\Paystack\Data\PaystackResponse;
use App\Support\SensitiveDataMasker;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Writes the request history row and a structured line to the `paystack`
 * log channel. All payloads are masked first; the Authorization header is
 * never written anywhere.
 */
class RequestLogger
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
        private readonly Settings $settings,
    ) {}

    public function log(
        Endpoint $endpoint,
        ExplorerContext $context,
        ?User $user,
        array $params,
        PaystackRequest $request,
        PaystackResponse $response,
        ?string $ip,
    ): ?ApiRequestLog {
        Log::channel('paystack')->info('Paystack API request', [
            'user_id' => $user?->id,
            'endpoint' => $endpoint->key,
            'method' => $request->method,
            'path' => $request->path,
            'country' => $context->country->code,
            'currency' => $context->currency->code,
            'environment' => $context->environment->value,
            'status' => $response->status,
            'duration_ms' => $response->durationMs,
            'mock' => $response->isMock,
            'transport_error' => $response->transportError,
        ]);

        if (! $this->settings->bool('history_enabled')) {
            return null;
        }

        [$responseBody, $truncated] = $this->responseForStorage($response);

        return ApiRequestLog::create([
            'user_id' => $user?->id,
            'endpoint_id' => $endpoint->id,
            'endpoint_key' => $endpoint->key,
            'environment' => $context->environment->value,
            'country_code' => $context->country->code,
            'currency_code' => $context->currency->code,
            'method' => $request->method,
            'path' => $request->path,
            'url' => $this->masker->maskString($request->url()),
            'params' => $this->masker->mask($this->paramsForStorage($params)),
            'request_query' => $this->masker->mask($request->normalisedQuery()) ?: null,
            'request_body' => $request->hasBody() ? $this->masker->mask($request->displayBody()) : null,
            'request_headers' => $request->displayHeaders(),
            'status_code' => $response->status,
            'success' => $response->successful(),
            'duration_ms' => $response->durationMs,
            'response_headers' => $this->masker->maskHeaders($response->headers),
            'response_body' => $responseBody,
            'response_truncated' => $truncated,
            'error_message' => $response->transportError,
            'is_mock' => $response->isMock,
            'ip_address' => $ip,
        ]);
    }

    /**
     * Files cannot be replayed, so only their names are kept.
     */
    private function paramsForStorage(array $params): array
    {
        return array_map(
            fn ($v) => $v instanceof UploadedFile ? null : $v,
            $params
        );
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function responseForStorage(PaystackResponse $response): array
    {
        if (! config('paystack.history.store_responses', true)) {
            return [null, false];
        }

        $json = $response->json();
        $body = $json !== null
            ? json_encode($this->masker->mask($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $this->masker->maskString($response->body);

        $limit = (int) config('paystack.history.max_response_kb', 256) * 1024;
        if (strlen($body) > $limit) {
            return [mb_strcut($body, 0, $limit), true];
        }

        return [$body, false];
    }
}
