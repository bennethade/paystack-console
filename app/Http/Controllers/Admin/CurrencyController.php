<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaystackCurrency;
use App\Services\Paystack\PaystackCountryResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly PaystackCountryResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.currencies.index', [
            'currencies' => PaystackCurrency::withCount('countries')->with('countries')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.currencies.form', ['currency' => new PaystackCurrency(['subunit_factor' => 100, 'is_enabled' => false])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currency = new PaystackCurrency;
        $this->save($request, $currency);
        $this->audit->record('admin.currency_created', $currency, ['code' => $currency->code]);

        return redirect()->route('admin.currencies.index')->with('status', "{$currency->code} created.");
    }

    public function edit(PaystackCurrency $currency): View
    {
        return view('admin.currencies.form', ['currency' => $currency]);
    }

    public function update(Request $request, PaystackCurrency $currency): RedirectResponse
    {
        $this->save($request, $currency);
        $this->audit->record('admin.currency_updated', $currency, ['code' => $currency->code, 'enabled' => $currency->is_enabled]);

        return redirect()->route('admin.currencies.index')->with('status', "{$currency->code} updated.");
    }

    public function destroy(PaystackCurrency $currency): RedirectResponse
    {
        $this->audit->record('admin.currency_deleted', $currency, ['code' => $currency->code]);
        $currency->delete();
        $this->resolver->flush();

        return redirect()->route('admin.currencies.index')->with('status', 'Currency deleted.');
    }

    private function save(Request $request, PaystackCurrency $currency): void
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('paystack_currencies', 'code')->ignore($currency->id)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:8'],
            'subunit_name' => ['nullable', 'string', 'max:32'],
            'subunit_factor' => ['required', 'integer', 'min:1', 'max:10000'],
            'minimum_amount' => ['required', 'integer', 'min:0'],
        ]);

        $currency->fill($data + ['is_enabled' => $request->boolean('is_enabled')]);
        $currency->code = strtoupper($data['code']);
        $currency->save();

        $this->resolver->flush();
    }
}
