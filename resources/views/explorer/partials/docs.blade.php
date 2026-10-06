@php
    $locations = ['path' => 'Path parameters', 'query' => 'Query parameters', 'body' => 'Body parameters'];
    $bindingText = [
        'currency' => 'Pre-filled with the selected currency; limited to the country\'s currencies.',
        'country_code' => 'Pre-filled with the selected country code.',
        'bank_country' => 'Pre-filled with the value Paystack accepts for the selected country.',
        'channels' => 'Limited to channels documented for the selected country.',
        'recipient_type' => 'Limited to recipient types documented for the selected country.',
        'callback_url' => 'Defaults to this explorer\'s payment callback page.',
    ];
@endphp

<div class="space-y-2">
    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $endpoint->category->name }} → {{ $endpoint->name }}</h3>
    <div class="code-block"><x-method :method="$endpoint->method" /> {{ config('paystack.base_url') }}{{ $endpoint->path }}</div>
    @if ($endpoint->description)<p class="text-ink-300">{{ $endpoint->description }}</p>@endif
</div>

<dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
    <div><dt class="text-ink-500">Authorization</dt><dd class="font-mono text-ink-200">Bearer {{ $endpoint->auth === 'public' ? 'PUBLIC_KEY' : 'SECRET_KEY' }}</dd></div>
    <div><dt class="text-ink-500">Body</dt><dd class="font-mono text-ink-200">{{ $endpoint->sendsBody() ? ($endpoint->body_type === 'multipart' ? 'multipart/form-data' : 'application/json') : 'none' }}</dd></div>
    <div><dt class="text-ink-500">Pagination</dt><dd class="text-ink-200">{{ $endpoint->pagination ?? 'none' }}</dd></div>
    <div><dt class="text-ink-500">Source</dt><dd class="text-ink-200">{{ $endpoint->source === 'manual' ? 'API reference' : 'OpenAPI spec + reference' }}</dd></div>
</dl>

@if (($endpoint->required_features ?? []) || ($endpoint->supported_currencies ?? []))
    <div class="rounded-lg border border-ink-700 p-3 text-xs">
        <div class="mb-1 font-semibold uppercase tracking-wide text-ink-400">Availability</div>
        @foreach ($endpoint->required_features ?? [] as $feature)
            <p class="text-ink-300">Requires: {{ $featureLabels[$feature] ?? $feature }}</p>
        @endforeach
        @if ($endpoint->supported_currencies)
            <p class="text-ink-300">Currencies: <span class="font-mono">{{ implode(', ', $endpoint->supported_currencies) }}</span></p>
        @endif
    </div>
@endif

@foreach ($locations as $in => $title)
    @php $group = $params->where('in', $in); @endphp
    @if ($group->isNotEmpty())
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $title }}</h4>
            <div class="overflow-x-auto rounded-lg border border-ink-700">
                <table class="table">
                    <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                    @foreach ($group->sortByDesc(fn ($p) => $p['required'] ?? false) as $p)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-mono text-ink-100">{{ $p['name'] }}</span>
                                @if ($p['required'] ?? false)
                                    <span class="badge badge-danger ml-1">Required</span>
                                @else
                                    <span class="badge badge-neutral ml-1">Optional</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs text-ink-300">
                                {{ $p['type'] }}@if (isset($p['items']['type']))&lt;{{ $p['items']['type'] }}&gt;@endif
                                @if (! empty($p['format']))<br><span class="text-ink-500">{{ $p['format'] }}</span>@endif
                            </td>
                            <td class="text-xs text-ink-300">
                                {{ $p['description'] ?? '' }}
                                @php $enum = $p['enum'] ?? ($p['items']['enum'] ?? null); @endphp
                                @if ($enum)
                                    <div class="mt-1">Allowed: @foreach ($enum as $value)<code class="mr-1 rounded bg-ink-800 px-1 font-mono text-[11px]">{{ is_bool($value) ? var_export($value, true) : $value }}</code>@endforeach</div>
                                @endif
                                @if (! empty($p['binding']))
                                    <div class="mt-1 text-brand-400">{{ $bindingText[$p['binding']] ?? '' }}</div>
                                @endif
                                @if (! empty($p['properties']))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-ink-400">Object properties ({{ count($p['properties']) }})</summary>
                                        <ul class="mt-1 space-y-0.5 pl-3">
                                            @foreach ($p['properties'] as $prop)
                                                <li><span class="font-mono text-ink-200">{{ $prop['name'] }}</span> <span class="text-ink-500">{{ $prop['type'] ?? '' }}{{ ! empty($prop['required']) ? ', required' : '' }}</span>@if (! empty($prop['description'])) · {{ $prop['description'] }}@endif</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if (! empty($p['items']['properties']))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-ink-400">Item properties</summary>
                                        <ul class="mt-1 space-y-0.5 pl-3">
                                            @foreach ($p['items']['properties'] as $prop)
                                                <li><span class="font-mono text-ink-200">{{ $prop['name'] }}</span> <span class="text-ink-500">{{ $prop['type'] ?? '' }}</span>@if (! empty($prop['description'])) · {{ $prop['description'] }}@endif</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

@if ($endpoint->example)
    <x-json-block :data="$endpoint->example" title="Example request body (from the specification)" />
@endif

@if ($endpoint->docs_url)
    <p><a href="{{ $endpoint->docs_url }}" target="_blank" rel="noopener noreferrer" class="text-brand-400 hover:underline">View the official Paystack documentation ↗</a></p>
@endif
