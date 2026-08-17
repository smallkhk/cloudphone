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

@if ($totalPlans === 0)
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
    {{-- Device type — VMOS's own "Standard" vs "High-end Real Machine" split --}}
    <div class="mb-4 flex gap-2 border-b border-ink-200">
        @foreach ([
            ['', 'All devices'],
            ['standard', 'Standard'],
            ['high_end', 'High-end Real Machine'],
        ] as [$value, $label])
            <a href="{{ route('plans.index', array_filter(['tier' => $value ?: null, 'q' => $search, 'android' => $android, 'model' => $model, 'duration' => $duration])) }}"
               class="border-b-2 px-4 py-2.5 text-sm font-medium {{ $tier === $value ? 'border-brand-600 text-brand-600' : 'border-transparent text-ink-500 hover:text-ink-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Android version --}}
    @if (! empty($androidVersions))
        <x-pill-filter label="Android" param="android" route="plans.index" all-label="Any"
            :active="$android"
            :options="collect($androidVersions)->mapWithKeys(fn ($v) => [$v => $v])"
            :params="['tier' => $tier ?: null, 'q' => $search ?: null, 'model' => $model ?: null, 'region' => $preferredRegion ?: null, 'duration' => $duration ?: null]" />
    @endif

    {{-- Device model --}}
    @if (! empty($models))
        <x-pill-filter label="Model" param="model" route="plans.index" all-label="Any"
            :active="$model"
            :options="collect($models)->mapWithKeys(fn ($m) => [$m => $m])"
            :params="['tier' => $tier ?: null, 'q' => $search ?: null, 'android' => $android ?: null, 'region' => $preferredRegion ?: null, 'duration' => $duration ?: null]" />
    @endif

    {{-- Region — doesn't filter devices (any device can be bought in any
         region), it just pre-selects the "Buy now" region below so you don't
         have to re-pick it per device. --}}
    @if (! empty($regionOptions))
        <x-pill-filter label="Region" param="region" route="plans.index" all-label="No preference"
            :active="$preferredRegion"
            :options="$regionOptions"
            :params="['tier' => $tier ?: null, 'q' => $search ?: null, 'android' => $android ?: null, 'model' => $model ?: null, 'duration' => $duration ?: null]" />
    @endif

    {{-- Search & duration --}}
    <form method="GET" action="{{ route('plans.index') }}" class="card mt-2 flex flex-wrap items-center gap-2 p-4">
        <input type="hidden" name="tier" value="{{ $tier }}">
        <input type="hidden" name="android" value="{{ $android }}">
        <input type="hidden" name="model" value="{{ $model }}">
        <input type="hidden" name="region" value="{{ $preferredRegion }}">
        <div class="relative min-w-[14rem] flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <input name="q" value="{{ $search }}" class="input w-full pl-9"
                   placeholder="Search a device — “Samsung”, “Pixel”, “V08”…" autocomplete="off">
        </div>

        <select name="duration" class="input w-auto" onchange="this.form.submit()">
            <option value="">Any duration</option>
            @foreach ($durations as $d)
                <option value="{{ $d->duration_minutes }}" @selected($duration === (string) $d->duration_minutes)>
                    {{ $d->duration_label ?: $d->duration_minutes.' min' }}
                </option>
            @endforeach
        </select>

        <button class="btn-primary">Search</button>

        @if ($search || $android || $duration || $model || $preferredRegion)
            <a href="{{ route('plans.index', array_filter(['tier' => $tier ?: null])) }}" class="btn-ghost btn-sm">Clear</a>
        @endif
    </form>

    @if ($groups->total() === 0)
        <div class="card mt-6 p-10 text-center">
            <h2 class="text-lg font-semibold text-ink-900">Nothing matched “{{ $search }}”</h2>
            <p class="mt-2 text-sm text-ink-500">Try a shorter search, or clear the filters to see every device.</p>
            <a href="{{ route('plans.index') }}" class="btn-primary mt-6">Show all devices</a>
        </div>
    @else
        <p class="mt-6 text-sm text-ink-500">
            {{ number_format($groups->total()) }} {{ Str::plural('device', $groups->total()) }} available
            @if ($search || $android || $duration || $model) for this search @endif
            · showing {{ $groups->firstItem() }}–{{ $groups->lastItem() }}
        </p>

        <div class="mt-4 space-y-4">
            @foreach ($groups as $group)
                @php
                    $variants = $skus[$group->name] ?? collect();
                    $first = $variants->first();
                @endphp
                @continue (! $first)

                {{-- Collapsed by default when the catalogue is large, so the page
                     stays scannable; the first family opens automatically. --}}
                <section x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="card overflow-hidden">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center gap-4 p-5 text-left transition-colors hover:bg-ink-50">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="6" y="2" width="12" height="20" rx="2.5" />
                                <path stroke-linecap="round" d="M10.5 18.5h3" />
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-base font-bold tracking-tight text-ink-900">{{ $group->name }}</span>
                                <span class="{{ $first->deviceTier() === 'standard' ? 'badge-gray' : 'badge-blue' }} flex-none">
                                    {{ $first->deviceTier() === 'standard' ? 'Standard' : 'High-end Real Machine' }}
                                </span>
                            </span>
                            <span class="mt-0.5 block text-sm text-ink-500">
                                Android {{ $first->android_version }}
                                @if ($first->config_model) · {{ $first->config_model }} @endif
                                · {{ $variants->count() }} {{ Str::plural('duration', $variants->count()) }}
                            </span>
                        </span>

                        <span class="hidden flex-none text-right sm:block">
                            <span class="block text-xs text-ink-400">from</span>
                            <span class="block text-lg font-extrabold text-ink-900">${{ number_format($group->from_price, 2) }}</span>
                        </span>

                        <svg class="h-5 w-5 flex-none text-ink-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse x-cloak>
                        <div class="grid gap-4 border-t border-ink-100 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($variants as $sku)
                                <div class="rounded-xl border border-ink-200 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg">
                                    <p class="text-sm font-medium text-ink-500">{{ $sku->duration_label }}</p>
                                    <p class="mt-2 flex items-baseline gap-1">
                                        <span class="text-3xl font-extrabold tracking-tight text-ink-900">${{ number_format($sku->price, 2) }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-ink-400">Paid in USDT (TRC20)</p>

                                    <div class="mt-5 space-y-2 text-xs text-ink-600">
                                        @foreach ([
                                            'Dedicated Android '.$sku->android_version.' device',
                                            'Unique IMEI & fingerprint',
                                            'Runs 24/7 · full ADB access',
                                        ] as $feature)
                                            <p class="flex items-center gap-2">
                                                <svg class="h-3.5 w-3.5 flex-none text-brand-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                {{ $feature }}
                                            </p>
                                        @endforeach
                                    </div>

                                    @auth
                                        <form method="POST" action="{{ route('orders.store') }}" class="mt-6" x-data="{ proxyMode: '' }">
                                            @csrf
                                            <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="auto_renew" value="1">

                                            @if (! empty($regionOptions))
                                                <label class="label text-xs" for="region-{{ $sku->id }}">Region</label>
                                                <select id="region-{{ $sku->id }}" name="country_code" class="input mb-3 text-sm">
                                                    <option value="" @selected(! $preferredRegion)>Let us choose</option>
                                                    @foreach ($regionOptions as $code => $label)
                                                        <option value="{{ $code }}" @selected($preferredRegion === $code)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @endif

                                            <label class="label text-xs" for="proxy-mode-{{ $sku->id }}">Proxy (optional)</label>
                                            <select id="proxy-mode-{{ $sku->id }}" name="proxy_mode" x-model="proxyMode" class="input mb-2 text-sm">
                                                <option value="">None</option>
                                                <option value="custom">Use my own proxy</option>
                                                @if (! empty($proxyProducts))
                                                    <option value="vmos">Buy a residential proxy</option>
                                                @endif
                                            </select>

                                            <div x-show="proxyMode === 'custom'" x-cloak class="mb-3 space-y-2 rounded-lg bg-ink-50 p-3">
                                                <input name="proxy_ip" placeholder="Proxy IP" class="input text-sm" :required="proxyMode === 'custom'">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input name="proxy_port" type="number" min="1" max="65535" placeholder="Port" class="input text-sm" :required="proxyMode === 'custom'">
                                                    <select name="proxy_type" class="input text-sm">
                                                        <option value="proxy">Proxy</option>
                                                        <option value="vpn">VPN</option>
                                                    </select>
                                                </div>
                                                <select name="proxy_name" class="input text-sm">
                                                    <option value="socks5">SOCKS5</option>
                                                    <option value="http-relay">HTTP</option>
                                                </select>
                                                <input name="proxy_account" placeholder="Username (optional)" class="input text-sm" autocomplete="off">
                                                <input name="proxy_password" type="password" placeholder="Password (optional)" class="input text-sm" autocomplete="new-password">
                                                <p class="hint">Applied automatically once your device finishes provisioning — usually within a few minutes of payment.</p>
                                            </div>

                                            @if (! empty($proxyProducts))
                                                <div x-show="proxyMode === 'vmos'" x-cloak class="mb-3 space-y-2 rounded-lg bg-ink-50 p-3">
                                                    <select name="proxy_good_id" class="input text-sm" :required="proxyMode === 'vmos'">
                                                        @foreach ($proxyProducts as $product)
                                                            <option value="{{ $product['proxyGoodId'] ?? '' }}">
                                                                {{ $product['proxyGoodName'] ?? 'Proxy package' }}
                                                                — +${{ number_format(($product['proxyGoodPrice'] ?? 0) / 100 * (1 + (\App\Models\Setting::get('default_markup_percent', 30) / 100)), 2) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <select name="proxy_country" class="input text-sm" :required="proxyMode === 'vmos'">
                                                        @foreach ($proxyRegions as $r)
                                                            <option value="{{ $r['country'] ?? '' }}">{{ $r['countryZh'] ?? ($r['country'] ?? '?') }} ({{ strtoupper($r['country'] ?? '') }})</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="hint">Bought and attached automatically once your device is ready. Added to your total below.</p>
                                                </div>
                                            @endif

                                            <button class="btn-primary w-full">Buy now</button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}" class="btn-primary mt-6 w-full">Log in to buy</a>
                                    @endauth
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        @if ($groups->hasPages())
            <div class="mt-8">{{ $groups->links() }}</div>
        @endif
    @endif
@endif
