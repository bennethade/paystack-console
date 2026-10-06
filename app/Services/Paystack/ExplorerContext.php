<?php

namespace App\Services\Paystack;

use App\Enums\PaystackEnvironment;
use App\Models\PaystackCountry;
use App\Models\PaystackCurrency;

/**
 * The market + environment a request is executed against.
 */
final class ExplorerContext
{
    public function __construct(
        public readonly PaystackCountry $country,
        public readonly PaystackCurrency $currency,
        public readonly PaystackEnvironment $environment,
    ) {}

    public function isLive(): bool
    {
        return $this->environment->isLive();
    }

    /**
     * Safe to expose to the browser: no credentials.
     */
    public function toPublicArray(): array
    {
        return [
            'country' => $this->country->code,
            'country_name' => $this->country->name,
            'currency' => $this->currency->code,
            'environment' => $this->environment->value,
        ];
    }
}
