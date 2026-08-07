<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My cloud phones" subtitle="Your devices, running in the cloud 24/7.">
            <x-slot name="actions">
                <a href="{{ route('plans.index') }}" class="btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    Add a device
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($instances->isEmpty())
        <div class="card p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50">
                <svg class="h-7 w-7 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="6" y="2" width="12" height="20" rx="2.5" />
                    <path d="M11 18h2" />
                </svg>
            </div>
            <h2 class="mt-6 text-lg font-semibold text-ink-900">No cloud phones yet</h2>
            <p class="mx-auto mt-2 max-w-sm text-sm text-ink-500">
                Pick a plan and your first device will be provisioned automatically once payment confirms.
            </p>
            <a href="{{ route('plans.index') }}" class="btn-primary mt-7">Browse plans</a>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($instances as $instance)
                <div class="card overflow-hidden">
                    {{-- Screen preview --}}
                    <div class="relative aspect-[4/3] bg-gradient-to-br from-ink-900 to-ink-950">
                        @if ($instance->screenshot_url)
                            <img src="{{ $instance->screenshot_url }}" alt="Screen preview" class="h-full w-full object-contain">
                        @else
                            <div class="flex h-full items-center justify-center">
                                <div class="text-center">
                                    <svg class="mx-auto h-9 w-9 text-ink-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="6" y="2" width="12" height="20" rx="2.5" />
                                    </svg>
                                    <p class="mt-2 text-xs text-ink-500">No preview yet</p>
                                </div>
                            </div>
                        @endif

                        <div class="absolute left-3 top-3">
                            <span class="{{ $instance->online ? 'badge bg-emerald-500/90 text-white ring-emerald-400/30' : 'badge bg-ink-800/90 text-ink-300 ring-white/10' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $instance->online ? 'bg-white' : 'bg-ink-400' }}"></span>
                                {{ $instance->statusLabel() }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <h3 class="truncate text-base font-semibold text-ink-900">
                            {{ $instance->nickname ?? $instance->sku?->name ?? 'Cloud Phone' }}
                        </h3>
                        <p class="mt-0.5 font-mono text-xs text-ink-500">{{ $instance->pad_code ?? 'Provisioning…' }}</p>

                        @if ($instance->expires_at)
                            <div class="mt-3 flex items-center gap-1.5 text-xs {{ $instance->isExpired() ? 'text-red-600' : 'text-ink-500' }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $instance->isExpired() ? 'Expired' : 'Expires' }} {{ $instance->expires_at->diffForHumans() }}
                            </div>
                        @endif

                        @if ($instance->pad_code)
                            <div class="mt-5 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('instances.screenshot', $instance) }}">
                                    @csrf
                                    <button class="btn-secondary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.9a2 2 0 001.7-1l.5-.9a2 2 0 011.7-1h2.4a2 2 0 011.7 1l.5.9a2 2 0 001.7 1H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <circle cx="12" cy="13" r="3" />
                                        </svg>
                                        Screenshot
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('instances.restart', $instance) }}">
                                    @csrf
                                    <button class="btn-secondary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006 5.3M4 15a8 8 0 0014 3.7" />
                                        </svg>
                                        Restart
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('instances.reset', $instance) }}"
                                      onsubmit="return confirm('Reset erases ALL data on this device and cannot be undone. Continue?')">
                                    @csrf
                                    <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Reset</button>
                                </form>
                            </div>
                        @else
                            <p class="mt-5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Provisioning in progress — controls appear once your device is ready.
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
