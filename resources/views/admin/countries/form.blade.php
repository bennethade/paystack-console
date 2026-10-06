@extends('layouts.app')
@section('title', $country->exists ? 'Edit '.$country->name : 'Add country')

@section('content')
@include('admin.partials.nav')
@php $selectedCurrencies = old('currency_ids', $country->exists ? $country->currencies->pluck('id')->all() : []); @endphp
<form method="POST" action="{{ $country->exists ? route('admin.countries.update', $country) : route('admin.countries.store') }}" class="mx-auto max-w-3xl space-y-5">
    @csrf
    @if ($country->exists) @method('PUT') @endif
    <h1 class="text-xl font-semibold text-ink-100">{{ $country->exists ? 'Edit '.$country->name : 'Add country' }}</h1>

    <div class="card card-body grid gap-4 sm:grid-cols-4">
        <div><label for="code" class="label">ISO code</label><input id="code" name="code" maxlength="2" value="{{ old('code', $country->code) }}" class="input font-mono uppercase" required></div>
        <div class="sm:col-span-2"><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', $country->name) }}" class="input" required></div>
        <div><label for="flag" class="label">Flag emoji</label><input id="flag" name="flag" value="{{ old('flag', $country->flag) }}" class="input"></div>
        <div class="sm:col-span-2">
            <label for="bank_country" class="label">Bank list country value</label>
            <input id="bank_country" name="bank_country" value="{{ old('bank_country', $country->bank_country) }}" class="input font-mono" placeholder="e.g. nigeria">
            <p class="help">Value accepted by <code>GET /bank?country=</code>. Leave empty if Paystack does not accept this market.</p>
        </div>
        <div><label for="sort_order" class="label">Sort order</label><input id="sort_order" name="sort_order" type="number" value="{{ old('sort_order', $country->sort_order ?? 0) }}" class="input"></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $country->is_enabled))> Enabled</label></div>
    </div>

    <fieldset class="card card-body space-y-3">
        <legend class="text-sm font-semibold text-ink-100">Currencies</legend>
        <div class="flex flex-wrap gap-3">
            @foreach ($currencies as $currency)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="currency_ids[]" value="{{ $currency->id }}" @checked(in_array($currency->id, $selectedCurrencies))> <span class="font-mono">{{ $currency->code }}</span> <span class="text-ink-500">{{ $currency->name }}</span></label>
            @endforeach
        </div>
        <div class="w-48">
            <label for="default_currency_id" class="label">Default currency</label>
            <select id="default_currency_id" name="default_currency_id" class="input">
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected(old('default_currency_id', $country->default_currency_id) == $currency->id)>{{ $currency->code }}</option>
                @endforeach
            </select>
        </div>
    </fieldset>

    <fieldset class="card card-body space-y-3">
        <legend class="text-sm font-semibold text-ink-100">Payment channels</legend>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($channels as $key => $channel)
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="channels[]" value="{{ $key }}" class="mt-1" @checked(in_array($key, old('channels', $country->channels ?? [])))>
                    <span><span class="font-mono">{{ $key }}</span> · {{ $channel['label'] }}
                        <span class="block text-xs text-ink-500">{{ $channel['countries'] ? 'Documented for: '.implode(', ', $channel['countries']) : ($channel['note'] ?? '') }}</span></span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="card card-body space-y-3">
        <legend class="text-sm font-semibold text-ink-100">Features</legend>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($features as $key => $label)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, old('features', $country->features ?? [])))> {{ $label }}</label>
            @endforeach
        </div>
        <div>
            <label for="recipient_types" class="label">Transfer recipient types (comma separated)</label>
            <input id="recipient_types" name="recipient_types" value="{{ old('recipient_types', implode(', ', $country->recipient_types ?? [])) }}" class="input font-mono">
        </div>
        <div>
            <label for="notes" class="label">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $country->notes) }}</textarea>
        </div>
    </fieldset>

    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('admin.countries.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </div>
</form>
@if ($country->exists)
    <form method="POST" action="{{ route('admin.countries.destroy', $country) }}" class="mx-auto mt-4 max-w-3xl text-right" x-data @submit="if (! confirm({{ \Illuminate\Support\Js::from('Delete '.$country->name.'?') }})) $event.preventDefault()">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm">Delete country</button>
    </form>
@endif
@endsection
