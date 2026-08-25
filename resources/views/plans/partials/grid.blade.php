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

        <div class="mt-4" x-data="{ selectedFamily: null, selectedSku: null, proxyMode: '' }">
            {{-- Devices — pick one to reveal its durations below. --}}
            <div class="flex flex-wrap gap-2">
                @foreach ($groups as $group)
                    @php
                        $variants = $skus[$group->name] ?? collect();
                        $first = $variants->first();
                    @endphp
                    @continue (! $first)

                    <button type="button"
                            @click="selectedFamily = (selectedFamily === '{{ $group->name }}') ? null : '{{ $group->name }}'; selectedSku = null; proxyMode = ''"
                            class="rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors"
                            :class="selectedFamily === '{{ $group->name }}' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-ink-200 text-ink-700 hover:border-brand-300'">
                        {{ $group->name }}
                    </button>
                @endforeach
            </div>

            {{-- Duration → proxy → buy, for whichever device is selected above. --}}
            @foreach ($groups as $group)
                @php
                    $variants = $skus[$group->name] ?? collect();
                @endphp
                @continue ($variants->isEmpty())

                <div x-show="selectedFamily === '{{ $group->name }}'" x-collapse x-cloak class="card mt-4 p-5">
                    <h3 class="text-sm font-semibold text-ink-900">1. Choose a duration</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($variants as $sku)
                            <button type="button" @click="selectedSku = (selectedSku === {{ $sku->id }}) ? null : {{ $sku->id }}"
                                    class="rounded-xl border px-4 py-3 text-left transition-colors"
                                    :class="selectedSku === {{ $sku->id }} ? 'border-brand-600 bg-brand-50' : 'border-ink-200 hover:border-brand-300'">
                                <span class="block text-xs font-medium text-ink-500">{{ $sku->duration_label }}</span>
                                <span class="block text-lg font-extrabold text-ink-900">${{ number_format($sku->price, 2) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($variants as $sku)
                        <div x-show="selectedSku === {{ $sku->id }}" x-collapse x-cloak class="mt-5 border-t border-ink-100 pt-5">
                            @auth
                                <h3 class="text-sm font-semibold text-ink-900">2. Proxy (optional)</h3>
                                <form method="POST" action="{{ route('orders.store') }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="auto_renew" value="1">
                                    <input type="hidden" name="country_code" value="{{ $preferredRegion }}">

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

                                    <h3 class="mt-2 text-sm font-semibold text-ink-900">3. Order</h3>
                                    <p class="mt-1 text-xs text-ink-500">
                                        {{ $group->name }} · {{ $sku->duration_label }} · ${{ number_format($sku->price, 2) }}
                                        @if ($preferredRegion) · {{ $regionOptions[$preferredRegion] ?? $preferredRegion }} @endif
                                    </p>
                                    <button class="btn-primary mt-3 w-full">Buy now</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary w-full">Log in to buy</a>
                            @endauth
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        @if ($groups->hasPages())
            <div class="mt-8">{{ $groups->links() }}</div>
        @endif
    @endif
@endif
