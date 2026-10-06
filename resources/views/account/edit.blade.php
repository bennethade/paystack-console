@extends('layouts.app')
@section('title', 'Account')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-2xl font-semibold text-ink-100">Account</h1>

    <section class="card" aria-labelledby="profile-heading">
        <div class="card-header"><h2 id="profile-heading" class="font-semibold text-ink-100">Profile</h2></div>
        <form method="POST" action="{{ route('account.profile') }}" class="card-body space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" required>
                </div>
                <div>
                    <label class="label" for="email-ro">Email</label>
                    <input id="email-ro" value="{{ $user->email }}" class="input" disabled>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-ink-400">
                Role: <span class="badge badge-info">{{ $user->role->label() }}</span>
                Live requests: <span class="badge {{ $user->can_use_live ? 'badge-danger' : 'badge-neutral' }}">{{ $user->can_use_live ? 'Allowed' : 'Not allowed' }}</span>
                @if ($user->last_login_at) Last sign-in {{ $user->last_login_at->diffForHumans() }} @endif
            </div>
            <button class="btn btn-primary">Save profile</button>
        </form>
    </section>

    <section class="card" aria-labelledby="password-heading">
        <div class="card-header"><h2 id="password-heading" class="font-semibold text-ink-100">Change password</h2></div>
        <form method="POST" action="{{ route('account.password') }}" class="card-body grid gap-4 sm:grid-cols-3">
            @csrf @method('PUT')
            <div>
                <label for="current_password" class="label">Current password</label>
                <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
            </div>
            <div>
                <label for="password" class="label">New password</label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
            <div class="sm:col-span-3"><button class="btn btn-primary">Change password</button></div>
        </form>
    </section>

    <section class="card" aria-labelledby="sessions-heading">
        <div class="card-header"><h2 id="sessions-heading" class="font-semibold text-ink-100">Active sessions</h2></div>
        <div class="card-body space-y-4">
            @if ($sessions === [])
                <p class="text-sm text-ink-400">Session listing requires SESSION_DRIVER=database.</p>
            @else
                <ul class="divide-y divide-ink-800">
                    @foreach ($sessions as $session)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span class="text-ink-200">{{ $session['agent'] ?: 'Unknown device' }}</span>
                            <span class="font-mono text-xs text-ink-400">{{ $session['ip'] }} · {{ $session['last_active']->diffForHumans() }}
                                @if ($session['current'])<span class="badge badge-success ml-1">This device</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('account.sessions.destroy') }}" class="flex flex-wrap items-end gap-3">
                @csrf @method('DELETE')
                <div class="w-full sm:w-64">
                    <label for="sessions-password" class="label">Confirm with your password</label>
                    <input id="sessions-password" name="password" type="password" class="input" autocomplete="current-password" required>
                </div>
                <button class="btn btn-danger">Sign out other sessions</button>
            </form>
        </div>
    </section>
</div>
@endsection
