@extends('layouts.app')
@section('title', 'Request history')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink-100">Request history</h1>
        @if ($logs->total() > 0)
            <form method="POST" action="{{ route('explorer.history.clear') }}" x-data @submit="if (! confirm('Delete your entire request history?')) $event.preventDefault()">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Clear history</button>
            </form>
        @endif
    </div>

    <form method="GET" class="card card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="f-endpoint" class="label">Endpoint</label>
            <input id="f-endpoint" name="endpoint" value="{{ $filters['endpoint'] ?? '' }}" class="input" placeholder="e.g. transaction.initialize">
        </div>
        <div>
            <label for="f-outcome" class="label">Outcome</label>
            <select id="f-outcome" name="outcome" class="input">
                <option value="">Any</option>
                <option value="success" @selected(($filters['outcome'] ?? '') === 'success')>2xx</option>
                <option value="error" @selected(($filters['outcome'] ?? '') === 'error')>Errors</option>
            </select>
        </div>
        <div>
            <label for="f-env" class="label">Environment</label>
            <select id="f-env" name="environment" class="input">
                <option value="">Any</option>
                <option value="test" @selected(($filters['environment'] ?? '') === 'test')>Test</option>
                <option value="live" @selected(($filters['environment'] ?? '') === 'live')>Live</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('explorer.history.index') }}" class="btn btn-ghost">Reset</a>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr><th>Status</th><th>Endpoint</th><th class="hidden md:table-cell">Market</th><th class="hidden sm:table-cell">Time</th><th class="hidden lg:table-cell">When</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td><x-status-badge :code="$log->status_code" :success="$log->success" />@if ($log->is_mock)<span class="badge badge-info ml-1">Mock</span>@endif</td>
                    <td class="min-w-0">
                        <a href="{{ route('explorer.history.show', $log) }}" class="block hover:underline">
                            <span class="flex items-center gap-2"><x-method :method="$log->method" /><span class="truncate font-mono text-xs">{{ $log->path }}</span></span>
                            <span class="text-xs text-ink-500">{{ $log->endpoint_key }}</span>
                        </a>
                    </td>
                    <td class="hidden md:table-cell"><span class="font-mono text-xs">{{ $log->country_code }}/{{ $log->currency_code }}</span> <x-env-badge :environment="$log->environment" /></td>
                    <td class="hidden font-mono text-xs sm:table-cell">{{ $log->duration_ms !== null ? $log->duration_ms.' ms' : '—' }}</td>
                    <td class="hidden text-xs text-ink-400 lg:table-cell" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('explorer.history.replay', $log) }}" class="btn btn-secondary btn-sm" title="Load into the form without executing">Replay</a>
                            <form method="POST" action="{{ route('explorer.history.duplicate', $log) }}">@csrf<button class="btn btn-ghost btn-sm">Duplicate</button></form>
                            <form method="POST" action="{{ route('explorer.history.destroy', $log) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Delete entry">✕</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-ink-400">No requests recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
