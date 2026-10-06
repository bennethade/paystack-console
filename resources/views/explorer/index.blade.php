@extends('layouts.app')
@section('title', 'Dashboard')

@php
    $byKey = collect($navigation)->flatMap(fn ($c) => $c['endpoints'])->keyBy('key');
    $quick = collect(['transaction.initialize', 'transaction.verify', 'bank.list', 'customer.create', 'transferrecipient.create', 'transfer.initiate', 'plan.create', 'refund.create'])
        ->map(fn ($k) => $byKey->get($k))->filter();
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink-100">Paystack API Explorer</h1>
            <p class="mt-1 text-sm text-ink-400">
                {{ $endpointCount }} endpoints in {{ $categoryCount }} categories, generated from Paystack's official OpenAPI specification and API reference.
            </p>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-ink-700 bg-ink-900 px-3 py-2 text-sm">
            <span class="text-lg" aria-hidden="true">{{ $context->country->flag }}</span>
            <span class="text-ink-100">{{ $context->country->name }}</span>
            <span class="font-mono text-ink-300">{{ $context->currency->code }}</span>
            <x-env-badge :environment="$context->environment->value" />
        </div>
    </div>

    <section aria-labelledby="quick-heading">
        <h2 id="quick-heading" class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-400">Common operations</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($quick as $item)
                <a href="{{ $item['url'] }}" class="card block p-4 transition hover:border-brand-500/60">
                    <div class="flex items-center gap-2"><x-method :method="$item['method']" /><span class="text-xs text-ink-500">{{ $item['category'] }}</span></div>
                    <div class="mt-2 font-medium text-ink-100">{{ $item['name'] }}</div>
                    <div class="mt-1 truncate font-mono text-xs text-ink-400">{{ $item['path'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card xl:col-span-2" aria-labelledby="recent-heading">
            <div class="card-header">
                <h2 id="recent-heading" class="font-semibold text-ink-100">Your recent requests</h2>
                <a href="{{ route('explorer.history.index') }}" class="text-xs text-brand-400 hover:underline">All history →</a>
            </div>
            @if ($recent->isEmpty())
                <p class="card-body text-sm text-ink-400">No requests yet. Pick an endpoint from the sidebar or press <span class="kbd">/</span> to search.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Status</th><th>Endpoint</th><th class="hidden sm:table-cell">Market</th><th class="text-right">Time</th></tr></thead>
                        <tbody>
                        @foreach ($recent as $log)
                            <tr>
                                <td><x-status-badge :code="$log->status_code" :success="$log->success" /></td>
                                <td>
                                    <a href="{{ route('explorer.history.show', $log) }}" class="flex items-center gap-2 hover:underline">
                                        <x-method :method="$log->method" /><span class="truncate font-mono text-xs">{{ $log->path }}</span>
                                    </a>
                                </td>
                                <td class="hidden sm:table-cell"><span class="font-mono text-xs">{{ $log->country_code }}/{{ $log->currency_code }}</span> <x-env-badge :environment="$log->environment" /></td>
                                <td class="text-right text-xs text-ink-400">{{ $log->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="fav-heading">
            <div class="card-header"><h2 id="fav-heading" class="font-semibold text-ink-100">★ Favorites</h2></div>
            <div class="card-body">
                @forelse ($favorites as $endpoint)
                    <a href="{{ $endpoint->url() }}" class="nav-link"><x-method :method="$endpoint->method" /><span class="truncate">{{ $endpoint->name }}</span></a>
                @empty
                    <p class="text-sm text-ink-400">Star an endpoint to pin it here and in the sidebar.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card" aria-labelledby="cred-heading">
        <div class="card-header">
            <h2 id="cred-heading" class="font-semibold text-ink-100">Credential configuration</h2>
            <span class="text-xs text-ink-500">Keys are read from .env on the server and never displayed.</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Market</th><th>Currencies</th><th>Test secret key</th><th>Live secret key</th><th class="hidden md:table-cell">Env var</th></tr></thead>
                <tbody>
                @foreach ($countries as $country)
                    <tr>
                        <td>{{ $country->flag }} {{ $country->name }}</td>
                        <td class="font-mono text-xs">{{ $country->currencies->pluck('code')->implode(', ') }}</td>
                        @foreach (['test', 'live'] as $env)
                            @php $s = $credentialMatrix[$country->code][$env]; @endphp
                            <td>
                                @if ($s['secret_configured'] && $s['secret_prefix_ok'])
                                    <span class="badge badge-success">Configured</span>
                                @elseif ($s['secret_configured'])
                                    <span class="badge badge-danger">Wrong prefix</span>
                                @else
                                    <span class="badge badge-neutral">Not set</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="hidden font-mono text-[11px] text-ink-500 md:table-cell">{{ $credentialMatrix[$country->code]['test']['env_vars']['secret'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
