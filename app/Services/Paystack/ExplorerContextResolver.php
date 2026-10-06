<?php

namespace App\Services\Paystack;

use App\Enums\PaystackEnvironment;
use App\Models\User;
use App\Models\UserApiPreference;
use App\Support\Settings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Determines the active country / currency / environment for the current
 * user. Selection is kept in the session and persisted to the user's
 * preferences so it survives logins.
 */
class ExplorerContextResolver
{
    private const SESSION_KEY = 'explorer.context';

    public function __construct(
        private readonly PaystackCountryResolver $countries,
        private readonly PaystackCurrencyResolver $currencies,
        private readonly Settings $settings,
    ) {}

    public function current(Session $session, ?User $user): ExplorerContext
    {
        $stored = $session->get(self::SESSION_KEY);

        if (! is_array($stored) && $user) {
            $preference = $user->preference;
            $stored = $preference ? [
                'country' => $preference->country_code,
                'currency' => $preference->currency_code,
                'environment' => $preference->environment,
            ] : null;
        }

        $stored = is_array($stored) ? $stored : [];

        $country = $this->countries->resolve($stored['country'] ?? null);
        if ($country === null) {
            throw new RuntimeException('No Paystack countries are enabled. Run the database seeder or enable a country in the admin area.');
        }

        $currency = $this->currencies->resolve($country, $stored['currency'] ?? null);
        if ($currency === null) {
            throw new RuntimeException("No enabled currency is linked to {$country->name}.");
        }

        $environment = PaystackEnvironment::tryFrom($stored['environment'] ?? '') ?? PaystackEnvironment::Test;
        if ($environment->isLive() && ! $this->canUseLive($user)) {
            $environment = PaystackEnvironment::Test;
        }

        return new ExplorerContext($country, $currency, $environment);
    }

    /**
     * Validate and store a new selection.
     *
     * @throws ValidationException
     */
    public function update(Session $session, ?User $user, string $countryCode, ?string $currencyCode, string $environment): ExplorerContext
    {
        $country = $this->countries->find($countryCode);
        if ($country === null) {
            throw ValidationException::withMessages(['country' => 'The selected country is not supported.']);
        }

        if ($currencyCode !== null && $currencyCode !== '' && ! $this->currencies->isValidFor($country, $currencyCode)) {
            throw ValidationException::withMessages(['currency' => "Currency {$currencyCode} is invalid for the selected country ({$country->name})."]);
        }

        $env = PaystackEnvironment::tryFrom($environment);
        if ($env === null) {
            throw ValidationException::withMessages(['environment' => 'The selected environment is invalid.']);
        }
        if ($env->isLive() && ! $this->canUseLive($user)) {
            throw ValidationException::withMessages(['environment' => 'Live mode is not available to your account.']);
        }

        $currency = $this->currencies->resolve($country, $currencyCode);

        $context = new ExplorerContext($country, $currency, $env);
        $session->put(self::SESSION_KEY, $context->toPublicArray());

        if ($user) {
            UserApiPreference::updateOrCreate(['user_id' => $user->id], [
                'country_code' => $country->code,
                'currency_code' => $currency->code,
                'environment' => $env->value,
            ]);
        }

        return $context;
    }

    public function canUseLive(?User $user): bool
    {
        return $user !== null
            && $this->settings->liveModeAvailable()
            && $user->hasPermission('explorer.execute-live');
    }
}
