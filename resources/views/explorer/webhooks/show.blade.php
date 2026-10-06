@extends('layouts.app')
@section('title', 'Webhook '.($event->event ?? $event->uuid))

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('explorer.webhooks.index') }}" class="text-xs text-ink-500 hover:text-ink-300">← Webhooks</a>
            <h1 class="mt-1 font-mono text-xl font-semibold text-ink-100">{{ $event->event ?? '(no event field)' }}</h1>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('explorer.webhooks.reverify', $event) }}">@csrf<button class="btn btn-secondary">Re-verify signature</button></form>
            @can('delete', $event)
                <form method="POST" action="{{ route('explorer.webhooks.destroy', $event) }}">@csrf @method('DELETE')<button class="btn btn-danger">Delete</button></form>
            @endcan
        </div>
    </div>

    <dl class="card card-body grid grid-cols-2 gap-3 text-sm lg:grid-cols-5">
        <div><dt class="text-xs text-ink-500">Signature</dt><dd>{!! $event->signature_valid ? '<span class="badge badge-success">Valid</span>' : '<span class="badge badge-danger">Invalid</span>' !!}</dd></div>
        <div><dt class="text-xs text-ink-500">Reference</dt><dd class="break-all font-mono text-xs">{{ $event->reference ?? '—' }}</dd></div>
        <div><dt class="text-xs text-ink-500">Market</dt><dd>{{ $event->country_code }} <x-env-badge :environment="$event->environment" /></dd></div>
        <div><dt class="text-xs text-ink-500">Source IP</dt><dd class="font-mono text-xs">{{ $event->source_ip }} {!! $event->ip_allowed ? '<span class="badge badge-success">Paystack</span>' : '<span class="badge badge-warning">Unknown</span>' !!}</dd></div>
        <div><dt class="text-xs text-ink-500">Received</dt><dd>{{ $event->created_at->format('Y-m-d H:i:s') }}</dd></div>
        <div class="col-span-2 lg:col-span-5"><dt class="text-xs text-ink-500">Verification</dt><dd class="text-ink-300">{{ $event->verification_note }}</dd></div>
        <div class="col-span-2 lg:col-span-5"><dt class="text-xs text-ink-500">x-paystack-signature</dt><dd class="break-all font-mono text-xs text-ink-300">{{ $event->signature ?? '—' }}</dd></div>
    </dl>

    <div class="grid gap-4 xl:grid-cols-3">
        <x-json-block class="card card-body" :data="$event->headers" title="Headers" />
        <x-json-block class="card card-body xl:col-span-2" :data="$event->decodedPayload()" title="Payload" max-height="40rem" />
    </div>
</div>
@endsection
