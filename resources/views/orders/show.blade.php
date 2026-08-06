<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Order') }} {{ $order->reference }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 rounded-md bg-green-50 text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Order summary</h3>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-gray-500">Plan</dt>
                    <dd class="text-gray-900">{{ $order->sku->name }} &times; {{ $order->quantity }}</dd>
                    <dt class="text-gray-500">Total</dt>
                    <dd class="text-gray-900">${{ number_format($order->total_price, 2) }}</dd>
                    <dt class="text-gray-500">Status</dt>
                    <dd class="text-gray-900">{{ str($order->status)->headline() }}</dd>
                </dl>
            </div>

            @if ($payment && $order->status === 'pending_payment')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Pay with USDT ({{ $payment->network }})</h3>

                    @if ($payment->status === 'awaiting_payment')
                        <p class="text-sm text-gray-600 mb-4">
                            Send exactly <strong>{{ number_format($payment->amount_crypto, 2) }} USDT</strong> to the address below,
                            then paste your transaction hash. This quote expires {{ $payment->expires_at->diffForHumans() }}.
                        </p>
                        <div class="bg-gray-50 rounded-md p-4 mb-4">
                            <p class="text-xs text-gray-500 mb-1">{{ $payment->network }} address</p>
                            <p class="font-mono text-sm break-all">{{ $payment->pay_to_address }}</p>
                        </div>

                        <form method="POST" action="{{ route('orders.payment', $order) }}" class="space-y-3">
                            @csrf
                            <x-input-label for="tx_hash" value="Transaction hash" />
                            <x-text-input id="tx_hash" name="tx_hash" type="text" class="w-full" placeholder="e.g. 3f8a9c..." required />
                            <x-input-error :messages="$errors->get('tx_hash')" />
                            <x-primary-button>{{ __('I\'ve sent the payment') }}</x-primary-button>
                        </form>
                    @elseif ($payment->status === 'submitted')
                        <p class="text-sm text-gray-600">
                            Transaction <span class="font-mono">{{ $payment->tx_hash }}</span> submitted — we're confirming it on-chain.
                            This page updates automatically once confirmed (usually within a couple of minutes).
                        </p>
                    @elseif ($payment->status === 'expired')
                        <p class="text-sm text-red-600">This payment quote expired without being confirmed. Please place a new order.</p>
                    @endif
                </div>
            @endif

            @if ($order->cloudInstances->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Your cloud phones</h3>
                    <ul class="text-sm space-y-1">
                        @foreach ($order->cloudInstances as $instance)
                            <li>
                                <a href="{{ route('instances.index') }}" class="text-indigo-600 hover:underline">
                                    {{ $instance->pad_code ?? 'Provisioning… (equipment #' . $instance->equipment_id . ')' }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($order->status === 'failed')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <p class="text-sm text-red-600">This order failed to provision: {{ $order->error_message }}. Contact support.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
