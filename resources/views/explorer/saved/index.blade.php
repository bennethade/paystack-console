@extends('layouts.app')
@section('title', 'Saved requests')

@section('content')
<div class="space-y-4">
    <h1 class="text-2xl font-semibold text-ink-100">Saved requests</h1>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Endpoint</th><th class="hidden sm:table-cell">Market</th><th class="hidden md:table-cell">Saved</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            @forelse ($saved as $item)
                <tr>
                    <td class="text-ink-100">{{ $item->name }}</td>
                    <td>
                        @if ($item->endpoint)
                            <span class="flex items-center gap-2"><x-method :method="$item->endpoint->method" /><span class="truncate font-mono text-xs">{{ $item->endpoint->path }}</span></span>
                        @else
                            <span class="text-ink-500">Endpoint removed</span>
                        @endif
                    </td>
                    <td class="hidden font-mono text-xs sm:table-cell">{{ $item->country_code }}/{{ $item->currency_code }}</td>
                    <td class="hidden text-xs text-ink-400 md:table-cell">{{ $item->created_at->diffForHumans() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($item->endpoint?->is_enabled)
                                <a href="{{ $item->endpoint->url(['saved' => $item->id]) }}" class="btn btn-secondary btn-sm">Open</a>
                            @endif
                            <form method="POST" action="{{ route('explorer.saved.destroy', $item) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Delete {{ $item->name }}">✕</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-ink-400">Nothing saved yet. Use “Save…” on an endpoint page, or “Duplicate” in history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $saved->links() }}
</div>
@endsection
