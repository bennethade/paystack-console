@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
<form method="POST" action="{{ route('login.attempt') }}" class="space-y-4" novalidate>
    @csrf
    <div>
        <label for="email" class="label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="input @error('email') input-error @enderror" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <div class="flex items-center justify-between">
            <label for="password" class="label">Password</label>
            <a href="{{ route('password.request') }}" class="mb-1 text-xs text-brand-400 hover:underline">Forgot password?</a>
        </div>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="input @error('password') input-error @enderror">
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-ink-300">
        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-500" {{ old('remember') ? 'checked' : '' }}>
        Remember me
    </label>
    <button type="submit" class="btn btn-primary w-full">Sign in</button>
</form>

@if (app(\App\Support\Settings::class)->bool('registration_enabled'))
    <p class="text-center text-sm text-ink-400">No account? <a href="{{ route('register') }}" class="text-brand-400 hover:underline">Create one</a></p>
@endif
@endsection
