<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-20">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-white font-sans">

<header x-data="{ open: false, scrolled: false }"
        @scroll.window="scrolled = window.scrollY > 8"
        :class="scrolled ? 'bg-white/90 backdrop-blur border-ink-200/80 shadow-sm' : 'bg-transparent border-transparent'"
        class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-200">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8" aria-label="Main">
        <x-logo />

        <div class="hidden items-center gap-1 lg:flex">
            <a href="{{ route('home') }}#features" class="btn-ghost">Features</a>
            <a href="{{ route('home') }}#use-cases" class="btn-ghost">Use cases</a>
            <a href="{{ route('plans.index') }}" class="btn-ghost">Pricing</a>
            <a href="{{ route('home') }}#faq" class="btn-ghost">FAQ</a>
        </div>

        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ route('instances.index') }}" class="btn-secondary">Dashboard</a>
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}" class="btn-primary">Admin</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary">Get started</a>
            @endauth
        </div>

        <button @click="open = !open" class="btn-ghost p-2 lg:hidden" aria-label="Toggle menu" :aria-expanded="open.toString()">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </nav>

    <div x-show="open" x-cloak x-transition class="border-t border-ink-200 bg-white px-4 py-4 lg:hidden">
        <div class="flex flex-col gap-1">
            <a href="{{ route('home') }}#features" @click="open=false" class="btn-ghost justify-start">Features</a>
            <a href="{{ route('home') }}#use-cases" @click="open=false" class="btn-ghost justify-start">Use cases</a>
            <a href="{{ route('plans.index') }}" class="btn-ghost justify-start">Pricing</a>
            <a href="{{ route('home') }}#faq" @click="open=false" class="btn-ghost justify-start">FAQ</a>
            <div class="mt-3 flex flex-col gap-2 border-t border-ink-100 pt-3">
                @auth
                    <a href="{{ route('instances.index') }}" class="btn-secondary">Dashboard</a>
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary">Admin</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-secondary">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                @endauth
            </div>
        </div>
    </div>
</header>

<main>
    {{ $slot }}
</main>

<footer class="border-t border-ink-200 bg-ink-950 text-ink-300">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-logo light />
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-400">
                    {{ \App\Models\Setting::get('site_tagline', 'Real Android cloud phones running 24/7 in the cloud. Manage unlimited accounts, automate social media, and test apps — from any device.') }}
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-sm">
                    @if ($email = \App\Models\Setting::get('support_email'))
                        <a href="mailto:{{ $email }}" class="text-ink-300 hover:text-white">{{ $email }}</a>
                    @endif
                    @if ($tg = \App\Models\Setting::get('support_telegram'))
                        <a href="{{ $tg }}" class="text-ink-300 hover:text-white">Telegram</a>
                    @endif
                    @if ($wa = \App\Models\Setting::get('support_whatsapp'))
                        <a href="{{ $wa }}" class="text-ink-300 hover:text-white">WhatsApp</a>
                    @endif
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white">Product</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('home') }}#features" class="hover:text-white">Features</a></li>
                    <li><a href="{{ route('home') }}#use-cases" class="hover:text-white">Use cases</a></li>
                    <li><a href="{{ route('plans.index') }}" class="hover:text-white">Pricing</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white">Account</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @auth
                        <li><a href="{{ route('instances.index') }}" class="hover:text-white">My cloud phones</a></li>
                        <li><a href="{{ route('orders.index') }}" class="hover:text-white">Orders</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="hover:text-white">Log in</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white">Create account</a></li>
                    @endauth
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-ink-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Cloud phones powered by VMOS Cloud infrastructure.</p>
        </div>
    </div>
</footer>


<x-chat-widget />
</body>
</html>
