<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Proxies" subtitle="Static residential IPs you can buy and attach to your customers' devices." />
    </x-slot>

    @unless ($configured)
        <div class="card border-l-4 border-l-amber-400 p-5">
            <p class="text-sm text-ink-700">Add your VMOS credentials to manage proxies.</p>
            <a href="{{ route('admin.settings.edit', 'vmos') }}" class="btn-primary btn-sm mt-3">Go to VMOS settings</a>
        </div>
    @else
        @if ($errors_list = collect($errors)->filter())
            @if ($errors_list->isNotEmpty())
                <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                    <p class="font-semibold">Some proxy data couldn't be loaded:</p>
                    <ul class="mt-1.5 list-inside list-disc space-y-0.5 text-xs">
                        @foreach ($errors_list as $key => $message)
                            <li><strong>{{ $key }}</strong>: {{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if ($traffic)
            <div class="card mb-6 p-5">
                <p class="text-sm font-medium text-ink-500">Dynamic proxy traffic balance</p>
                <p class="mt-1.5 text-2xl font-extrabold text-ink-900">
                    {{ is_array($traffic) ? ($traffic['balance'] ?? $traffic['traffic'] ?? json_encode($traffic)) : $traffic }}
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Buy --}}
            <form method="POST" action="{{ route('admin.proxies.buy') }}" class="card p-6">
                @csrf
                <h2 class="text-base font-semibold text-ink-900">Buy static residential proxies</h2>
                <p class="mt-1 text-sm text-ink-500">Charged to your VMOS balance. Prices below are what VMOS charges you.</p>

                @if (empty($products))
                    <p class="mt-5 rounded-lg bg-ink-50 p-4 text-sm text-ink-500">
                        No proxy products returned by VMOS. Check
                        <a href="{{ route('admin.diagnostics.index', ['probe' => 'static_proxies']) }}" class="font-medium text-brand-600 hover:underline">Diagnostics</a>.
                    </p>
                @else
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="label" for="proxy_good_id">Package</label>
                            <select id="proxy_good_id" name="proxy_good_id" class="input" required>
                                @foreach ($products as $product)
                                    <option value="{{ $product['proxyGoodId'] }}">
                                        {{ $product['proxyGoodName'] ?? 'Package' }}
                                        — ${{ number_format(($product['proxyGoodPrice'] ?? 0) / 100, 2) }}
                                        ({{ match ((int) ($product['proxyGoodType'] ?? 0)) { 1 => 'SOCKS5', 2 => 'HTTP', 3 => 'HTTPS', default => 'General' } }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">VMOS quotes proxy prices in cents, so they're divided by 100 here.</p>
                        </div>

                        <div>
                            <label class="label" for="country">Country</label>
                            <select id="country" name="country" class="input" required
                                    onchange="document.getElementById('proxy_address').value = this.options[this.selectedIndex].dataset.label || ''">
                                @foreach ($regions as $region)
                                    <option value="{{ $region['country'] ?? '' }}" data-label="{{ $region['countryZh'] ?? '' }}">
                                        {{ $region['countryZh'] ?? $region['country'] ?? '?' }} ({{ strtoupper($region['country'] ?? '') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" id="proxy_address" name="proxy_address" value="{{ $regions[0]['countryZh'] ?? '' }}">

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label" for="num">Quantity</label>
                                <input id="num" name="num" type="number" min="1" max="20" value="1" class="input" required>
                            </div>
                            <label class="flex items-center gap-2.5 pb-2.5 pt-7 text-sm text-ink-700">
                                <input type="checkbox" name="auto_renew" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                Auto-renew
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary" onclick="return confirm('This charges your VMOS account. Continue?')">Buy proxies</button>
                    </div>
                @endif
            </form>

            {{-- Attach --}}
            <form method="POST" action="{{ route('admin.proxies.attach') }}" class="card p-6">
                @csrf
                <h2 class="text-base font-semibold text-ink-900">Attach a proxy to devices</h2>
                <p class="mt-1 text-sm text-ink-500">Mounts one of your proxies onto the selected cloud phones.</p>

                @if (empty($owned))
                    <p class="mt-5 rounded-lg bg-ink-50 p-4 text-sm text-ink-500">You don't own any proxies yet — buy some first.</p>
                @elseif ($devices->isEmpty())
                    <p class="mt-5 rounded-lg bg-ink-50 p-4 text-sm text-ink-500">No provisioned devices to attach a proxy to.</p>
                @else
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="label" for="proxy_id">Proxy</label>
                            <select id="proxy_id" name="proxy_id" class="input" required>
                                @foreach ($owned as $proxy)
                                    <option value="{{ $proxy['proxyId'] ?? '' }}">
                                        {{ $proxy['proxyHost'] ?? '?' }}:{{ $proxy['proxyPort'] ?? '?' }}
                                        · {{ strtoupper($proxy['proxyCountry'] ?? '') }}
                                        · {{ ($proxy['proxyUseNumber'] ?? 0) }} device(s) attached
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="label">Devices</label>
                            <div class="max-h-64 space-y-1.5 overflow-y-auto rounded-xl bg-ink-50 p-3 ring-1 ring-inset ring-ink-200">
                                @foreach ($devices as $device)
                                    <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-white">
                                        <input type="checkbox" name="pad_codes[]" value="{{ $device->pad_code }}"
                                               class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                        <span class="font-mono text-xs">{{ $device->pad_code }}</span>
                                        <span class="truncate text-xs text-ink-500">{{ $device->user?->email ?? 'in stock' }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary">Attach proxy</button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Owned proxies --}}
        <div class="card mt-6">
            <h2 class="px-5 py-4 text-base font-semibold text-ink-900">Your proxies</h2>
            <div class="table-wrap border-t border-ink-100">
                <table class="table">
                    <thead>
                        <tr><th>Address</th><th>Type</th><th>Country</th><th>Status</th><th>In use</th><th>Expires</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($owned as $proxy)
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-medium text-ink-900">{{ $proxy['proxyHost'] ?? '?' }}:{{ $proxy['proxyPort'] ?? '?' }}</p>
                                    <p class="font-mono text-xs text-ink-500">{{ $proxy['account'] ?? '' }}</p>
                                </td>
                                <td class="text-sm text-ink-600">
                                    {{ match ((int) ($proxy['proxyType'] ?? 0)) { 1 => 'SOCKS5', 2 => 'HTTP', 3 => 'HTTPS', default => '—' } }}
                                </td>
                                <td class="text-sm text-ink-600">{{ strtoupper($proxy['proxyCountry'] ?? '—') }}</td>
                                <td>
                                    <span class="{{ ((int) ($proxy['proxyStatus'] ?? 0)) === 1 ? 'badge-green' : 'badge-amber' }}">
                                        {{ match ((int) ($proxy['proxyStatus'] ?? 0)) {
                                            1 => 'Normal', 0 => 'Checking', 2 => 'Pending check', -1 => 'Check failed', default => 'Unknown',
                                        } }}
                                    </span>
                                </td>
                                <td class="text-sm text-ink-600">{{ $proxy['proxyUseNumber'] ?? 0 }}</td>
                                <td class="text-sm text-ink-600">
                                    {{ isset($proxy['expireTime']) ? \Carbon\Carbon::createFromTimestamp($proxy['expireTime'])->format('d M Y') : '—' }}
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.proxies.destroy') }}"
                                          onsubmit="return confirm('Delete this proxy? Devices using it will be unbound.')">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="host" value="{{ $proxy['proxyHost'] ?? '' }}">
                                        <input type="hidden" name="port" value="{{ $proxy['proxyPort'] ?? 0 }}">
                                        <input type="hidden" name="account" value="{{ $proxy['account'] ?? '' }}">
                                        <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-12 text-center text-sm text-ink-500">No proxies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless
</x-admin-layout>
