@auth
    @if (auth()->user()->is_admin && ! filled(config('crypto.usdt_trc20_address')))
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            <svg class="h-5 w-5 flex-none text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3L13.71 4a2 2 0 00-3.42 0L3.36 16a2 2 0 001.71 3z" />
            </svg>
            <span class="flex-1">
                <strong>Checkout is disabled.</strong> No USDT receiving wallet is set, so customers can't complete a purchase.
            </span>
            <a href="{{ route('admin.settings.edit', 'payments') }}" class="btn-secondary btn-sm">Add wallet</a>
        </div>
    @endif
@endauth

@if ($skus->isEmpty())
    <div class="card mx-auto max-w-lg p-10 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-ink-100">
            <svg class="h-6 w-6 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.6a1 1 0 00-.9.6l-.9 1.8a1 1 0 01-.9.6h-3.4a1 1 0 01-.9-.6l-.9-1.8a1 1 0 00-.9-.6H4" />
            </svg>
        </div>
        <h2 class="mt-5 text-lg font-semibold text-ink-900">No plans available yet</h2>
        <p class="mt-2 text-sm text-ink-500">
            @auth
                @if (auth()->user()->is_admin)
                    Sync the catalogue from VMOS and set your prices to start selling.
                @else
                    We're setting things up. Please check back shortly.
                @endif
            @else
                We're setting things up. Please check back shortly.
            @endauth
        </p>
        @auth
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.skus.index') }}" class="btn-primary mt-6">Go to Plans &amp; pricing</a>
            @endif
        @endauth
    </div>
@else
    <div class="space-y-12">
        @foreach ($grouped as $deviceName => $variants)
            @php $first = $variants->first(); @endphp
            <section>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-ink-900">{{ $deviceName }}</h2>
                        <p class="mt-1 text-sm text-ink-500">
                            Android {{ $first->android_version }}
                            @if ($first->config_model) · {{ $first->config_model }} @endif
                        </p>
                    </div>
                    <span class="badge-blue">{{ $variants->count() }} {{ Str::plural('option', $variants->count()) }}</span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($variants->sortBy('duration_minutes') as $sku)
                        <div class="card flex flex-col p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                            <p class="text-sm font-medium text-ink-500">{{ $sku->duration_label }}</p>
                            <p class="mt-2 flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold tracking-tight text-ink-900">${{ number_format($sku->price, 2) }}</span>
                            </p>
                            <p class="mt-1 text-xs text-ink-400">Paid in USDT (TRC20)</p>

                            <div class="mt-5 flex-1 space-y-2 text-xs text-ink-600">
                                <p class="flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 flex-none text-brand-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Dedicated Android {{ $sku->android_version }} device
                                </p>
                                <p class="flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 flex-none text-brand-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Unique IMEI &amp; fingerprint
                                </p>
                                <p class="flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 flex-none text-brand-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Runs 24/7 · full ADB access
                                </p>
                            </div>

                            @auth
                                <form method="POST" action="{{ route('orders.store') }}" class="mt-6">
                                    @csrf
                                    <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="auto_renew" value="1">
                                    <button class="btn-primary w-full">Buy now</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary mt-6 w-full">Log in to buy</a>
                            @endauth
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
