@extends('layouts.guest')
@section('title', 'Create account')

@section('content')
<form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
    @csrf
    <div>
        <label for="name" class="label">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="input @error('name') input-error @enderror">
        @error('name')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="email" class="label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="input @error('email') input-error @enderror">
        @error('email')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="label">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" class="input @error('password') input-error @enderror" aria-describedby="password-help">
        <p id="password-help" class="help">At least 10 characters with upper and lower case letters and a number.</p>
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password_confirmation" class="label">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
    </div>
    <button type="submit" class="btn btn-primary w-full">Create account</button>
</form>
<p class="text-center text-sm text-ink-400">Already registered? <a href="{{ route('login') }}" class="text-brand-400 hover:underline">Sign in</a></p>
@endsection
