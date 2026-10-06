@props(['data', 'title' => null, 'maxHeight' => '28rem'])
{{-- Renders stored JSON with highlighting. $data is passed through a JSON island (escaped by @json). --}}
<div x-data="jsonBlock" {{ $attributes->class(['space-y-2']) }}>
    @if ($title)
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $title }}</h3>
            <button type="button" class="btn btn-ghost btn-sm" @click="copy()" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
        </div>
    @endif
    <script type="application/json">@json($data)</script>
    <pre class="code-block" style="max-height: {{ $maxHeight }}" x-html="html"></pre>
</div>
