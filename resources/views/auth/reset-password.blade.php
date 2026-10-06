@extends('layouts.guest')
@section('title', 'Choose a new password')

@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label for="email" class="label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="input @error('email') input-error @enderror">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="label">New password</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="input @error('password') input-error @enderror">
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password_confirmation" class="label">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
    </div>
    <button type="submit" class="btn btn-primary w-full">Reset password</button>
</form>
@endsection
