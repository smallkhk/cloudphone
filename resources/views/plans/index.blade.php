<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cloud Phone Plans') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 rounded-md bg-green-50 text-green-800">{{ session('status') }}</div>
            @endif

            @if ($skus->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 text-gray-600">
                    No plans are available yet. Run <code>php artisan vmos:sync-skus</code> and set prices under Admin &rarr; Plans.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($skus as $sku)
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col">
                            <div class="p-6 flex-1">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $sku->name }}</h3>
                                <p class="text-sm text-gray-500 mt-1">Android {{ $sku->android_version }} &middot; {{ $sku->duration_label }}</p>
                                <p class="text-3xl font-bold text-gray-900 mt-4">${{ number_format($sku->price, 2) }}</p>
                                <p class="text-xs text-gray-400">paid in USDT (TRC20)</p>
                            </div>
                            <div class="p-6 pt-0">
                                @auth
                                    <form method="POST" action="{{ route('orders.store') }}">
                                        @csrf
                                        <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <x-primary-button class="w-full justify-center">{{ __('Buy now') }}</x-primary-button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}" class="block text-center w-full px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-700">
                                        {{ __('Log in to buy') }}
                                    </a>
                                @endauth
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
