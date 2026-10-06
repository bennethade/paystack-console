<?php

namespace App\Services\Paystack;

use App\Models\Endpoint;

/**
 * Decides whether an endpoint can be used in the selected market, based on
 * the country's documented features and the endpoint's currency limits.
 */
class EndpointAvailability
{
    /**
     * @return list<string> Human-readable reasons; empty when available.
     */
    public function problems(Endpoint $endpoint, ExplorerContext $context): array
    {
        $problems = [];
        $labels = config('paystack.feature_labels', []);

        foreach ($endpoint->required_features ?? [] as $feature) {
            if (! $context->country->hasFeature($feature)) {
                $label = $labels[$feature] ?? $feature;
                $problems[] = "{$label} is not available for {$context->country->name}.";
            }
        }

        $currencies = $endpoint->supported_currencies;
        if (! empty($currencies) && ! in_array($context->currency->code, $currencies, true)) {
            $problems[] = sprintf(
                'This endpoint only supports %s; the selected currency is %s.',
                implode(', ', $currencies),
                $context->currency->code
            );
        }

        return $problems;
    }

    public function isAvailable(Endpoint $endpoint, ExplorerContext $context): bool
    {
        return $this->problems($endpoint, $context) === [];
    }
}
