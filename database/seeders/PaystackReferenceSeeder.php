<?php

namespace Database\Seeders;

use App\Models\PaystackCountry;
use App\Models\PaystackCurrency;
use App\Services\Paystack\PaystackCountryResolver;
use Illuminate\Database\Seeder;

/**
 * Seeds currencies and countries from config/paystack.php (which documents
 * the source of every value). Idempotent: re-running updates existing rows.
 */
class PaystackReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $currencyIds = [];
        foreach (config('paystack.currencies') as $code => $currency) {
            $model = PaystackCurrency::updateOrCreate(['code' => $code], [
                'name' => $currency['name'],
                'symbol' => $currency['symbol'],
                'subunit_name' => $currency['subunit'],
                'subunit_factor' => $currency['subunit_factor'],
                'minimum_amount' => $currency['minimum_amount'],
            ]);
            if ($model->wasRecentlyCreated) {
                $model->update(['is_enabled' => true]);
            }
            $currencyIds[$code] = $model->id;
        }

        $sort = 0;
        foreach (config('paystack.countries') as $code => $country) {
            $model = PaystackCountry::updateOrCreate(['code' => $code], [
                'name' => $country['name'],
                'flag' => $country['flag'],
                'bank_country' => $country['bank_country'],
                'default_currency_id' => $currencyIds[$country['default_currency']],
                'channels' => $this->channelsFor($code),
                'features' => $country['features'],
                'recipient_types' => $country['recipient_types'],
                'sort_order' => $sort += 10,
            ]);
            if ($model->wasRecentlyCreated) {
                $model->update(['is_enabled' => true]);
            }

            $model->currencies()->sync(array_map(fn ($c) => $currencyIds[$c], $country['currencies']));
        }

        app(PaystackCountryResolver::class)->flush();
    }

    /**
     * Channels documented for the market, plus channels with no documented
     * market restriction.
     */
    private function channelsFor(string $code): array
    {
        $channels = [];
        foreach (config('paystack.channels') as $channel => $meta) {
            if ($meta['countries'] === null || in_array($code, $meta['countries'], true)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }
}
