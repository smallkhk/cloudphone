<x-admin-layout>
    <x-slot name="header">
        {{-- Plain "&" here: the component escapes it, so "&amp;" would double-escape. --}}
        <x-page-header title="Plans & pricing" subtitle="Your selling price versus what VMOS charges you.">
            <x-slot name="actions">
                <form method="POST" action="{{ route('admin.skus.sync') }}">
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

    {{-- Bulk pricing --}}
    <form method="POST" action="{{ route('admin.skus.bulk-markup') }}" class="card mb-6 p-5">
        @csrf
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div>
                <label class="label" for="markup_percent">Bulk re-price at cost +</label>
                <div class="flex items-center gap-2">
                    <input id="markup_percent" name="markup_percent" type="number" step="1" min="0" max="1000"
                           class="input w-28" value="30" required>
                    <span class="text-sm font-medium text-ink-600">%</span>
                </div>
            </div>
            <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-700">
                <input type="checkbox" name="only_unpriced" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                Only plans without a price
            </label>
            <button class="btn-secondary sm:ml-auto"
                    onclick="return confirm('Re-price plans using this markup?')">Apply markup</button>
        </div>
    </form>

    <div class="card">
        <form method="GET" class="flex gap-2 p-5">
            <input name="q" value="{{ $search }}" class="input max-w-sm" placeholder="Search plans…">
            <button class="btn-secondary">Search</button>
        </form>

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
                                    Android {{ $sku->android_version }}
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
                                <p class="text-sm text-ink-500">No plans synced yet.</p>
                                <p class="mt-1 text-xs text-ink-400">Add your VMOS credentials in Settings, then hit “Sync from VMOS”.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
