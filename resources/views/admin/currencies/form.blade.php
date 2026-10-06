@extends('layouts.app')
@section('title', $currency->exists ? 'Edit '.$currency->code : 'Add currency')

@section('content')
@include('admin.partials.nav')
<form method="POST" action="{{ $currency->exists ? route('admin.currencies.update', $currency) : route('admin.currencies.store') }}" class="mx-auto max-w-2xl space-y-5">
    @csrf
    @if ($currency->exists) @method('PUT') @endif
    <h1 class="text-xl font-semibold text-ink-100">{{ $currency->exists ? 'Edit '.$currency->code : 'Add currency' }}</h1>
    <div class="card card-body grid gap-4 sm:grid-cols-3">
        <div><label for="code" class="label">ISO 4217 code</label><input id="code" name="code" maxlength="3" value="{{ old('code', $currency->code) }}" class="input font-mono uppercase" required></div>
        <div class="sm:col-span-2"><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', $currency->name) }}" class="input" required></div>
        <div><label for="symbol" class="label">Symbol</label><input id="symbol" name="symbol" value="{{ old('symbol', $currency->symbol) }}" class="input" required></div>
        <div><label for="subunit_name" class="label">Subunit name</label><input id="subunit_name" name="subunit_name" value="{{ old('subunit_name', $currency->subunit_name) }}" class="input"></div>
        <div><label for="subunit_factor" class="label">Subunit factor</label><input id="subunit_factor" name="subunit_factor" type="number" min="1" value="{{ old('subunit_factor', $currency->subunit_factor) }}" class="input" required></div>
        <div><label for="minimum_amount" class="label">Minimum (subunit)</label><input id="minimum_amount" name="minimum_amount" type="number" min="0" value="{{ old('minimum_amount', $currency->minimum_amount ?? 0) }}" class="input" required></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $currency->is_enabled))> Enabled</label></div>
    </div>
    <div class="flex gap-2"><button class="btn btn-primary">Save</button><a href="{{ route('admin.currencies.index') }}" class="btn btn-ghost">Cancel</a></div>
</form>
@if ($currency->exists)
    <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}" class="mx-auto mt-4 max-w-2xl text-right" x-data @submit="if (! confirm({{ \Illuminate\Support\Js::from('Delete '.$currency->code.'?') }})) $event.preventDefault()">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm">Delete currency</button>
    </form>
@endif
@endsection
