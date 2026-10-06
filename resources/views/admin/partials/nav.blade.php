@php
    $links = [
        'admin.dashboard' => ['Overview', 'admin.dashboard'],
        'admin.countries.*' => ['Countries', 'admin.countries.index'],
        'admin.currencies.*' => ['Currencies', 'admin.currencies.index'],
        'admin.categories.*' => ['Categories', 'admin.categories.index'],
        'admin.endpoints.*' => ['Endpoints', 'admin.endpoints.index'],
        'admin.users.*' => ['Users', 'admin.users.index'],
        'admin.logs.*' => ['Request logs', 'admin.logs.index'],
        'admin.settings.*' => ['Settings & flags', 'admin.settings.edit'],
        'admin.audit.*' => ['Audit log', 'admin.audit.index'],
    ];
@endphp
<nav class="mb-5 flex gap-1 overflow-x-auto border-b border-ink-700" aria-label="Admin sections">
    @foreach ($links as $pattern => [$label, $route])
        <a href="{{ route($route) }}" class="tab whitespace-nowrap {{ request()->routeIs($pattern) ? 'tab-active' : '' }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('explorer.webhooks.index') }}" class="tab whitespace-nowrap">Webhook events</a>
</nav>
