@extends('layouts.app')
@section('title', 'Countries')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-100">Countries</h1>
            <p class="text-sm text-ink-400">Markets are seeded from <code class="font-mono">config/paystack.php</code>. Only add a market Paystack documents. Keys are configured in <code class="font-mono">.env</code>, never here.</p>
        </div>
        <a href="{{ route('admin.countries.create') }}" class="btn btn-primary">Add country</a>
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Country</th><th>Currencies</th><th class="hidden lg:table-cell">Channels</th><th class="hidden xl:table-cell">Features</th><th>Test key</th><th>Live key</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($countries as $country)
                <tr>
                    <td class="whitespace-nowrap">{{ $country->flag }} {{ $country->name }} <span class="font-mono text-xs text-ink-500">{{ $country->code }}</span></td>
                    <td class="font-mono text-xs">@foreach ($country->currencies as $c){{ $c->code }}{{ $c->id === $country->default_currency_id ? '*' : '' }} @endforeach</td>
                    <td class="hidden text-xs lg:table-cell">{{ implode(', ', $country->channels ?? []) }}</td>
                    <td class="hidden text-xs xl:table-cell">{{ implode(', ', $country->features ?? []) }}</td>
                    @foreach (['test', 'live'] as $env)
                        @php $s = $status[$country->code][$env]; @endphp
                        <td>
                            @if ($s['secret_configured'] && $s['secret_prefix_ok'])<span class="badge badge-success">OK</span>
                            @elseif ($s['secret_configured'])<span class="badge badge-danger">Prefix</span>
                            @else<span class="badge badge-neutral" title="{{ $s['env_vars']['secret'] }}">Unset</span>@endif
                        </td>
                    @endforeach
                    <td>{!! $country->is_enabled ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-neutral">Disabled</span>' !!}</td>
                    <td class="text-right"><a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-ink-500">* default currency</p>
</div>
@endsection
