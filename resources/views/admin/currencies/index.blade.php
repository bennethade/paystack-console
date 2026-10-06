@extends('layouts.app')
@section('title', 'Currencies')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-100">Currencies</h1>
            <p class="text-sm text-ink-400">Seeded from Paystack's supported-currency table. Minimum amounts are in the subunit.</p>
        </div>
        <a href="{{ route('admin.currencies.create') }}" class="btn btn-primary">Add currency</a>
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Subunit</th><th>Minimum</th><th>Countries</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($currencies as $currency)
                <tr>
                    <td class="font-mono">{{ $currency->code }}</td>
                    <td>{{ $currency->symbol }} {{ $currency->name }}</td>
                    <td class="text-xs">{{ $currency->subunit_name ?? '—' }} (×{{ $currency->subunit_factor }})</td>
                    <td class="font-mono text-xs">{{ $currency->minimum_amount }} = {{ $currency->formatSubunit($currency->minimum_amount) }}</td>
                    <td class="font-mono text-xs">{{ $currency->countries->pluck('code')->implode(', ') ?: '—' }}</td>
                    <td>{!! $currency->is_enabled ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-neutral">Disabled</span>' !!}</td>
                    <td class="text-right"><a href="{{ route('admin.currencies.edit', $currency) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
