<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $isLive = isset($explorerContext) && $explorerContext->isLive();
    $activeCategory = request()->route('category');
    $activeEndpoint = request()->route('endpoint');
    $user = auth()->user();
@endphp
<body class="h-full" x-data="appShell">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[100] btn btn-primary">Skip to content</a>

@if ($isLive)
    <div class="live-stripes sticky top-0 z-50 px-4 py-1.5 text-center text-xs font-bold tracking-widest text-white" role="alert">
        LIVE MODE · REQUESTS USE PRODUCTION CREDENTIALS AND CAN MOVE REAL MONEY
    </div>
@endif

<div class="flex min-h-full flex-col {{ $isLive ? 'ring-4 ring-inset ring-red-600/70' : '' }}">
    {{-- Top bar --}}
    <header class="sticky {{ $isLive ? 'top-7' : 'top-0' }} z-40 border-b border-ink-700 bg-ink-900/95 backdrop-blur">
        <div class="flex h-14 items-center gap-3 px-3 sm:px-4">
            <button type="button" class="btn btn-ghost lg:hidden" @click="drawerOpen = true" aria-label="Open navigation" :aria-expanded="drawerOpen">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 5h14a1 1 0 010 2H3a1 1 0 010-2zm0 4h14a1 1 0 010 2H3a1 1 0 010-2zm0 4h14a1 1 0 010 2H3a1 1 0 010-2z" clip-rule="evenodd"/></svg>
            </button>

            <a href="{{ route('explorer.index') }}" class="flex items-center gap-2 font-semibold text-ink-100">
                <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-500 font-mono text-sm font-black text-ink-950" aria-hidden="true">P</span>
                <span class="hidden sm:inline">Paystack API Explorer</span>
            </a>

            <button type="button" @click="openSearch()" class="ml-2 hidden flex-1 items-center gap-2 rounded-lg border border-ink-700 bg-ink-850 px-3 py-1.5 text-left text-sm text-ink-400 hover:border-ink-600 md:flex md:max-w-md">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                <span class="flex-1">Search endpoints…</span>
                <span class="kbd">/</span>
            </button>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" @click="openSearch()" class="btn btn-ghost md:hidden" aria-label="Search endpoints">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                </button>

                @isset($explorerContext)
                    <button type="button" class="hidden items-center gap-1 rounded-md border border-ink-700 px-2 py-1 font-mono text-xs text-ink-300 sm:flex lg:hidden" @click="drawerOpen = true" aria-label="Change country and currency">
                        {{ $explorerContext->country->flag }} {{ $explorerContext->country->code }} · {{ $explorerContext->currency->code }}
                    </button>
                    @if ($isLive)
                        <span class="badge border-red-500 bg-red-600 text-white">Live mode</span>
                    @else
                        <span class="badge badge-warning">Test mode</span>
                    @endif
                    @if (! empty($mockMode))
                        <span class="badge badge-info" title="Responses are mock data; Paystack is not called">Mock</span>
                    @endif
                @endisset

                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-ghost" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-ink-700 text-xs font-semibold text-ink-100" aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="hidden max-w-32 truncate text-sm md:inline">{{ $user->name }}</span>
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-56 rounded-lg border border-ink-700 bg-ink-850 p-1 shadow-xl" role="menu">
                        <div class="px-3 py-2 text-xs text-ink-400">
                            {{ $user->email }}<br><span class="text-ink-300">{{ $user->role->label() }}</span>
                        </div>
                        <a href="{{ route('account.edit') }}" class="nav-link" role="menuitem">Account &amp; sessions</a>
                        <a href="{{ route('explorer.history.index') }}" class="nav-link" role="menuitem">Request history</a>
                        <a href="{{ route('explorer.saved.index') }}" class="nav-link" role="menuitem">Saved requests</a>
                        @can('webhooks.view')
                            <a href="{{ route('explorer.webhooks.index') }}" class="nav-link" role="menuitem">Webhooks</a>
                        @endcan
                        @can('admin.access')
                            <a href="{{ route('admin.dashboard') }}" class="nav-link" role="menuitem">Admin</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link w-full text-left text-red-300" role="menuitem">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        {{-- Mobile drawer overlay --}}
        <div x-cloak x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/60 lg:hidden" @click="drawerOpen = false" aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] -translate-x-full overflow-y-auto border-r border-ink-700 bg-ink-900 transition-transform lg:sticky lg:top-14 lg:z-0 lg:h-[calc(100vh-3.5rem)] lg:w-72 lg:translate-x-0"
            :class="drawerOpen ? 'translate-x-0' : ''"
            aria-label="Explorer navigation">
            <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3 lg:hidden">
                <span class="font-semibold text-ink-100">Navigation</span>
                <button type="button" class="btn btn-ghost btn-sm" @click="drawerOpen = false" aria-label="Close navigation">✕</button>
            </div>

            @isset($explorerContext)
                {{-- Country / currency / environment --}}
                <div class="space-y-3 border-b border-ink-700 p-4" x-data="contextSwitcher" data-url="{{ route('explorer.context') }}">
                    <div>
                        <label for="ctx-country" class="label">Country</label>
                        <select id="ctx-country" class="input" x-model="country" @change="onCountryChange()" :disabled="saving">
                            <template x-for="c in countries" :key="c.code">
                                <option :value="c.code" x-text="`${c.flag ?? ''} ${c.name}`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="ctx-currency" class="label">Currency</label>
                            <select id="ctx-currency" class="input font-mono" x-model="currency" @change="save()" :disabled="saving">
                                <template x-for="code in currencies" :key="code">
                                    <option :value="code" x-text="code"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="ctx-env" class="label">Environment</label>
                            <select id="ctx-env" class="input" x-model="environment" @change="onEnvironmentChange()" :disabled="saving"
                                    :class="environment === 'live' ? 'border-red-500 text-red-300' : ''">
                                <option value="test">Test</option>
                                @if ($liveAvailable)
                                    <option value="live">Live</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <p x-cloak x-show="error" x-text="error" class="error-text" role="alert"></p>
                    <p x-cloak x-show="saving" class="help">Switching…</p>

                    <div class="flex flex-wrap gap-1.5 text-[11px]">
                        @if ($credentialStatus['secret_configured'] && $credentialStatus['secret_prefix_ok'])
                            <span class="badge badge-success" title="A {{ $explorerContext->environment->label() }} secret key is configured for {{ $explorerContext->country->name }}">Secret key ✓</span>
                        @elseif ($credentialStatus['secret_configured'])
                            <span class="badge badge-danger" title="The configured key does not match the {{ $explorerContext->environment->value }} environment">Key/env mismatch</span>
                        @else
                            <span class="badge badge-danger" title="Set {{ $credentialStatus['env_vars']['secret'] }} or PAYSTACK_SECRET_KEY">No secret key</span>
                        @endif
                        @if ($credentialStatus['public_configured'])
                            <span class="badge badge-neutral">Public key ✓</span>
                        @endif
                    </div>

                    {{-- Live confirmation --}}
                    <div x-cloak x-show="confirmLive" class="fixed inset-0 z-[60] grid place-items-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="live-switch-title">
                        <div class="card w-full max-w-md border-red-500/60" @click.outside="cancelLive()">
                            <div class="card-body space-y-3">
                                <h2 id="live-switch-title" class="text-lg font-semibold text-red-300">Switch to LIVE mode?</h2>
                                <p class="text-sm text-ink-300">Requests will use production credentials and can charge cards, move money and change real customer data. Every live request will still ask for confirmation.</p>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn btn-secondary" @click="cancelLive()">Stay in test</button>
                                    <button type="button" class="btn btn-live" @click="save()">Switch to live</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endisset

            <nav class="space-y-4 p-3" aria-label="Endpoints">
                <div class="space-y-0.5">
                    <a href="{{ route('explorer.index') }}" class="nav-link {{ request()->routeIs('explorer.index') ? 'nav-link-active' : '' }}">Dashboard</a>
                    <a href="{{ route('explorer.history.index') }}" class="nav-link {{ request()->routeIs('explorer.history.*') ? 'nav-link-active' : '' }}">History</a>
                    <a href="{{ route('explorer.saved.index') }}" class="nav-link {{ request()->routeIs('explorer.saved.*') ? 'nav-link-active' : '' }}">Saved requests</a>
                    @can('webhooks.view')
                        <a href="{{ route('explorer.webhooks.index') }}" class="nav-link {{ request()->routeIs('explorer.webhooks.*') ? 'nav-link-active' : '' }}">Webhooks</a>
                    @endcan
                    @can('admin.access')
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'nav-link-active' : '' }}">Admin</a>
                    @endcan
                </div>

                @if (! empty($favoriteKeys))
                    <div>
                        <h2 class="mb-1 px-2 text-[11px] font-semibold uppercase tracking-wider text-amber-300/80">★ Favorites</h2>
                        @foreach ($navigation as $category)
                            @foreach ($category['endpoints'] as $item)
                                @if (in_array($item['key'], $favoriteKeys, true))
                                    <a href="{{ $item['url'] }}" class="nav-link {{ $activeCategory === $category['slug'] && str_ends_with($item['url'], '/'.$activeEndpoint) ? 'nav-link-active' : '' }}">
                                        <x-method :method="$item['method']" />
                                        <span class="truncate">{{ $item['name'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                @endif

                <div>
                    <label for="sidebar-filter" class="sr-only">Filter endpoints</label>
                    <input id="sidebar-filter" type="search" x-model="sidebarFilter" placeholder="Filter endpoints…" class="input py-1.5 text-xs">
                </div>

                <div class="space-y-1">
                    @foreach ($navigation ?? [] as $category)
                        <details class="group" {{ $activeCategory === $category['slug'] ? 'open' : '' }}
                                 x-show="matchesSidebar(@js($category['name'].' '.collect($category['endpoints'])->pluck('name')->implode(' ').' '.collect($category['endpoints'])->pluck('path')->implode(' ')))"
                                 :open="sidebarFilter.trim() !== '' || {{ $activeCategory === $category['slug'] ? 'true' : 'false' }}">
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-ink-400 hover:text-ink-100">
                                <span>{{ $category['name'] }}</span>
                                <span class="text-ink-500 transition group-open:rotate-90" aria-hidden="true">▸</span>
                            </summary>
                            <ul class="mt-0.5 space-y-0.5 pl-1">
                                @foreach ($category['endpoints'] as $item)
                                    @php $isActive = $activeCategory === $category['slug'] && str_ends_with($item['url'], '/'.$activeEndpoint); @endphp
                                    <li x-show="matchesSidebar(@js($item['name'].' '.$item['path'].' '.$category['name']))">
                                        <a href="{{ $item['url'] }}" class="nav-link {{ $isActive ? 'nav-link-active' : '' }}" @if($isActive) aria-current="page" @endif>
                                            <x-method :method="$item['method']" />
                                            <span class="truncate">{{ $item['name'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach
                </div>
            </nav>
        </aside>

        <main id="main" class="min-w-0 flex-1 p-3 sm:p-5 lg:p-6" tabindex="-1">
            @if (! empty($announcement))
                <div class="mb-4 rounded-lg border border-brand-500/40 bg-brand-500/10 px-4 py-2 text-sm text-brand-400" role="status">{{ $announcement }}</div>
            @endif
            @isset($explorerSetupError)
                <div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300" role="alert">
                    <strong>Setup required:</strong> {{ $explorerSetupError }}
                </div>
            @endisset
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>

{{-- Global search --}}
<div x-cloak x-show="searchOpen" class="fixed inset-0 z-[70] flex items-start justify-center bg-black/70 p-4 pt-[10vh]" role="dialog" aria-modal="true" aria-label="Search endpoints" @keydown.escape.window="searchOpen = false">
    <div class="card w-full max-w-2xl overflow-hidden" @click.outside="searchOpen = false">
        <div class="flex items-center gap-2 border-b border-ink-700 px-3">
            <svg class="h-5 w-5 text-ink-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
            <input x-ref="searchInput" x-model="query" type="search" class="w-full bg-transparent py-3 text-sm text-ink-100 outline-none placeholder:text-ink-500"
                   placeholder="transaction initialize, banks, refund, DVA, charge…" aria-label="Search endpoints"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()" @input="highlighted = 0"
                   role="combobox" aria-controls="search-results" :aria-expanded="results.length > 0">
            <span class="kbd">Esc</span>
        </div>
        <ul id="search-results" class="max-h-[60vh] overflow-y-auto p-2" role="listbox">
            <template x-for="(r, i) in results" :key="r.key">
                <li role="option" :aria-selected="i === highlighted">
                    <a :href="r.url" class="flex items-start gap-3 rounded-md px-3 py-2" :class="i === highlighted ? 'bg-ink-800' : 'hover:bg-ink-850'" @mouseenter="highlighted = i">
                        <span class="method mt-0.5" :class="`method-${r.method}`" x-text="r.method"></span>
                        <span class="min-w-0">
                            <span class="block text-sm text-ink-100" x-text="r.name"></span>
                            <span class="block truncate font-mono text-xs text-ink-400" x-text="r.path"></span>
                        </span>
                        <span class="ml-auto shrink-0 text-xs text-ink-500" x-text="r.category"></span>
                    </a>
                </li>
            </template>
            <li x-show="query.trim() !== '' && results.length === 0" class="px-3 py-6 text-center text-sm text-ink-400">No endpoints match “<span x-text="query"></span>”.</li>
            <li x-show="query.trim() === ''" class="px-3 py-6 text-center text-sm text-ink-400">Type to search {{ count($navigation ?? []) }} categories of Paystack endpoints.</li>
        </ul>
    </div>
</div>

{{-- Data for the client. Non-executable JSON islands: public data only, never credentials. --}}
@php
    $endpointIndex = collect($navigation ?? [])
        ->flatMap(fn ($c) => $c['endpoints'])
        ->map(fn ($e) => array_intersect_key($e, array_flip(['key', 'name', 'method', 'path', 'category', 'url'])))
        ->values();
@endphp
<script type="application/json" id="endpoint-index">@json($endpointIndex)</script>
@isset($explorerContext)
<script type="application/json" id="explorer-context">@json(['countries' => $explorerCountries, 'context' => $explorerContext->toPublicArray()])</script>
@endisset
@stack('islands')
</body>
</html>
