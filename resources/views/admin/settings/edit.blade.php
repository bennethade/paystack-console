@extends('layouts.app')
@section('title', 'Settings')

@section('content')
@include('admin.partials.nav')
<div class="grid gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5 xl:col-span-2">
        @csrf @method('PUT')
        <h1 class="text-xl font-semibold text-ink-100">Settings &amp; feature flags</h1>

        <fieldset class="card card-body space-y-3">
            <legend class="text-sm font-semibold text-ink-100">Feature flags</legend>
            @foreach ($definitions as $key => $definition)
                @if ($definition['group'] === 'features')
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="{{ $key }}" value="1" class="mt-1" @checked($values[$key])>
                        <span><span class="font-mono text-ink-100">{{ $key }}</span><span class="block text-xs text-ink-400">{{ $definition['description'] }}</span></span>
                    </label>
                @endif
            @endforeach
        </fieldset>

        <fieldset class="card card-body grid gap-4 sm:grid-cols-2">
            <legend class="text-sm font-semibold text-ink-100">General</legend>
            <div>
                <label for="default_role" class="label">Default role for new registrations</label>
                <select id="default_role" name="default_role" class="input">
                    <option value="developer" @selected($values['default_role'] === 'developer')>Developer</option>
                    <option value="viewer" @selected($values['default_role'] === 'viewer')>Viewer (read-only)</option>
                </select>
            </div>
            <div>
                <label for="history_retention_days" class="label">History retention (days)</label>
                <input id="history_retention_days" name="history_retention_days" type="number" min="1" value="{{ $values['history_retention_days'] }}" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="announcement" class="label">Announcement banner</label>
                <input id="announcement" name="announcement" value="{{ $values['announcement'] }}" class="input" maxlength="500">
            </div>
        </fieldset>

        <button class="btn btn-primary">Save settings</button>
    </form>

    <aside class="space-y-5">
        <section class="card card-body space-y-2 text-sm">
            <h2 class="font-semibold text-ink-100">Environment (read-only)</h2>
            <p class="text-xs text-ink-400">Set in <code>.env</code>. Feature flags can only narrow what these allow.</p>
            <dl class="space-y-1">
                @foreach ($environment as $name => $on)
                    <div class="flex items-center justify-between"><dt class="font-mono text-xs">{{ $name }}</dt><dd>{!! $on ? '<span class="badge badge-success">true</span>' : '<span class="badge badge-neutral">false</span>' !!}</dd></div>
                @endforeach
            </dl>
        </section>
        <section class="card card-body space-y-2 text-sm">
            <h2 class="font-semibold text-ink-100">Webhook events</h2>
            <p class="text-ink-400">{{ $webhookCount }} stored.</p>
            <form method="POST" action="{{ route('admin.webhooks.purge') }}" x-data @submit="if (! confirm('Delete all stored webhook events?')) $event.preventDefault()">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm">Delete all webhook events</button>
            </form>
        </section>
    </aside>
</div>
@endsection
