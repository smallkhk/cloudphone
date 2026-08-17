<x-admin-layout>
    <x-slot name="header">
        {{-- Plain "&" here: the component escapes it, so "&amp;" would double-escape. --}}
        <x-page-header title="Plans & pricing" subtitle="Your selling price versus what VMOS charges you.">
            <x-slot name="actions">
                <form method="POST" action="{{ $type === 'email_account' ? route('admin.skus.sync-email') : route('admin.skus.sync') }}">
                    @csrf
                    <button class="btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006 5.3M4 15a8 8 0 0014 3.7" />
                        </svg>
                        Sync from VMOS
                    </button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="flex gap-2 border-b border-ink-200">
        <a href="{{ route('admin.skus.index') }}"
           class="border-b-2 px-4 py-2.5 text-sm font-medium {{ $type === 'cloud_phone' ? 'border-brand-600 text-brand-600' : 'border-transparent text-ink-500 hover:text-ink-800' }}">
            Cloud phones
        </a>
        <a href="{{ route('admin.skus.index', ['type' => 'email_account']) }}"
           class="border-b-2 px-4 py-2.5 text-sm font-medium {{ $type === 'email_account' ? 'border-brand-600 text-brand-600' : 'border-transparent text-ink-500 hover:text-ink-800' }}">
            Email accounts
        </a>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Plans in catalogue" :value="number_format($stats['total'])" />
        <x-stat-card label="Live on storefront" :value="number_format($stats['live'])" tone="green" />
        <x-stat-card label="Matching your filter" :value="number_format($stats['matching'])" tone="amber" />
    </div>

    {{-- Bulk pricing --}}
    <form method="POST" action="{{ route('admin.skus.bulk-markup') }}" class="card mt-6 p-5">
        @csrf
        {{-- Carry the filter through so "only these" re-prices what's on screen. --}}
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="q" value="{{ $search }}">
        <input type="hidden" name="android" value="{{ $android }}">
        <input type="hidden" name="duration" value="{{ $duration }}">
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div>
                <label class="label" for="markup_percent">Bulk re-price at cost +</label>
                <div class="flex items-center gap-2">
                    <input id="markup_percent" name="markup_percent" type="number" step="1" min="0" max="1000"
                           class="input w-28" value="{{ \App\Models\Setting::get('default_markup_percent', 30) }}" required>
                    <span class="text-sm font-medium text-ink-600">%</span>
                </div>
            </div>
            <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-700">
                <input type="checkbox" name="only_unpriced" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                Only plans without a price
            </label>
            <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-700">
                <input type="checkbox" name="scoped" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                Only the {{ number_format($stats['matching']) }} plan(s) matching my filter
            </label>
            <button class="btn-secondary sm:ml-auto"
                    onclick="return confirm('Re-price plans using this markup?')">Apply markup</button>
        </div>
    </form>

    <div class="card mt-6">
        {{-- Search & filters --}}
        <form method="GET" class="flex flex-wrap items-center gap-2 p-5">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="relative min-w-[14rem] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input name="q" value="{{ $search }}" class="input w-full pl-9" placeholder="Search device, model or duration…">
            </div>

            @if ($type === 'cloud_phone')
                <select name="android" class="input w-auto">
                    <option value="">Any Android</option>
                    @foreach ($androidVersions as $version)
                        <option value="{{ $version }}" @selected($android === (string) $version)>Android {{ $version }}</option>
                    @endforeach
                </select>

                <select name="duration" class="input w-auto">
                    <option value="">Any duration</option>
                    @foreach ($durations as $d)
                        <option value="{{ $d->duration_minutes }}" @selected($duration === (string) $d->duration_minutes)>
                            {{ $d->duration_label ?: $d->duration_minutes.' min' }}
                        </option>
                    @endforeach
                </select>
            @endif

            <select name="status" class="input w-auto">
                <option value="">Any status</option>
                <option value="live" @selected($status === 'live')>Live only</option>
                <option value="hidden" @selected($status === 'hidden')>Hidden only</option>
                <option value="unpriced" @selected($status === 'unpriced')>No price set</option>
            </select>

            <button class="btn-secondary">Search</button>

            @if ($search || $android || $duration || $status)
                <a href="{{ route('admin.skus.index', ['type' => $type]) }}" class="btn-ghost btn-sm">Clear</a>
            @endif
        </form>

        {{-- Show/hide everything currently matching --}}
        @if ($search || $android || $duration || $status)
            <div class="flex flex-wrap items-center gap-2 border-t border-ink-100 bg-ink-50/60 px-5 py-3 text-sm text-ink-600">
                <span>Apply to all {{ number_format($stats['matching']) }} matching plan(s):</span>
                @foreach ([['1', 'Show on storefront'], ['0', 'Hide from storefront']] as [$value, $label])
                    <form method="POST" action="{{ route('admin.skus.bulk-status') }}">
                        @csrf
                        <input type="hidden" name="active" value="{{ $value }}">
                        <input type="hidden" name="type" value="{{ $type }}">
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="android" value="{{ $android }}">
                        <input type="hidden" name="duration" value="{{ $duration }}">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <button class="btn-secondary btn-sm"
                                onclick="return confirm('{{ $label }} for all {{ number_format($stats['matching']) }} matching plans?')">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Your cost</th>
                        <th>Sell price</th>
                        <th>Margin</th>
                        <th>Live</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($skus as $sku)
                        @php
                            $margin = (float) $sku->price - (float) $sku->vmos_cost_price;
                            $marginPct = (float) $sku->vmos_cost_price > 0
                                ? round($margin / (float) $sku->vmos_cost_price * 100)
                                : 0;
                        @endphp
                        <tr>
                            <form method="POST" action="{{ route('admin.skus.update', $sku) }}" id="sku-{{ $sku->id }}">
                                @csrf @method('PATCH')
                            </form>
                            <td>
                                <p class="text-sm font-medium text-ink-900">{{ $sku->name }}</p>
                                <p class="text-xs text-ink-500">
                                    @if ($sku->type === 'email_account')
                                        {{ $sku->config_model }}
                                    @else
                                        Android {{ $sku->android_version }}
                                    @endif
                                    @if ($sku->sell_out) · <span class="text-red-600">Sold out at VMOS</span> @endif
                                </p>
                            </td>
                            <td class="text-sm text-ink-600">{{ $sku->duration_label }}</td>
                            <td class="text-sm text-ink-600">${{ number_format($sku->vmos_cost_price, 2) }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <span class="text-sm text-ink-500">$</span>
                                    <input form="sku-{{ $sku->id }}" name="price" type="number" step="0.01" min="0"
                                           value="{{ $sku->price }}" class="input w-24 text-sm">
                                </div>
                            </td>
                            <td>
                                <span class="{{ $margin > 0 ? 'badge-green' : 'badge-red' }}">
                                    ${{ number_format($margin, 2) }} ({{ $marginPct }}%)
                                </span>
                            </td>
                            <td>
                                <label class="inline-flex cursor-pointer items-center">
                                    <input form="sku-{{ $sku->id }}" type="checkbox" name="active" value="1" @checked($sku->active)
                                           class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                </label>
                            </td>
                            <td class="text-right">
                                <button form="sku-{{ $sku->id }}" class="btn-secondary btn-sm">Save</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-14 text-center">
                                @if ($search || $android || $duration || $status)
                                    <p class="text-sm text-ink-500">No plans match that search.</p>
                                    <a href="{{ route('admin.skus.index') }}" class="btn-secondary btn-sm mt-4">Clear filters</a>
                                @else
                                    <p class="text-sm text-ink-500">No plans synced yet.</p>
                                    <p class="mt-1 text-xs text-ink-400">Add your VMOS credentials in Settings, then hit “Sync from VMOS”.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($skus->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">{{ $skus->links() }}</div>
        @endif
    </div>
</x-admin-layout>
