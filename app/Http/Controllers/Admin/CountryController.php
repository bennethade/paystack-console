<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaystackEnvironment;
use App\Http\Controllers\Controller;
use App\Models\PaystackCountry;
use App\Models\PaystackCurrency;
use App\Services\Paystack\PaystackCountryResolver;
use App\Services\Paystack\PaystackCredentials;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Markets. Credentials are NOT editable here: they live in .env /
 * config/paystack.php. This screen only shows whether they are configured.
 */
class CountryController extends Controller
{
    public function __construct(
        private readonly PaystackCountryResolver $resolver,
        private readonly PaystackCredentials $credentials,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $countries = PaystackCountry::with(['currencies', 'defaultCurrency'])->ordered()->get();

        $status = [];
        foreach ($countries as $country) {
            foreach (PaystackEnvironment::cases() as $env) {
                $status[$country->code][$env->value] = $this->credentials->status($country->code, $env);
            }
        }

        return view('admin.countries.index', ['countries' => $countries, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.countries.form', $this->formData(new PaystackCountry(['is_enabled' => false])));
    }

    public function store(Request $request): RedirectResponse
    {
        $country = new PaystackCountry;
        $this->save($request, $country);
        $this->audit->record('admin.country_created', $country, ['code' => $country->code]);

        return redirect()->route('admin.countries.index')->with('status', "{$country->name} created.");
    }

    public function edit(PaystackCountry $country): View
    {
        return view('admin.countries.form', $this->formData($country->load('currencies')));
    }

    public function update(Request $request, PaystackCountry $country): RedirectResponse
    {
        $this->save($request, $country);
        $this->audit->record('admin.country_updated', $country, ['code' => $country->code, 'enabled' => $country->is_enabled]);

        return redirect()->route('admin.countries.index')->with('status', "{$country->name} updated.");
    }

    public function destroy(PaystackCountry $country): RedirectResponse
    {
        $this->audit->record('admin.country_deleted', $country, ['code' => $country->code]);
        $country->delete();
        $this->resolver->flush();

        return redirect()->route('admin.countries.index')->with('status', 'Country deleted.');
    }

    private function save(Request $request, PaystackCountry $country): void
    {
        $channels = array_keys(config('paystack.channels'));
        $features = array_keys(config('paystack.feature_labels'));

        $data = $request->validate([
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('paystack_countries', 'code')->ignore($country->id)],
            'name' => ['required', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:16'],
            'bank_country' => ['nullable', 'string', 'max:50'],
            'currency_ids' => ['required', 'array', 'min:1'],
            'currency_ids.*' => ['integer', 'exists:paystack_currencies,id'],
            'default_currency_id' => ['required', 'integer', 'in:'.implode(',', (array) $request->input('currency_ids', [0]))],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in($channels)],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in($features)],
            'recipient_types' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $country->fill([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'flag' => $data['flag'] ?? null,
            'bank_country' => $data['bank_country'] ?? null,
            'default_currency_id' => $data['default_currency_id'],
            'channels' => array_values($data['channels'] ?? []),
            'features' => array_values($data['features'] ?? []),
            'recipient_types' => array_values(array_filter(array_map('trim', explode(',', $data['recipient_types'] ?? '')))),
            'notes' => $data['notes'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_enabled' => $request->boolean('is_enabled'),
        ])->save();

        $country->currencies()->sync($data['currency_ids']);
        $this->resolver->flush();
    }

    private function formData(PaystackCountry $country): array
    {
        return [
            'country' => $country,
            'currencies' => PaystackCurrency::orderBy('code')->get(),
            'channels' => config('paystack.channels'),
            'features' => config('paystack.feature_labels'),
        ];
    }
}
