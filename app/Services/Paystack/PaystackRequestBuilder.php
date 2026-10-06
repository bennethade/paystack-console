<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;
use App\Services\Paystack\Data\PaystackRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Places validated values into the path, query string or body according to
 * each parameter's `in`, and picks the body encoding.
 */
class PaystackRequestBuilder
{
    /**
     * @param  array<string, mixed>  $values  Output of EndpointValidator
     */
    public function build(Endpoint $endpoint, array $values): PaystackRequest
    {
        $path = $endpoint->path;
        $query = [];
        $body = [];
        $files = [];

        foreach ($endpoint->parameterDefinitions() as $parameter) {
            if (! array_key_exists($parameter->name, $values)) {
                continue;
            }

            $value = $values[$parameter->name];

            switch ($parameter->in) {
                case 'path':
                    // rawurlencode prevents path traversal / injection via path params.
                    $path = str_replace('{'.$parameter->name.'}', rawurlencode((string) $value), $path);
                    break;
                case 'query':
                    $query[$parameter->name] = $value;
                    break;
                default:
                    if ($parameter->root) {
                        // The value is the whole body, e.g. the JSON array for POST /bulkcharge.
                        $body = array_values((array) $value);
                    } elseif ($value instanceof UploadedFile) {
                        $files[$parameter->name] = $value;
                    } else {
                        $body[$parameter->name] = $value;
                    }
            }
        }

        if (preg_match('/\{([^}]+)\}/', $path, $missing)) {
            throw new InvalidArgumentException("Missing path parameter: {$missing[1]}");
        }

        $bodyType = match (true) {
            $endpoint->method === 'GET' => 'none',
            $files !== [] => 'multipart',
            default => $endpoint->body_type === 'none' && $body !== [] ? 'json' : $endpoint->body_type,
        };

        return new PaystackRequest(
            method: $endpoint->method,
            baseUrl: config('paystack.base_url'),
            path: $path,
            query: $query,
            body: $body,
            files: $files,
            bodyType: $bodyType,
            auth: $endpoint->auth,
        );
    }
}
