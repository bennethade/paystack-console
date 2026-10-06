@extends('layouts.app')
@section('title', 'Request '.$log->endpoint_key)

@section('content')
<div class="space-y-4" x-data="copyButton">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ url()->previous() === url()->current() ? route('explorer.history.index') : url()->previous() }}" class="text-xs text-ink-500 hover:text-ink-300">← Back</a>
            <h1 class="mt-1 text-xl font-semibold text-ink-100">{{ $log->endpoint?->name ?? $log->endpoint_key }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <x-method :method="$log->method" /><code class="break-all font-mono text-xs">{{ $log->url }}</code>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($log->endpoint?->is_enabled)
                <a href="{{ route('explorer.history.replay', $log) }}" class="btn btn-primary">Replay (load into form)</a>
                <form method="POST" action="{{ route('explorer.history.duplicate', $log) }}">@csrf<button class="btn btn-secondary">Duplicate</button></form>
            @endif
            @can('delete', $log)
                <form method="POST" action="{{ route('explorer.history.destroy', $log) }}">@csrf @method('DELETE')<button class="btn btn-danger">Delete</button></form>
            @endcan
        </div>
    </div>

    <dl class="card card-body grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
        <div><dt class="text-xs text-ink-500">Status</dt><dd><x-status-badge :code="$log->status_code" :success="$log->success" /></dd></div>
        <div><dt class="text-xs text-ink-500">Time</dt><dd class="font-mono">{{ $log->duration_ms ?? '—' }} ms</dd></div>
        <div><dt class="text-xs text-ink-500">Market</dt><dd class="font-mono">{{ $log->country_code }} / {{ $log->currency_code }}</dd></div>
        <div><dt class="text-xs text-ink-500">Environment</dt><dd><x-env-badge :environment="$log->environment" />@if ($log->is_mock) <span class="badge badge-info">Mock</span>@endif</dd></div>
        <div><dt class="text-xs text-ink-500">When</dt><dd title="{{ $log->created_at }}">{{ $log->created_at->format('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="text-xs text-ink-500">User</dt><dd class="truncate">{{ $log->user?->email ?? 'deleted user' }}</dd></div>
    </dl>

    @if ($log->error_message)
        <div class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-300">{{ $log->error_message }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="card card-body space-y-4">
            <h2 class="font-semibold text-ink-100">Request</h2>
            <x-json-block :data="$log->request_headers" title="Headers (Authorization masked)" />
            @if ($log->request_query)<x-json-block :data="$log->request_query" title="Query" />@endif
            @if ($log->request_body)<x-json-block :data="$log->request_body" title="Body" />@endif
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-400">cURL</h3>
                    <button type="button" class="btn btn-ghost btn-sm" @click="copy(@js($curl))" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                </div>
                <pre class="code-block whitespace-pre-wrap break-all">{{ $curl }}</pre>
            </div>
        </section>
        <section class="card card-body space-y-4">
            <h2 class="font-semibold text-ink-100">Response</h2>
            @if ($log->response_headers)<x-json-block :data="$log->response_headers" title="Headers" max-height="12rem" />@endif
            @if ($log->response_body !== null)
                <x-json-block :data="$log->decodedResponse()" title="Body{{ $log->response_truncated ? ' (truncated)' : '' }}" max-height="40rem" />
            @else
                <p class="text-sm text-ink-400">Response body was not stored.</p>
            @endif
        </section>
    </div>
</div>
@endsection
