@extends('layouts.app')
@section('title', $endpoint->exists ? 'Edit '.$endpoint->name : 'Add endpoint')

@section('content')
@include('admin.partials.nav')
<form method="POST" action="{{ $endpoint->exists ? route('admin.endpoints.update', $endpoint) : route('admin.endpoints.store') }}" class="space-y-5">
    @csrf
    @if ($endpoint->exists) @method('PUT') @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-100">{{ $endpoint->exists ? 'Edit endpoint' : 'Add endpoint' }}</h1>
        @if ($endpoint->exists && $endpoint->is_enabled)
            <a href="{{ $endpoint->url() }}" class="btn btn-secondary">Open in explorer</a>
        @endif
    </div>

    @if ($endpoint->exists && $endpoint->source === 'openapi')
        <p class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-200">
            Imported from the OpenAPI spec. Saving marks it as locally modified, so re-imports keep your changes (unless forced).
        </p>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <div class="card card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', $endpoint->name) }}" class="input" required></div>
            <div><label for="key" class="label">Key</label><input id="key" name="key" value="{{ old('key', $endpoint->key) }}" class="input font-mono" required placeholder="resource.action"></div>
            <div><label for="category_id" class="label">Category</label>
                <select id="category_id" name="category_id" class="input">
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $endpoint->category_id) == $category->id)>{{ $category->name }}</option>@endforeach
                </select></div>
            <div><label for="method" class="label">HTTP method</label>
                <select id="method" name="method" class="input font-mono">
                    @foreach (\App\Models\Endpoint::METHODS as $m)<option @selected(old('method', $endpoint->method) === $m)>{{ $m }}</option>@endforeach
                </select></div>
            <div><label for="path" class="label">Path</label><input id="path" name="path" value="{{ old('path', $endpoint->path) }}" class="input font-mono" required placeholder="/resource/{id}"></div>
            <div><label for="auth" class="label">Authorization</label>
                <select id="auth" name="auth" class="input">
                    <option value="secret" @selected(old('auth', $endpoint->auth) === 'secret')>Secret key</option>
                    <option value="public" @selected(old('auth', $endpoint->auth) === 'public')>Public key</option>
                </select></div>
            <div><label for="body_type" class="label">Body encoding</label>
                <select id="body_type" name="body_type" class="input">
                    @foreach (['json' => 'application/json', 'multipart' => 'multipart/form-data', 'none' => 'none'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('body_type', $endpoint->body_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div><label for="workflow" class="label">Special workflow</label>
                <select id="workflow" name="workflow" class="input"><option value="">None</option>
                    @foreach (\App\Models\Endpoint::WORKFLOWS as $value => $label)<option value="{{ $value }}" @selected(old('workflow', $endpoint->workflow) === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div><label for="pagination" class="label">Pagination</label>
                <select id="pagination" name="pagination" class="input"><option value="">None</option>
                    @foreach (['page', 'cursor', 'both'] as $value)<option @selected(old('pagination', $endpoint->pagination) === $value)>{{ $value }}</option>@endforeach
                </select></div>
            <div class="sm:col-span-2"><label for="docs_url" class="label">Official documentation URL</label><input id="docs_url" name="docs_url" type="url" value="{{ old('docs_url', $endpoint->docs_url) }}" class="input"></div>
            <div class="sm:col-span-2"><label for="description" class="label">Description</label><textarea id="description" name="description" rows="3" class="input">{{ old('description', $endpoint->description) }}</textarea></div>
            <div class="sm:col-span-2"><label for="notes" class="label">Notes shown to users</label><textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $endpoint->notes) }}</textarea></div>
            <div class="flex flex-wrap gap-4 sm:col-span-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $endpoint->is_enabled))> Enabled</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_dangerous" value="1" @checked(old('is_dangerous', $endpoint->is_dangerous))> Moves money / irreversible</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_documented" value="1" @checked(old('is_documented', $endpoint->is_documented))> On API reference pages</label>
            </div>
        </div>

        <div class="space-y-5">
            <fieldset class="card card-body space-y-3">
                <legend class="text-sm font-semibold text-ink-100">Market requirements</legend>
                <p class="help">The endpoint is only executable when the selected country has every required feature.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($features as $key => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="required_features[]" value="{{ $key }}" @checked(in_array($key, old('required_features', $endpoint->required_features ?? [])))> {{ $label }}</label>
                    @endforeach
                </div>
                <div class="text-sm">
                    <span class="label">Restrict to currencies (none = any)</span>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($currencies as $code)
                            <label class="flex items-center gap-1 font-mono"><input type="checkbox" name="supported_currencies[]" value="{{ $code }}" @checked(in_array($code, old('supported_currencies', $endpoint->supported_currencies ?? [])))> {{ $code }}</label>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            <div class="card card-body space-y-2">
                <label for="parameters_json" class="label">Parameters (JSON)</label>
                <textarea id="parameters_json" name="parameters_json" rows="18" class="input font-mono text-xs" spellcheck="false">{{ old('parameters_json', $parametersJson) }}</textarea>
                <p class="help">
                    Array of objects: <code>name</code>, <code>in</code> (path|query|body), <code>type</code> (string|integer|number|boolean|array|object|file),
                    optional <code>required</code>, <code>description</code>, <code>enum</code>, <code>format</code> (email|uri|date|date-time),
                    <code>items</code>, <code>properties</code>, <code>binding</code> (currency|country_code|bank_country|channels|recipient_type|callback_url), <code>sensitive</code>.
                    Every <code>{placeholder}</code> in the path needs an <code>"in": "path"</code> parameter.
                </p>
            </div>
        </div>
    </div>

    <div class="flex gap-2"><button class="btn btn-primary">Save endpoint</button><a href="{{ route('admin.endpoints.index') }}" class="btn btn-ghost">Cancel</a></div>
</form>

@if ($endpoint->exists && $endpoint->source === 'manual')
    <form method="POST" action="{{ route('admin.endpoints.destroy', $endpoint) }}" class="mt-4 text-right" x-data @submit="if (! confirm('Delete this endpoint?')) $event.preventDefault()">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm">Delete endpoint</button>
    </form>
@endif
@endsection
