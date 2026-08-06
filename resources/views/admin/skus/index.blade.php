<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admin — Plans & Pricing') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 rounded-md bg-green-50 text-green-800">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-500 mb-4">
                Run <code class="bg-gray-100 px-1 rounded">php artisan vmos:sync-skus</code> to pull new plans from VMOS.
                New plans default to a 30% markup and are active; adjust below.
            </p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Plan</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">VMOS cost</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Your price</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Active</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Sold out (VMOS)</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($skus as $sku)
                            <tr>
                                <form method="POST" action="{{ route('admin.skus.update', $sku) }}">
                                    @csrf
                                    @method('PATCH')
                                    <td class="px-4 py-3">
                                        {{ $sku->name }}
                                        <div class="text-xs text-gray-400">Android {{ $sku->android_version }} &middot; {{ $sku->duration_label }}</div>
                                    </td>
                                    <td class="px-4 py-3">${{ number_format($sku->vmos_cost_price, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0" name="price" value="{{ $sku->price }}"
                                               class="w-24 rounded-md border-gray-300 text-sm">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="active" value="1" @checked($sku->active)>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $sku->sell_out ? 'text-red-600' : 'text-gray-400' }}">
                                            {{ $sku->sell_out ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-secondary-button type="submit">Save</x-secondary-button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No SKUs synced yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
