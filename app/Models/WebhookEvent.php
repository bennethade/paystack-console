<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'uuid', 'environment', 'country_code', 'event', 'reference', 'payload', 'headers', 'signature',
    'signature_valid', 'verification_note', 'source_ip', 'ip_allowed',
])]
class WebhookEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event) {
            $event->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'signature_valid' => 'boolean',
            'ip_allowed' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function decodedPayload(): mixed
    {
        $decoded = json_decode($this->payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->payload;
    }
}
