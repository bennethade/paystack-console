@extends('layouts.app')
@section('title', 'Payment result')

@php
    $status = $transaction['status'] ?? null;
    $currency = $transaction['currency'] ?? null;
    $currencyModel = $currency ? $context->country->currencies->firstWhere('code', $currency) : null;
    $amount = isset($transaction['amount']) && $currencyModel ? $currencyModel->formatSubunit((int) $transaction['amount']).' '.$currency : ($transaction['amount'] ?? null);
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-4">
    <h1 class="text-2xl font-semibold text-ink-100">Payment result</h1>

    <div class="card">
        <div class="card-body space-y-4">
            @if ($reference === '')
                <p class="text-sm text-ink-300">No transaction reference was supplied. Paystack appends <code class="font-mono">?reference=…</code> to the callback URL after checkout.</p>
            @else
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-ink-400">Reference</span>
                    <code class="rounded bg-ink-800 px-2 py-0.5 font-mono text-ink-100">{{ $reference }}</code>
                    <x-env-badge :environment="$context->environment->value" />
                </div>

                @if ($isMock)
                    <div class="rounded-lg border border-brand-500/40 bg-brand-500/10 p-3 text-sm text-brand-400">
                        Mock mode: no checkout happened and nothing was charged. This page shows how the real callback flow ends.
                    </div>
                @elseif ($context->isLive())
                    <div class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-300">
                        Live mode: the transaction is not verified automatically. Use the button below to verify it explicitly.
                    </div>
                @elseif ($error)
                    <div class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-300" role="alert">Automatic verification failed: {{ $error }}</div>
                @elseif ($transaction)
                    <div class="flex items-center gap-3">
                        <span class="badge text-sm {{ $status === 'success' ? 'badge-success' : (in_array($status, ['failed', 'abandoned', 'reversed']) ? 'badge-danger' : 'badge-warning') }}">{{ $status }}</span>
                        <span class="text-ink-300">{{ $transaction['gateway_response'] ?? '' }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-ink-500">Amount</dt><dd class="font-mono text-ink-100">{{ $amount ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-500">Channel</dt><dd class="text-ink-100">{{ $transaction['channel'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-500">Paid at</dt><dd class="text-ink-100">{{ $transaction['paid_at'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-500">Customer</dt><dd class="break-all text-ink-100">{{ $transaction['customer']['email'] ?? '—' }}</dd></div>
                    </dl>
                    <p class="text-xs text-ink-500">Verified with GET /transaction/verify/{{ $reference }}: HTTP {{ $result['response']['status'] ?? '—' }} in {{ $result['response']['duration_ms'] ?? '—' }} ms.
                        @if (! empty($result['history_id'])) <a class="text-brand-400 hover:underline" href="{{ route('explorer.history.show', $result['history_id']) }}">View full response</a>@endif
                    </p>
                @elseif (! auth()->user()->can('explorer.execute'))
                    <p class="text-sm text-ink-400">Your role cannot execute requests, so the transaction was not verified.</p>
                @endif

                <div class="flex flex-wrap gap-2">
                    @if ($verifyUrl)
                        <a href="{{ $verifyUrl }}" class="btn btn-primary">Open Verify Transaction</a>
                    @endif
                    <a href="{{ route('explorer.history.index') }}" class="btn btn-secondary">Request history</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
