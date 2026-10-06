<?php

namespace App\Services\Paystack;

use App\Models\PaystackCountry;
use Illuminate\Support\Collection;

/**
 * Resolves enabled Paystack markets from the database (seeded from
 * config/paystack.php and editable by admins).
 *
 * Memoized per request (a single small eager-loaded query) rather than put
 * in the cache store, since Eloquent models are not cacheable under
 * Laravel's restricted cache unserialization.
 */
class PaystackCountryResolver
{
    private ?Collection $countries = null;

    /**
     * @return Collection<int, PaystackCountry>
     */
    public function enabled(): Collection
    {
        return $this->countries ??= PaystackCountry::query()
            ->enabled()
            ->ordered()
            ->with(['currencies', 'defaultCurrency'])
            ->get();
    }

    public function find(?string $code): ?PaystackCountry
    {
        if ($code === null) {
            return null;
        }

        return $this->enabled()->first(fn (PaystackCountry $c) => $c->code === strtoupper($code));
    }

    public function default(): ?PaystackCountry
    {
        return $this->find(config('paystack.default_country')) ?? $this->enabled()->first();
    }

    public function resolve(?string $code): ?PaystackCountry
    {
        return $this->find($code) ?? $this->default();
    }

    public function flush(): void
    {
        $this->countries = null;
    }
}
