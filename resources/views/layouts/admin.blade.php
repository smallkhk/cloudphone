<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-ink-50 font-sans">
@php
    $pendingPayments = \App\Models\CryptoPayment::where('status', \App\Models\CryptoPayment::STATUS_SUBMITTED)->count();
    // Customers waiting on a reply, so a live chat isn't missed.
    $unreadChats = \App\Models\ChatConversation::unread()->count();
@endphp

<div x-data="{ sidebar: false }" class="min-h-screen">

    <div class="sticky top-0 z-40 flex items-center justify-between border-b border-ink-200 bg-white px-4 py-3 lg:hidden">
        <div class="flex items-center gap-2">
            <x-logo />
            <span class="badge-blue">Admin</span>
        </div>
        <button @click="sidebar = true" class="btn-ghost p-2" aria-label="Open menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false"
         class="fixed inset-0 z-40 bg-ink-900/50 lg:hidden"></div>

    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-ink-800 bg-ink-950 transition-transform duration-200 lg:translate-x-0">
        <div class="flex items-center justify-between px-5 py-5">
            <div class="flex items-center gap-2">
                <x-logo light />
            </div>
            <button @click="sidebar = false" class="p-1.5 text-ink-400 hover:text-white lg:hidden" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            @php
                $items = [
                    ['admin.dashboard', 'Dashboard', 'M4 5a1 1 0 011-1h5v7H4V5zm0 8h6v7H5a1 1 0 01-1-1v-6zm8-9h7a1 1 0 011 1v5h-8V4zm0 8h8v7a1 1 0 01-1 1h-7v-8z', null],
                    ['admin.orders.index', 'Orders', 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z', $pendingPayments ?: null],
                    ['admin.devices.index', 'Devices', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', null],
                    ['admin.users.index', 'Users', 'M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', null],
                    ['admin.skus.index', 'Plans & pricing', 'M7 7h.01M7 3h5a2 2 0 011.4.6l7 7a2 2 0 010 2.8l-5 5a2 2 0 01-2.8 0l-7-7A2 2 0 013 10V5a2 2 0 012-2z', null],
                    ['admin.chat.index', 'Live chat', 'M8 10h8M8 14h5M21 12a8 8 0 01-8 8H7l-4 3v-5.2A8 8 0 1121 12z', $unreadChats ?: null],
                    ['admin.proxies.index', 'Proxies', 'M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2a15 15 0 010 20 15 15 0 010-20z', null],
                    ['admin.settings.edit', 'Settings', 'M10.3 4.3a2 2 0 013.4 0l.5.9a2 2 0 002 1l1-.1a2 2 0 011.8 3l-.5.9a2 2 0 000 2.2l.5.9a2 2 0 01-1.8 3l-1-.1a2 2 0 00-2 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-2-1l-1 .1a2 2 0 01-1.8-3l.5-.9a2 2 0 000-2.2l-.5-.9a2 2 0 011.8-3l1 .1a2 2 0 002-1l.5-.9zM15 12a3 3 0 11-6 0 3 3 0 016 0z', null],
                    ['admin.diagnostics.index', 'API diagnostics', 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', null],
                ];
            @endphp

            <p class="px-3 pb-2 pt-3 text-xs font-semibold uppercase tracking-wider text-ink-500">Administration</p>

            @foreach ($items as [$route, $label, $icon, $badge])
                @php $isActive = request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route); @endphp
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $isActive ? 'bg-brand-600 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                    <svg class="h-5 w-5 flex-none {{ $isActive ? 'text-white' : 'text-ink-500' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                    </svg>
                    <span class="flex-1">{{ $label }}</span>
                    @if ($badge)
                        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold text-ink-900">{{ $badge }}</span>
                    @endif
                </a>
            @endforeach

            <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-ink-500">Shortcuts</p>
            <a href="{{ route('instances.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-300 transition-colors hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5 flex-none text-ink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Customer view
            </a>
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-300 transition-colors hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5 flex-none text-ink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10" />
                </svg>
                Public site
            </a>
        </nav>

        <div class="border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-xl px-3 py-2.5">
                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                    {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ auth()->user()?->name }}</p>
                    <p class="truncate text-xs text-ink-400">Administrator</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-300 transition-colors hover:bg-white/5 hover:text-white">
                    <svg class="h-5 w-5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-72">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            @isset($header)
                <div class="mb-8">{{ $header }}</div>
            @endisset

            <x-flash />

            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
