@extends('layouts.app')
@section('title', 'Request logs')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-100">Request logs (all users)</h1>
        <form method="POST" action="{{ route('admin.logs.purge') }}" class="flex items-end gap-2" x-data @submit="if (! confirm('Permanently delete these log entries?')) $event.preventDefault()">
            @csrf @method('DELETE')
            <div><label for="days" class="label">Delete entries older than (days)</label><input id="days" name="days" type="number" min="0" value="30" class="input w-28"></div>
            <button class="btn btn-danger">Purge</button>
        </form>
    </div>

    <form method="GET" class="card card-body grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div><label for="user" class="label">User</label>
            <select id="user" name="user" class="input"><option value="">All</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? null) == $u->id)>{{ $u->email }}</option>@endforeach
            </select></div>
        <div><label for="endpoint" class="label">Endpoint</label><input id="endpoint" name="endpoint" value="{{ $filters['endpoint'] ?? '' }}" class="input"></div>
        <div><label for="outcome" class="label">Outcome</label>
            <select id="outcome" name="outcome" class="input"><option value="">Any</option>
                <option value="success" @selected(($filters['outcome'] ?? '') === 'success')>2xx</option>
                <option value="error" @selected(($filters['outcome'] ?? '') === 'error')>Errors</option></select></div>
        <div><label for="environment" class="label">Environment</label>
            <select id="environment" name="environment" class="input"><option value="">Any</option>
                <option value="test" @selected(($filters['environment'] ?? '') === 'test')>Test</option>
                <option value="live" @selected(($filters['environment'] ?? '') === 'live')>Live</option></select></div>
        <div><label for="country" class="label">Country</label><input id="country" name="country" maxlength="2" value="{{ $filters['country'] ?? '' }}" class="input font-mono uppercase"></div>
        <div class="flex items-end gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('admin.logs.index') }}" class="btn btn-ghost">Reset</a></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Status</th><th>Endpoint</th><th>User</th><th class="hidden md:table-cell">Market</th><th class="hidden md:table-cell">Time</th><th class="hidden lg:table-cell">IP</th><th class="text-right">When</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td><x-status-badge :code="$log->status_code" :success="$log->success" /></td>
                    <td><a href="{{ route('explorer.history.show', $log) }}" class="hover:underline"><x-method :method="$log->method" /> <span class="font-mono text-xs">{{ $log->path }}</span></a></td>
                    <td class="text-xs">{{ $log->user?->email ?? '—' }}</td>
                    <td class="hidden font-mono text-xs md:table-cell">{{ $log->country_code }}/{{ $log->currency_code }} <x-env-badge :environment="$log->environment" /></td>
                    <td class="hidden font-mono text-xs md:table-cell">{{ $log->duration_ms }} ms</td>
                    <td class="hidden font-mono text-xs lg:table-cell">{{ $log->ip_address }}</td>
                    <td class="text-right text-xs text-ink-400" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-ink-400">No log entries.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
