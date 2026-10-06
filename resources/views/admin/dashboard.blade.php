@extends('layouts.app')
@section('title', 'Admin')

@section('content')
@include('admin.partials.nav')
<div class="space-y-6">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Users', $stats['users']],
            ['Endpoints enabled', $stats['endpoints_enabled'].' / '.$stats['endpoints_total']],
            ['Requests (24h)', $stats['requests_24h']],
            ['Errors (24h)', $stats['errors_24h']],
            ['Live requests (24h)', $stats['live_24h']],
            ['Avg response (24h)', $stats['avg_ms_24h'].' ms'],
            ['Webhooks (24h)', $stats['webhooks_24h']],
        ] as [$label, $value])
            <div class="card card-body">
                <div class="text-xs uppercase tracking-wide text-ink-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold text-ink-100">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="font-semibold text-ink-100">Most used endpoints (7 days)</h2></div>
            <table class="table">
                <thead><tr><th>Endpoint</th><th class="text-right">Requests</th><th class="text-right">Failures</th></tr></thead>
                <tbody>
                @forelse ($topEndpoints as $row)
                    <tr><td class="font-mono text-xs">{{ $row->endpoint_key }}</td><td class="text-right">{{ $row->total }}</td><td class="text-right">{{ $row->failures }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-ink-400">No activity.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="font-semibold text-ink-100">Recent audit events</h2><a class="text-xs text-brand-400" href="{{ route('admin.audit.index') }}">All →</a></div>
            <table class="table">
                <tbody>
                @forelse ($recentAudit as $entry)
                    <tr><td class="font-mono text-xs">{{ $entry->action }}</td><td class="truncate text-xs">{{ $entry->user?->email ?? '—' }}</td><td class="text-right text-xs text-ink-400">{{ $entry->created_at?->diffForHumans() }}</td></tr>
                @empty
                    <tr><td class="text-center text-ink-400">No audit events.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="card overflow-x-auto">
        <div class="card-header"><h2 class="font-semibold text-ink-100">Global activity</h2><a class="text-xs text-brand-400" href="{{ route('admin.logs.index') }}">All logs →</a></div>
        <table class="table">
            <thead><tr><th>Status</th><th>Endpoint</th><th>User</th><th>Market</th><th class="text-right">When</th></tr></thead>
            <tbody>
            @forelse ($recentRequests as $log)
                <tr>
                    <td><x-status-badge :code="$log->status_code" :success="$log->success" /></td>
                    <td><a class="hover:underline" href="{{ route('explorer.history.show', $log) }}"><x-method :method="$log->method" /> <span class="font-mono text-xs">{{ $log->path }}</span></a></td>
                    <td class="text-xs">{{ $log->user?->email ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $log->country_code }}/{{ $log->currency_code }} <x-env-badge :environment="$log->environment" /></td>
                    <td class="text-right text-xs text-ink-400">{{ $log->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-400">No requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
