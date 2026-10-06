@extends('layouts.app')
@section('title', 'Webhooks')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink-100">Webhook testing</h1>
        <a href="https://paystack.com/docs/payments/webhooks/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Webhook docs ↗</a>
    </div>

    @unless ($enabled)
        <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-200">Webhook reception is disabled (PAYSTACK_WEBHOOKS_ENABLED or the admin feature flag).</div>
    @endunless

    <section class="card" aria-labelledby="urls-heading">
        <div class="card-header">
            <h2 id="urls-heading" class="font-semibold text-ink-100">Webhook URLs</h2>
            <span class="text-xs text-ink-500">Set one in Paystack Dashboard → Settings → API Keys &amp; Webhooks</span>
        </div>
        <div class="card-body space-y-3 text-sm">
            <p class="text-ink-300">Each URL identifies the market and environment, so the explorer knows which secret key verifies the <code class="font-mono">x-paystack-signature</code> (HMAC-SHA512 of the raw body).</p>
            @if ($isLocal)
                <p class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">APP_URL points at localhost. Paystack cannot reach localhost URLs; expose the app through a tunnel and update APP_URL (and TRUSTED_PROXIES).</p>
            @endif
            <div class="overflow-x-auto rounded-lg border border-ink-700">
                <table class="table">
                    <thead><tr><th>Market</th><th>Env</th><th>URL</th><th>Key</th></tr></thead>
                    <tbody>
                    @foreach ($endpoints as $row)
                        <tr x-data="copyButton">
                            <td class="whitespace-nowrap">{{ $row['country']->flag }} {{ $row['country']->name }}</td>
                            <td><x-env-badge :environment="$row['environment']->value" /></td>
                            <td class="font-mono text-xs">
                                <span class="break-all">{{ $row['url'] }}</span>
                                <button type="button" class="ml-1 text-ink-500 hover:text-brand-400" @click="copy(@js($row['url']))" x-text="copied ? 'copied' : '⧉'" aria-label="Copy URL">⧉</button>
                            </td>
                            <td>{!! $row['configured'] ? '<span class="badge badge-success">Configured</span>' : '<span class="badge badge-neutral">Not set</span>' !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-ink-500">
                Paystack sends webhooks from {{ implode(', ', $allowedIps) }}.
                IP allow-listing is {{ $enforceIps ? 'enforced' : 'recorded but not enforced' }} (PAYSTACK_WEBHOOK_ENFORCE_IPS).
            </p>
        </div>
    </section>

    <section class="card" aria-labelledby="verify-heading" x-data="signatureVerifier" data-url="{{ route('explorer.webhooks.verify') }}" data-country="{{ $countries->first()?->code }}">
        <div class="card-header"><h2 id="verify-heading" class="font-semibold text-ink-100">Signature checker</h2></div>
        <form class="card-body grid gap-3 lg:grid-cols-2" @submit.prevent="verify()">
            <div class="lg:row-span-3">
                <label for="sig-payload" class="label">Raw payload (exact body bytes)</label>
                <textarea id="sig-payload" x-model="payload" rows="8" class="input font-mono text-xs" required spellcheck="false"></textarea>
            </div>
            <div>
                <label for="sig-value" class="label">x-paystack-signature</label>
                <input id="sig-value" x-model="signature" class="input font-mono text-xs" required>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="sig-country" class="label">Market</label>
                    <select id="sig-country" x-model="country" class="input">
                        @foreach ($countries as $country)<option value="{{ $country->code }}">{{ $country->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="sig-env" class="label">Environment</label>
                    <select id="sig-env" x-model="environment" class="input"><option value="test">Test</option><option value="live">Live</option></select>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="btn btn-primary" :disabled="loading">Verify signature</button>
                <template x-if="result">
                    <span class="text-sm" :class="result.valid ? 'text-emerald-300' : 'text-red-300'" x-text="(result.valid ? '✓ ' : '✗ ') + result.note" role="status"></span>
                </template>
            </div>
        </form>
    </section>

    <section class="space-y-3" aria-labelledby="events-heading">
        <h2 id="events-heading" class="text-lg font-semibold text-ink-100">Received events</h2>
        <form method="GET" class="card card-body grid gap-3 sm:grid-cols-4">
            <div><label for="w-event" class="label">Event</label><input id="w-event" name="event" value="{{ $filters['event'] ?? '' }}" class="input" placeholder="charge.success"></div>
            <div><label for="w-sig" class="label">Signature</label>
                <select id="w-sig" name="signature" class="input"><option value="">Any</option>
                    <option value="valid" @selected(($filters['signature'] ?? '') === 'valid')>Valid</option>
                    <option value="invalid" @selected(($filters['signature'] ?? '') === 'invalid')>Invalid</option></select></div>
            <div><label for="w-env" class="label">Environment</label>
                <select id="w-env" name="environment" class="input"><option value="">Any</option>
                    <option value="test" @selected(($filters['environment'] ?? '') === 'test')>Test</option>
                    <option value="live" @selected(($filters['environment'] ?? '') === 'live')>Live</option></select></div>
            <div class="flex items-end gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('explorer.webhooks.index') }}" class="btn btn-ghost">Reset</a></div>
        </form>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Event</th><th>Reference</th><th>Market</th><th>Signature</th><th class="hidden md:table-cell">Source IP</th><th>Received</th></tr></thead>
                <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td><a href="{{ route('explorer.webhooks.show', $event) }}" class="font-mono text-xs text-brand-400 hover:underline">{{ $event->event ?? '(no event)' }}</a></td>
                        <td class="max-w-40 truncate font-mono text-xs">{{ $event->reference ?? '—' }}</td>
                        <td class="whitespace-nowrap font-mono text-xs">{{ $event->country_code }} <x-env-badge :environment="$event->environment" /></td>
                        <td>{!! $event->signature_valid ? '<span class="badge badge-success">Valid</span>' : '<span class="badge badge-danger">Invalid</span>' !!}</td>
                        <td class="hidden font-mono text-xs md:table-cell">{{ $event->source_ip }} @if ($event->ip_allowed)<span class="badge badge-success">Paystack IP</span>@endif</td>
                        <td class="text-xs text-ink-400" title="{{ $event->created_at }}">{{ $event->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-ink-400">No webhook events received yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $events->links() }}
    </section>
</div>
@endsection
