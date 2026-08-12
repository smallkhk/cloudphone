<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="font-sans">
<div class="flex min-h-screen">
    {{-- Form side --}}
    <div class="flex w-full flex-col justify-center px-4 py-12 sm:px-6 lg:w-1/2 lg:px-16 xl:px-24">
        <div class="mx-auto w-full max-w-sm">
            <x-logo class="mb-10" />
            {{ $slot }}
        </div>
    </div>

    {{-- Brand side --}}
    <div class="relative hidden overflow-hidden bg-ink-950 lg:block lg:w-1/2">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -right-20 top-20 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)] [background-size:32px_32px]"></div>
        </div>

        <div class="relative flex h-full flex-col justify-center px-16 xl:px-24">
            <h2 class="text-3xl font-extrabold leading-tight tracking-tight text-white xl:text-4xl">
                Real Android phones,<br>running in the cloud
            </h2>
            <p class="mt-5 max-w-md text-base leading-relaxed text-ink-300">
                Launch isolated cloud devices in seconds. Each one has its own IP, IMEI and fingerprint —
                built for multi-account management, automation and app testing.
            </p>

            <ul class="mt-10 space-y-4">
                @foreach ([
                    'Deploy a new device in under a minute',
                    'Unique hardware identity per phone',
                    'Runs 24/7 without your own hardware',
                    'Full ADB and automation access',
                ] as $item)
                    <li class="flex items-center gap-3 text-sm text-ink-200">
                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-600/20 ring-1 ring-brand-500/30">
                            <svg class="h-3.5 w-3.5 text-brand-300" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<x-chat-widget />
</body>
</html>
