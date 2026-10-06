@extends('layouts.app')
@section('title', 'Users')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-100">Users</h1>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2"><input name="q" value="{{ $q }}" class="input" placeholder="Search name or email" aria-label="Search users"><button class="btn btn-secondary">Search</button></form>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
        </div>
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="hidden md:table-cell">Live</th><th class="hidden md:table-cell">Requests</th><th class="hidden lg:table-cell">Last sign-in</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td><div class="text-ink-100">{{ $user->name }}</div><div class="text-xs text-ink-400">{{ $user->email }}</div></td>
                    <td><span class="badge badge-info">{{ $user->role->value }}</span></td>
                    <td>{!! $user->isActive() ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Suspended</span>' !!}</td>
                    <td class="hidden md:table-cell">{!! $user->can_use_live ? '<span class="badge badge-danger">Allowed</span>' : '<span class="text-xs text-ink-500">No</span>' !!}</td>
                    <td class="hidden md:table-cell"><a class="text-brand-400 hover:underline" href="{{ route('admin.logs.index', ['user' => $user->id]) }}">{{ $user->request_logs_count }}</a></td>
                    <td class="hidden text-xs text-ink-400 lg:table-cell">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="text-right"><a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
