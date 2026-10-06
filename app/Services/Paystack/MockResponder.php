<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;
use App\Services\Paystack\Data\PaystackRequest;
use App\Services\Paystack\Data\PaystackResponse;
use Illuminate\Support\Str;

/**
 * Demo / mock mode. Only used when explicitly enabled (PAYSTACK_MOCK_MODE or
 * the admin feature flag). Responses are unmistakably labelled and are NOT
 * Paystack data: they echo the validated request so the UI can be exercised
 * without credentials.
 */
class MockResponder
{
    public function respond(Endpoint $endpoint, PaystackRequest $request): PaystackResponse
    {
        $data = [
            'mock' => true,
            'endpoint' => $endpoint->key,
            'method' => $request->method,
            'path' => $request->path,
            'query' => $request->normalisedQuery(),
            'body' => $request->displayBody(),
        ];

        if ($endpoint->workflow === 'payment_redirect') {
            $reference = $request->body['reference'] ?? 'mock_'.Str::lower(Str::random(12));
            $data['reference'] = $reference;
            $data['authorization_url'] = route('explorer.callback', ['reference' => $reference, 'mock' => 1]);
        }

        $body = json_encode([
            'status' => true,
            'message' => '[MOCK MODE] Request validated but NOT sent to Paystack. This is not a Paystack response.',
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new PaystackResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json'], 'X-Mock-Mode' => ['true']],
            body: $body,
            durationMs: 0,
            isMock: true,
        );
    }
}
