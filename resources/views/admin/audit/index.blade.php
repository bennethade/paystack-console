@extends('layouts.app')
@section('title', 'Audit log')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-100">Audit log</h1>
        <form method="GET" class="flex gap-2">
            <input name="action" value="{{ $action }}" class="input" placeholder="Action prefix, e.g. auth." aria-label="Filter by action">
            <button class="btn btn-secondary">Filter</button>
        </form>
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>When</th><th>Action</th><th>User</th><th class="hidden md:table-cell">Details</th><th class="hidden lg:table-cell">IP</th></tr></thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap text-xs text-ink-400" title="{{ $entry->created_at }}">{{ $entry->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="font-mono text-xs">{{ $entry->action }}</td>
                    <td class="text-xs">{{ $entry->user?->email ?? '—' }}</td>
                    <td class="hidden max-w-md truncate font-mono text-[11px] text-ink-400 md:table-cell" title="{{ json_encode($entry->meta) }}">{{ $entry->meta ? json_encode($entry->meta) : '' }}</td>
                    <td class="hidden font-mono text-xs lg:table-cell">{{ $entry->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-400">No audit entries.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</div>
@endsection
