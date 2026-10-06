@extends('layouts.app')
@section('title', $user->exists ? 'Edit '.$user->email : 'Add user')

@section('content')
@include('admin.partials.nav')
@php $self = $user->exists && $user->id === auth()->id(); @endphp
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="mx-auto max-w-2xl space-y-5">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <h1 class="text-xl font-semibold text-ink-100">{{ $user->exists ? 'Edit user' : 'Add user' }}</h1>

    <div class="card card-body grid gap-4 sm:grid-cols-2">
        <div><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" required></div>
        <div><label for="email" class="label">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="input" required></div>
        <div>
            <label for="role" class="label">Role</label>
            <select id="role" name="role" class="input" @disabled($self)>
                @foreach ($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? 'developer') === $role->value)>{{ $role->label() }}</option>@endforeach
            </select>
            @if ($self)<input type="hidden" name="role" value="{{ $user->role->value }}"><p class="help">You cannot change your own role.</p>@endif
        </div>
        <div>
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="input" @disabled($self)>
                @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $user->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            @if ($self)<input type="hidden" name="status" value="{{ $user->status->value }}">@endif
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="can_use_live" value="1" @checked(old('can_use_live', $user->can_use_live)) @disabled($self)>
            Allow live (production) requests. Also requires live mode to be enabled for the installation.
        </label>
        <div>
            <label for="password" class="label">{{ $user->exists ? 'New password (optional)' : 'Password' }}</label>
            <input id="password" name="password" type="password" class="input" autocomplete="new-password" @required(! $user->exists)>
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
        </div>
    </div>
    <p class="text-xs text-ink-500">Role permissions: Administrator (everything), Developer (browse, execute, webhooks), Viewer (browse documentation only).</p>
    <div class="flex gap-2"><button class="btn btn-primary">Save user</button><a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancel</a></div>
</form>
@endsection
