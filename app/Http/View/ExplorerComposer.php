<?php

namespace App\Http\View;

use App\Models\PaystackCountry;
use App\Services\Paystack\EndpointRegistry;
use App\Services\Paystack\ExplorerContextResolver;
use App\Services\Paystack\PaystackCountryResolver;
use App\Services\Paystack\PaystackCredentials;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Shares the explorer chrome (context switchers, sidebar, favourites,
 * credential status) with the main layout.
 *
 * Only public data is shared. Credential status is a set of booleans and
 * env-var NAMES; key values never reach a view.
 */
class ExplorerComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly ExplorerContextResolver $contexts,
        private readonly PaystackCountryResolver $countries,
        private readonly EndpointRegistry $registry,
        private readonly PaystackCredentials $credentials,
        private readonly Settings $settings,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        if ($user === null) {
            return;
        }

        try {
            $context = $this->contexts->current($this->request->session(), $user);
        } catch (Throwable $e) {
            $view->with('explorerSetupError', $e->getMessage());
            $view->with('navigation', collect());

            return;
        }

        $currencies = [];
        foreach ($this->countries->enabled() as $country) {
            foreach ($country->currencies as $currency) {
                $currencies[$currency->code] = $currency->toPublicArray();
            }
        }

        $view->with([
            'explorerContext' => $context,
            'explorerCountries' => $this->countries->enabled()->map(fn (PaystackCountry $c) => $c->toPublicArray())->values()->all(),
            'explorerCurrencies' => $currencies,
            'navigation' => $this->registry->navigation(),
            'favoriteKeys' => $user->favoriteEndpoints()->pluck('key')->all(),
            'credentialStatus' => $this->credentials->status($context->country->code, $context->environment),
            'liveAvailable' => $this->contexts->canUseLive($user),
            'mockMode' => $this->settings->mockMode(),
            'announcement' => $this->settings->get('announcement'),
        ]);
    }
}
