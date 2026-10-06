<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center p-4">
<main class="w-full max-w-md">
    <div class="mb-6 flex items-center justify-center gap-2 text-ink-100">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-500 font-mono text-lg font-black text-ink-950" aria-hidden="true">P</span>
        <span class="text-lg font-semibold">Paystack API Explorer</span>
    </div>
    <div class="card">
        <div class="card-body space-y-5 p-6">
            <h1 class="text-xl font-semibold text-ink-100">@yield('title')</h1>
            <x-flash />
            @yield('content')
        </div>
    </div>
    <p class="mt-6 text-center text-xs text-ink-500">Secret keys stay on the server. This console never sends them to your browser.</p>
</main>
</body>
</html>
