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

<div x-data="{ buyOpen: false, region: {{ $skus->isNotEmpty() ? "'".$skus->first()->default_country_code."'" : 'null' }} }">
    <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <h2 class="text-base font-semibold text-ink-900">Buy a static residential proxy</h2>
            <p class="mt-1 text-sm text-ink-500">
                {{ $skus->isNotEmpty() ? 'Pick a region and plan, then check out.' : 'No plans available yet.' }}
            </p>
        </div>

        @auth
            @if ($skus->isNotEmpty())
                <button type="button" @click="buyOpen = true" class="btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437m0 0L7.5 14.25M4.106 5.272l1.964 7.394m0 0h11.218a1.125 1.125 0 001.094-.852l1.5-6a1.125 1.125 0 00-1.094-1.398H5.25m0 0L4.106 5.272M7.5 18.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm10.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                    Buy Proxy
                </button>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn-primary">Log in to buy</a>
        @endauth
    </div>

    @if ($skus->isEmpty())
        <div class="card mx-auto mt-4 max-w-lg p-10 text-center">
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
    @endif

    {{-- Buy modal — mirrors VMOS's own "Purchase Proxy IP" popup: region, then plan, then submit. --}}
    @auth
        <div x-show="buyOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="buyOpen = false">
            <div @click.outside="buyOpen = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-ink-900">Purchase Proxy IP</h3>
                    <button type="button" @click="buyOpen = false" class="rounded-full p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4">
                    <label class="label">IP Type</label>
                    <div class="rounded-lg border-2 border-brand-600 bg-brand-50 p-3 text-sm font-medium text-brand-700">
                        Static Residential IP
                    </div>
                </div>

                <form method="POST" action="{{ route('orders.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="auto_renew" value="0">

                    <div>
                        <label class="label" for="proxy-region">Region</label>
                        <select id="proxy-region" class="input" x-model="region">
                            @foreach ($skus->pluck('default_country_code')->unique()->sort() as $code)
                                <option value="{{ $code }}">{{ \App\Services\Vmos\VmosRegionCatalog::nameFor($code) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Plan</label>
                        <div class="space-y-2">
                            @foreach ($skus as $sku)
                                <label x-show="region === '{{ $sku->default_country_code }}'" x-cloak
                                       class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-ink-200 p-3 hover:border-brand-400">
                                    <span class="flex items-center gap-2 text-sm text-ink-900">
                                        <input type="radio" name="sku_id" value="{{ $sku->id }}" class="text-brand-600 focus:ring-brand-500">
                                        {{ $sku->duration_label }}
                                    </span>
                                    <span class="text-sm font-semibold text-ink-900">${{ number_format($sku->price, 2) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-ink-100 pt-4">
                        <span class="text-xs text-ink-500">Paid in USDT (TRC20) or wallet balance</span>
                        <button class="btn-primary">Submit Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    @endauth
</div>

@auth
    <div class="mt-10">
        <h2 class="text-lg font-semibold text-ink-900">Add your own proxy</h2>
        <p class="mt-1 text-sm text-ink-500">Already have a proxy from somewhere else? Add it here for free — no order, nothing to buy.</p>

        <form method="POST" action="{{ route('proxies.store') }}" class="card mt-3 p-5">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="proxy-label">Label (optional)</label>
                    <input id="proxy-label" name="label" class="input" placeholder="e.g. Home proxy" value="{{ old('label') }}">
                </div>
                <div>
                    <label class="label" for="proxy-host">Server address</label>
                    <input id="proxy-host" name="host" class="input" placeholder="154.81.40.200" value="{{ old('host') }}" required>
                </div>
                <div>
                    <label class="label" for="proxy-port">Port</label>
                    <input id="proxy-port" name="port" type="number" class="input" placeholder="63007" value="{{ old('port') }}" required>
                </div>
                <div>
                    <label class="label" for="proxy-account">Account</label>
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
                <div class="sm:col-span-2">
                    <label class="label" for="proxy-remarks">Remarks (optional)</label>
                    <textarea id="proxy-remarks" name="remarks" class="input" rows="2">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="btn-primary">Add proxy</button>
            </div>
        </form>
    </div>

    <div class="mt-10 card" x-data="{ editing: null, viewing: null }">
        <h2 class="px-5 py-4 text-base font-semibold text-ink-900">Your proxies</h2>
        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th><th>Source</th><th>Proxy</th><th>Protocol</th><th>Status</th>
                        <th>Binding quantity</th><th>Attached to</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($owned as $proxy)
                        <tr>
                            <td class="text-sm text-ink-600">{{ $proxy->id }}</td>
                            <td class="text-sm text-ink-600">{{ $proxy->isCustom() ? 'Your own' : 'Bought' }}</td>
                            <td>
                                @if ($proxy->label)
                                    <p class="text-xs font-semibold text-ink-900">{{ $proxy->label }}</p>
                                @endif
                                <p class="font-mono text-xs font-medium text-ink-900">
                                    {{ $proxy->host ? "{$proxy->host}:{$proxy->port}" : 'Provisioning…' }}
                                </p>
                                @if ($proxy->account)
                                    <p class="font-mono text-xs text-ink-500">{{ $proxy->account }}</p>
                                @endif
                                @if ($proxy->remarks)
                                    <p class="mt-1 text-xs text-ink-400">{{ $proxy->remarks }}</p>
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
                                {{ $proxy->isAttached() ? 1 : 0 }}
                            </td>
                            <td class="text-sm text-ink-600">
                                {{ $proxy->attached_pad_code ?: '—' }}
                            </td>
                            <td class="text-right">
                                @if ($proxy->isDelivered())
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button type="button" class="btn-ghost btn-sm"
                                                @click="viewing = {
                                                    id: {{ $proxy->id }},
                                                    source: @js($proxy->isCustom() ? 'Your own' : 'Bought'),
                                                    label: @js($proxy->label),
                                                    host: @js($proxy->host),
                                                    port: {{ (int) $proxy->port }},
                                                    account: @js($proxy->account),
                                                    proxy_name: @js($proxy->proxy_name ? strtoupper($proxy->proxy_name) : '—'),
                                                    proxy_type: @js($proxy->proxy_type),
                                                    country_code: @js($proxy->country_code),
                                                    attached_pad_code: @js($proxy->attached_pad_code),
                                                    remarks: @js($proxy->remarks),
                                                    delivered_at: @js($proxy->delivered_at?->format('d M Y H:i')),
                                                }">
                                            View
                                        </button>

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
                                            <button type="button" class="btn-ghost btn-sm"
                                                    @click="editing = {
                                                        id: {{ $proxy->id }},
                                                        label: @js($proxy->label),
                                                        host: @js($proxy->host),
                                                        port: {{ (int) $proxy->port }},
                                                        account: @js($proxy->account),
                                                        proxy_name: @js($proxy->proxy_name),
                                                        proxy_type: @js($proxy->proxy_type),
                                                        remarks: @js($proxy->remarks),
                                                    }">
                                                Edit
                                            </button>

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
                        <tr><td colspan="8" class="py-12 text-center text-sm text-ink-500">Proxies you buy or add will show up here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Edit modal for a manually-added proxy --}}
        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="editing = null">
            <div @click.outside="editing = null" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" x-cloak>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-ink-900">Edit proxy</h3>
                    <button type="button" @click="editing = null" class="rounded-full p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="editing">
                    <form method="POST" :action="'/proxies/' + editing.id" class="mt-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="label">Label (optional)</label>
                                <input name="label" class="input" x-model="editing.label">
                            </div>
                            <div>
                                <label class="label">Server address</label>
                                <input name="host" class="input" x-model="editing.host" required>
                            </div>
                            <div>
                                <label class="label">Port</label>
                                <input name="port" type="number" class="input" x-model="editing.port" required>
                            </div>
                            <div>
                                <label class="label">Account</label>
                                <input name="account" class="input" x-model="editing.account" autocomplete="off">
                            </div>
                            <div>
                                <label class="label">Password</label>
                                <input name="password" type="password" class="input" autocomplete="off" placeholder="Leave blank to keep current">
                            </div>
                            <div>
                                <label class="label">Protocol</label>
                                <select name="proxy_name" class="input" x-model="editing.proxy_name">
                                    <option value="socks5">SOCKS5</option>
                                    <option value="http-relay">HTTP / HTTPS</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Mode</label>
                                <select name="proxy_type" class="input" x-model="editing.proxy_type">
                                    <option value="proxy">Proxy</option>
                                    <option value="vpn">VPN (all traffic)</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Remarks (optional)</label>
                                <textarea name="remarks" class="input" rows="2" x-model="editing.remarks"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-ink-100 pt-4">
                            <button class="btn-primary">Save changes</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- View modal — read-only details, for both bought and your-own proxies --}}
        <div x-show="viewing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="viewing = null">
            <div @click.outside="viewing = null" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" x-cloak>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-ink-900">Proxy details</h3>
                    <button type="button" @click="viewing = null" class="rounded-full p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="viewing">
                    <dl class="mt-4 divide-y divide-ink-100 text-sm">
                        <template x-if="viewing.label">
                            <div class="flex justify-between py-2"><dt class="text-ink-500">Label</dt><dd class="font-medium text-ink-900" x-text="viewing.label"></dd></div>
                        </template>
                        <div class="flex justify-between py-2"><dt class="text-ink-500">Source</dt><dd class="font-medium text-ink-900" x-text="viewing.source"></dd></div>
                        <div class="flex justify-between py-2"><dt class="text-ink-500">Server address</dt><dd class="font-mono text-ink-900" x-text="viewing.host"></dd></div>
                        <div class="flex justify-between py-2"><dt class="text-ink-500">Port</dt><dd class="font-mono text-ink-900" x-text="viewing.port"></dd></div>
                        <template x-if="viewing.account">
                            <div class="flex justify-between py-2"><dt class="text-ink-500">Account</dt><dd class="font-mono text-ink-900" x-text="viewing.account"></dd></div>
                        </template>
                        <div class="flex justify-between py-2"><dt class="text-ink-500">Protocol</dt><dd class="font-medium text-ink-900" x-text="viewing.proxy_name"></dd></div>
                        <template x-if="viewing.country_code">
                            <div class="flex justify-between py-2"><dt class="text-ink-500">Country</dt><dd class="font-medium text-ink-900" x-text="viewing.country_code"></dd></div>
                        </template>
                        <div class="flex justify-between py-2"><dt class="text-ink-500">Attached to</dt><dd class="font-mono text-ink-900" x-text="viewing.attached_pad_code || '—'"></dd></div>
                        <template x-if="viewing.delivered_at">
                            <div class="flex justify-between py-2"><dt class="text-ink-500">Delivered</dt><dd class="font-medium text-ink-900" x-text="viewing.delivered_at"></dd></div>
                        </template>
                        <template x-if="viewing.remarks">
                            <div class="flex justify-between gap-4 py-2"><dt class="flex-none text-ink-500">Remarks</dt><dd class="text-right text-ink-900" x-text="viewing.remarks"></dd></div>
                        </template>
                    </dl>
                </template>
            </div>
        </div>
    </div>
@endauth
