@extends('layouts.guest')
@section('title', 'Reset your password')

@section('content')
<p class="text-sm text-ink-400">Enter your email address and we'll send you a link to choose a new password.</p>
<form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
    @csrf
    <div>
        <label for="email" class="label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input @error('email') input-error @enderror">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="btn btn-primary w-full">Email reset link</button>
</form>
<p class="text-center text-sm text-ink-400"><a href="{{ route('login') }}" class="text-brand-400 hover:underline">Back to sign in</a></p>
@endsection
