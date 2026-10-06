<!DOCTYPE html>
<html lang="en" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-full place-items-center p-6">
<main class="text-center">
    <p class="font-mono text-5xl font-bold text-brand-500">@yield('code')</p>
    <h1 class="mt-3 text-xl font-semibold text-ink-100">@yield('message')</h1>
    <p class="mt-2 text-sm text-ink-400">@yield('detail')</p>
    <a href="{{ url('/') }}" class="btn btn-secondary mt-6">Back to the explorer</a>
</main>
</body>
</html>
