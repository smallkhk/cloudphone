<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-ink-50 font-sans">
<div x-data="{ sidebar: false }" class="min-h-screen">

    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-40 flex items-center justify-between border-b border-ink-200 bg-white px-4 py-3 lg:hidden">
        <x-logo />
        <button @click="sidebar = true" class="btn-ghost p-2" aria-label="Open menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- Sidebar backdrop (mobile) --}}
    <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false"
         class="fixed inset-0 z-40 bg-ink-900/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-ink-200 bg-white transition-transform duration-200 lg:translate-x-0">
        <div class="flex items-center justify-between px-5 py-5">
            <x-logo />
            <button @click="sidebar = false" class="btn-ghost p-1.5 lg:hidden" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <p class="px-3 pb-2 pt-3 text-xs font-semibold uppercase tracking-wider text-ink-400">Menu</p>

            <x-side-nav-link :href="route('instances.index')" :active="request()->routeIs('instances.*')"
                             icon="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                My cloud phones
            </x-side-nav-link>

            <x-side-nav-link :href="route('plans.index')" :active="request()->routeIs('plans.*')"
                             icon="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2zM9 9h6M9 13h6M9 17h3">
                Browse plans
            </x-side-nav-link>

            <x-side-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')"
                             icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z">
                My orders
            </x-side-nav-link>

            <x-side-nav-link :href="route('wallet.index')" :active="request()->routeIs('wallet.*')"
                             icon="M3 6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6zM3 10h18M16 15h2"
                             :badge="auth()->user()?->balance > 0 ? '$'.number_format(auth()->user()->balance, 2) : null">
                Wallet
            </x-side-nav-link>

            <x-side-nav-link :href="route('email-accounts.index')" :active="request()->routeIs('email-accounts.*')"
                             icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                Email accounts
            </x-side-nav-link>

            <x-side-nav-link :href="route('phone-numbers.index')" :active="request()->routeIs('phone-numbers.*')"
                             icon="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                Phone numbers
            </x-side-nav-link>

            <x-side-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')"
                             icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                Profile
            </x-side-nav-link>

            @if (auth()->user()?->is_admin)
                <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-ink-400">Administration</p>
                <x-side-nav-link :href="route('admin.dashboard')"
                                 icon="M4 5a1 1 0 011-1h5v7H4V5zm0 8h6v7H5a1 1 0 01-1-1v-6zm8-9h7a1 1 0 011 1v5h-8V4zm0 8h8v7a1 1 0 01-1 1h-7v-8z">
                    Admin panel
                </x-side-nav-link>
            @endif
        </nav>

        <div class="border-t border-ink-200 p-3">
            <div class="flex items-center gap-3 rounded-xl px-3 py-2.5">
                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                    {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-ink-900">{{ auth()->user()?->name }}</p>
                    <p class="truncate text-xs text-ink-500">{{ auth()->user()?->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="btn-ghost w-full justify-start text-sm">
                    <svg class="h-5 w-5 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
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

<x-chat-widget />
</body>
</html>
