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

<div class="mb-6 flex items-start gap-3 rounded-xl bg-ink-50 p-4 text-sm text-ink-600 ring-1 ring-inset ring-ink-200">
    <svg class="h-5 w-5 flex-none text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>
        Buying a device already lets you add a proxy alongside it at checkout — this page is only for a proxy on its
        own, so you can move it between devices later. Buying starts an order that finishes provisioning within a
        minute or two, then it's ready to attach below.
    </span>
</div>

@if ($countries->isNotEmpty())
    <x-pill-filter label="Country" param="country" route="proxies.index" all-label="All countries"
        :active="$country"
        :options="$countries->mapWithKeys(fn ($c) => [$c => \App\Services\Vmos\VmosRegionCatalog::nameFor($c)])" />
@endif

@if ($skus->isEmpty() && $countries->isEmpty())
    <div class="card mx-auto max-w-lg p-10 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-ink-100">
            <svg class="h-6 w-6 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
            </svg>
        </div>
        <h2 class="mt-5 text-lg font-semibold text-ink-900">No proxies available yet</h2>
        <p class="mt-2 text-sm text-ink-500">
            @auth
                @if (auth()->user()->is_admin)
                    Sync the catalogue under Plans &amp; pricing → Proxies.
                @else
                    We're setting things up. Please check back shortly.
                @endif
            @else
                We're setting things up. Please check back shortly.
            @endauth
        </p>
        @auth
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.skus.index', ['type' => 'proxy']) }}" class="btn-primary mt-6">Go to Plans &amp; pricing</a>
            @endif
        @endauth
    </div>
@elseif ($skus->isEmpty())
    <div class="card mx-auto max-w-lg p-10 text-center">
        <h2 class="text-lg font-semibold text-ink-900">No proxies for {{ \App\Services\Vmos\VmosRegionCatalog::nameFor($country) }}</h2>
        <p class="mt-2 text-sm text-ink-500">Try another country, or clear the filter to see everything available.</p>
        <a href="{{ route('proxies.index') }}" class="btn-secondary mt-6">Clear filter</a>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($skus as $sku)
            <div class="card p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                <p class="text-base font-bold tracking-tight text-ink-900">{{ \App\Services\Vmos\VmosRegionCatalog::nameFor($sku->default_country_code) }}</p>
                <p class="text-xs text-ink-500">{{ $sku->duration_label }}</p>
                <p class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold tracking-tight text-ink-900">${{ number_format($sku->price, 2) }}</span>
                </p>
                <p class="mt-1 text-xs text-ink-400">Paid in USDT (TRC20) or wallet balance</p>

                @auth
                    <form method="POST" action="{{ route('orders.store') }}" class="mt-5">
                        @csrf
                        <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="auto_renew" value="0">
                        <button class="btn-primary w-full">Buy now</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-primary mt-5 w-full">Log in to buy</a>
                @endauth
            </div>
        @endforeach
    </div>
@endif

@auth
    <div class="mt-10">
        <h2 class="text-lg font-semibold text-ink-900">Add your own proxy</h2>
        <p class="mt-1 text-sm text-ink-500">Already have a proxy from somewhere else? Add it here for free — no order, nothing to buy.</p>

        <form method="POST" action="{{ route('proxies.store') }}" class="card mt-3 p-5">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="proxy-host">Host / IP</label>
                    <input id="proxy-host" name="host" class="input" placeholder="154.81.40.200" value="{{ old('host') }}" required>
                </div>
                <div>
                    <label class="label" for="proxy-port">Port</label>
                    <input id="proxy-port" name="port" type="number" class="input" placeholder="63007" value="{{ old('port') }}" required>
                </div>
                <div>
                    <label class="label" for="proxy-account">Username</label>
                    <input id="proxy-account" name="account" class="input" autocomplete="off" value="{{ old('account') }}">
                </div>
                <div>
                    <label class="label" for="proxy-password">Password</label>
                    <input id="proxy-password" name="password" type="password" class="input" autocomplete="off">
                </div>
                <div>
                    <label class="label" for="proxy-name">Protocol</label>
                    <select id="proxy-name" name="proxy_name" class="input">
                        <option value="socks5">SOCKS5</option>
                        <option value="http-relay">HTTP / HTTPS</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="proxy-type">Mode</label>
                    <select id="proxy-type" name="proxy_type" class="input">
                        <option value="proxy">Proxy</option>
                        <option value="vpn">VPN (all traffic)</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="btn-primary">Add proxy</button>
            </div>
        </form>
    </div>

    <div class="mt-10 card">
        <h2 class="px-5 py-4 text-base font-semibold text-ink-900">Your proxies</h2>
        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th><th>Source</th><th>Proxy</th><th>Protocol</th><th>Status</th>
                        <th>Attached to</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($owned as $proxy)
                        <tr>
                            <td class="text-sm text-ink-600">{{ $proxy->id }}</td>
                            <td class="text-sm text-ink-600">{{ $proxy->isCustom() ? 'Your own' : 'Bought' }}</td>
                            <td>
                                <p class="font-mono text-xs font-medium text-ink-900">
                                    {{ $proxy->host ? "{$proxy->host}:{$proxy->port}" : 'Provisioning…' }}
                                </p>
                                @if ($proxy->account)
                                    <p class="font-mono text-xs text-ink-500">{{ $proxy->account }}</p>
                                @endif
                                @if ($proxy->purchase_error)
                                    <p class="mt-1 text-xs text-red-600">{{ $proxy->purchase_error }}</p>
                                @endif
                            </td>
                            <td class="text-sm text-ink-600">
                                {{ $proxy->proxy_name ? strtoupper($proxy->proxy_name) : '—' }}
                            </td>
                            <td>
                                <span class="{{ match ($proxy->purchase_status) {
                                    'COMPLETED' => 'badge-green',
                                    'FAILED' => 'badge-red',
                                    default => 'badge-amber',
                                } }}">{{ ucfirst(strtolower($proxy->purchase_status)) }}</span>
                            </td>
                            <td class="text-sm text-ink-600">
                                {{ $proxy->attached_pad_code ?: '—' }}
                            </td>
                            <td class="text-right">
                                @if ($proxy->isDelivered())
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if (! $proxy->attached_pad_code && $devices->isNotEmpty())
                                            <form method="POST" action="{{ route('proxies.attach', $proxy) }}" class="flex items-center gap-1">
                                                @csrf
                                                <select name="pad_code" class="input text-xs" required>
                                                    <option value="">Bind to…</option>
                                                    @foreach ($devices as $device)
                                                        <option value="{{ $device->pad_code }}">{{ $device->nickname ?: $device->pad_code }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn-secondary btn-sm">Bind</button>
                                            </form>
                                        @elseif ($proxy->attached_pad_code)
                                            <form method="POST" action="{{ route('proxies.detach', $proxy) }}">
                                                @csrf
                                                <button class="btn-ghost btn-sm">Unbind</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('proxies.test', $proxy) }}">
                                            @csrf
                                            <button class="btn-ghost btn-sm">Test</button>
                                        </form>

                                        @if ($proxy->isCustom())
                                            <form method="POST" action="{{ route('proxies.destroy', $proxy) }}"
                                                  onsubmit="return confirm('Remove this proxy?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-ink-500">Proxies you buy or add will show up here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
@endauth
