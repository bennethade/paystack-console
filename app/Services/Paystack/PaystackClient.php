<?php

namespace App\Services\Paystack;

use App\Services\Paystack\Data\PaystackRequest;
use App\Services\Paystack\Data\PaystackResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a built request to Paystack using Laravel's HTTP client.
 *
 * The key is attached here and nowhere else; it never leaves this method.
 * HTTP errors (4xx/5xx) are returned as responses, not thrown, so the
 * explorer can show Paystack's own error body.
 */
class PaystackClient
{
    public function send(PaystackRequest $request, string $key): PaystackResponse
    {
        $http = Http::withToken($key)
            ->acceptJson()
            ->withUserAgent(config('app.name').' API Explorer')
            ->timeout((int) config('paystack.timeout', 30))
            ->connectTimeout((int) config('paystack.connect_timeout', 10));

        $started = hrtime(true);

        try {
            $response = $this->dispatch($http, $request);
        } catch (ConnectionException $e) {
            return new PaystackResponse(
                status: null,
                headers: [],
                body: '',
                durationMs: $this->elapsed($started),
                transportError: 'Could not connect to Paystack: '.$this->scrub($e->getMessage(), $key),
            );
        } catch (Throwable $e) {
            return new PaystackResponse(
                status: null,
                headers: [],
                body: '',
                durationMs: $this->elapsed($started),
                transportError: 'Request failed: '.$this->scrub($e->getMessage(), $key),
            );
        }

        return new PaystackResponse(
            status: $response->status(),
            headers: $response->headers(),
            body: $response->body(),
            durationMs: $this->elapsed($started),
        );
    }

    private function dispatch(PendingRequest $http, PaystackRequest $request)
    {
        $url = rtrim($request->baseUrl, '/').$request->path;
        $method = strtolower($request->method);
        $query = $request->normalisedQuery();

        if ($request->method === 'GET') {
            return $http->get($url, $query);
        }

        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if ($request->bodyType === 'multipart' && $request->files !== []) {
            $http = $http->asMultipart();
            foreach ($request->files as $name => $file) {
                $http = $http->attach($name, fopen($file->getRealPath(), 'r'), $file->getClientOriginalName());
            }

            return $http->send(strtoupper($method), $url, ['multipart' => $this->multipartFields($request->body)]);
        }

        if (! $request->hasBody()) {
            return $http->send(strtoupper($method), $url);
        }

        return $http->asJson()->send(strtoupper($method), $url, ['json' => $request->body]);
    }

    /**
     * Non-file fields for a multipart body; nested values are JSON encoded.
     */
    private function multipartFields(array $body): array
    {
        $fields = [];
        foreach ($body as $name => $value) {
            $fields[] = [
                'name' => $name,
                'contents' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value),
            ];
        }

        return $fields;
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    /**
     * Guard against a key ever appearing in an exception message.
     */
    private function scrub(string $message, string $key): string
    {
        return str_replace($key, '********', $message);
    }
}
