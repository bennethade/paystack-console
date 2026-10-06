@extends('layouts.app')
@section('title', 'Endpoints')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-100">Endpoint registry</h1>
            <p class="text-sm text-ink-400">Imported from Paystack's OpenAPI spec and the documented overlay. Toggle endpoints without changing code.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.endpoints.import') }}">
                @csrf
                <button class="btn btn-secondary" title="Re-import the bundled spec. Locally edited endpoints are preserved.">Re-import spec</button>
            </form>
            <a href="{{ route('admin.endpoints.create') }}" class="btn btn-primary">Add endpoint</a>
        </div>
    </div>

    <form method="GET" class="card card-body grid gap-3 sm:grid-cols-4">
        <div class="sm:col-span-2"><label for="q" class="label">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="name, key or path"></div>
        <div><label for="category" class="label">Category</label>
            <select id="category" name="category" class="input"><option value="">All</option>
                @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach
            </select></div>
        <div><label for="state" class="label">State</label>
            <select id="state" name="state" class="input"><option value="">Any</option>
                @foreach (['enabled' => 'Enabled', 'disabled' => 'Disabled', 'modified' => 'Locally modified', 'undocumented' => 'Not on reference pages', 'manual' => 'Manual / reference-only'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
        <div class="flex gap-2 sm:col-span-4"><button class="btn btn-primary">Filter</button><a href="{{ route('admin.endpoints.index') }}" class="btn btn-ghost">Reset</a></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Endpoint</th><th class="hidden md:table-cell">Category</th><th class="hidden lg:table-cell">Flags</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            @foreach ($endpoints as $endpoint)
                <tr>
                    <td>
                        <div class="text-ink-100">{{ $endpoint->name }}</div>
                        <div class="flex items-center gap-2"><x-method :method="$endpoint->method" /><span class="font-mono text-xs text-ink-400">{{ $endpoint->path }}</span></div>
                        <div class="font-mono text-[11px] text-ink-500">{{ $endpoint->key }}</div>
                    </td>
                    <td class="hidden text-xs md:table-cell">{{ $endpoint->category->name }}</td>
                    <td class="hidden lg:table-cell">
                        <div class="flex flex-wrap gap-1">
                            @if ($endpoint->is_dangerous)<span class="badge badge-danger">money</span>@endif
                            @if ($endpoint->auth === 'public')<span class="badge badge-info">public key</span>@endif
                            @if ($endpoint->workflow)<span class="badge badge-info">{{ $endpoint->workflow }}</span>@endif
                            @if ($endpoint->is_locally_modified)<span class="badge badge-warning">edited</span>@endif
                            @if (! $endpoint->is_documented)<span class="badge badge-warning">undocumented</span>@endif
                            @if ($endpoint->source === 'manual')<span class="badge badge-neutral">manual</span>@endif
                            @foreach ($endpoint->required_features ?? [] as $f)<span class="badge badge-neutral">{{ $f }}</span>@endforeach
                        </div>
                    </td>
                    <td>{!! $endpoint->is_enabled ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-neutral">Disabled</span>' !!}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <form method="POST" action="{{ route('admin.endpoints.toggle', $endpoint) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $endpoint->is_enabled ? 'Disable' : 'Enable' }}</button></form>
                            <a href="{{ route('admin.endpoints.edit', $endpoint) }}" class="btn btn-secondary btn-sm">Edit</a>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $endpoints->links() }}
</div>
@endsection
