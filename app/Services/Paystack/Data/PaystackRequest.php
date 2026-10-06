<?php

namespace App\Services\Paystack\Data;

use Illuminate\Http\UploadedFile;

/**
 * A fully built request, ready to send. Contains no credentials: the client
 * attaches the Authorization header at send time.
 */
final class PaystackRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, UploadedFile>  $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $baseUrl,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $files = [],
        public readonly string $bodyType = 'json',
        public readonly string $auth = 'secret',
    ) {}

    public function url(): string
    {
        $url = rtrim($this->baseUrl, '/').$this->path;

        if ($this->query !== []) {
            $url .= '?'.http_build_query($this->normalisedQuery(), '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * Query values as Paystack expects them (booleans as true/false).
     */
    public function normalisedQuery(): array
    {
        return array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $this->query);
    }

    public function hasBody(): bool
    {
        return $this->method !== 'GET' && $this->bodyType !== 'none' && ($this->body !== [] || $this->files !== []);
    }

    /**
     * Headers as sent, with the token replaced by a placeholder.
     */
    public function displayHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.str_repeat('*', 16),
            'Accept' => 'application/json',
        ];

        if ($this->hasBody()) {
            $headers['Content-Type'] = $this->files !== [] ? 'multipart/form-data' : 'application/json';
        }

        return $headers;
    }

    /**
     * Body for display / logging: files are described, never inlined.
     */
    public function displayBody(): array
    {
        $body = $this->body;
        foreach ($this->files as $name => $file) {
            $body[$name] = sprintf('@%s (%s, %d bytes)', $file->getClientOriginalName(), $file->getClientMimeType(), $file->getSize());
        }

        return $body;
    }
}
