<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code', 'name', 'flag', 'bank_country', 'default_currency_id', 'channels', 'features',
    'recipient_types', 'notes', 'is_enabled', 'sort_order',
])]
class PaystackCountry extends Model
{
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'features' => 'array',
            'recipient_types' => 'array',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(PaystackCurrency::class, 'paystack_country_currency', 'country_id', 'currency_id');
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(PaystackCurrency::class, 'default_currency_id');
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function supportsCurrency(string $code): bool
    {
        return $this->currencies->contains(fn (PaystackCurrency $c) => $c->code === $code && $c->is_enabled);
    }

    /**
     * Data that is safe to expose to the browser. Contains no credentials.
     */
    public function toPublicArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'flag' => $this->flag,
            'bank_country' => $this->bank_country,
            'default_currency' => $this->defaultCurrency?->code,
            'currencies' => $this->currencies->where('is_enabled', true)->pluck('code')->values()->all(),
            'channels' => array_values($this->channels ?? []),
            'features' => array_values($this->features ?? []),
            'recipient_types' => array_values($this->recipient_types ?? []),
        ];
    }
}
