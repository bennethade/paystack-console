<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'symbol', 'subunit_name', 'subunit_factor', 'minimum_amount', 'is_enabled'])]
class PaystackCurrency extends Model
{
    protected function casts(): array
    {
        return [
            'subunit_factor' => 'integer',
            'minimum_amount' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(PaystackCountry::class, 'paystack_country_currency', 'currency_id', 'country_id');
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * Format an amount given in the subunit, e.g. 5000 NGN → "₦50.00".
     */
    public function formatSubunit(int $amount): string
    {
        $factor = max(1, $this->subunit_factor);

        return $this->symbol.number_format($amount / $factor, 2);
    }

    /**
     * Data that is safe to expose to the browser.
     */
    public function toPublicArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'subunit' => $this->subunit_name,
            'subunit_factor' => $this->subunit_factor,
            'minimum_amount' => $this->minimum_amount,
        ];
    }
}
