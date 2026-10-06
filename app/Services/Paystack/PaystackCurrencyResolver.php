<?php

namespace App\Services\Paystack;

use App\Models\PaystackCountry;
use App\Models\PaystackCurrency;
use Illuminate\Support\Collection;

/**
 * Resolves which currencies are valid for a market. A currency is only valid
 * for a country if it is linked to that country AND enabled.
 */
class PaystackCurrencyResolver
{
    /**
     * @return Collection<int, PaystackCurrency>
     */
    public function forCountry(PaystackCountry $country): Collection
    {
        return $country->currencies->where('is_enabled', true)->sortBy('code')->values();
    }

    public function isValidFor(PaystackCountry $country, ?string $code): bool
    {
        return $code !== null && $this->forCountry($country)->contains('code', strtoupper($code));
    }

    /**
     * The requested currency if valid for the country, otherwise the
     * country's default (or first) currency.
     */
    public function resolve(PaystackCountry $country, ?string $code): ?PaystackCurrency
    {
        $currencies = $this->forCountry($country);

        if ($code !== null && ($match = $currencies->firstWhere('code', strtoupper($code)))) {
            return $match;
        }

        $default = $country->defaultCurrency;

        return $default && $default->is_enabled && $currencies->contains('code', $default->code)
            ? $default
            : $currencies->first();
    }
}
