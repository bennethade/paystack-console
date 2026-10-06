<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * History entry for one executed request. Everything stored here has already
 * been passed through SensitiveDataMasker; the bearer token is never stored.
 */
#[Fillable([
    'uuid', 'user_id', 'endpoint_id', 'endpoint_key', 'environment', 'country_code', 'currency_code',
    'method', 'path', 'url', 'params', 'request_query', 'request_body', 'request_headers', 'status_code',
    'success', 'duration_ms', 'response_headers', 'response_body', 'response_truncated', 'error_message',
    'is_mock', 'ip_address',
])]
class ApiRequestLog extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ApiRequestLog $log) {
            $log->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'request_query' => 'array',
            'request_body' => 'array',
            'request_headers' => 'array',
            'response_headers' => 'array',
            'success' => 'boolean',
            'response_truncated' => 'boolean',
            'is_mock' => 'boolean',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function decodedResponse(): mixed
    {
        if ($this->response_body === null) {
            return null;
        }

        $decoded = json_decode($this->response_body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->response_body;
    }
}
