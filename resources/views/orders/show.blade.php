<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Order ' . $order->reference" :subtitle="$order->created_at->format('d M Y, H:i')">
            <x-slot name="actions">
                <a href="{{ route('orders.index') }}" class="btn-ghost">&larr; All orders</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Payment --}}
            @if ($payment && $order->status === 'pending_payment' && $payment->status === 'awaiting_payment')
                <div class="card overflow-hidden">
                    <div class="border-b border-ink-100 bg-gradient-to-r from-brand-600 to-indigo-600 px-6 py-5">
                        <h2 class="text-lg font-semibold text-white">Complete your payment</h2>
                        <p class="mt-1 text-sm text-brand-100">
                            Send the exact amount below, then paste your transaction hash.
                        </p>
                    </div>

                    <div class="p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-ink-50 p-4 ring-1 ring-inset ring-ink-200">
                                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Amount to send</p>
                                <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ number_format($payment->amount_crypto, 2) }} <span class="text-base font-semibold text-ink-500">USDT</span></p>
                                <p class="mt-1 text-xs text-ink-500">Network: {{ $payment->network }}</p>
                            </div>

                            <div class="rounded-xl bg-ink-50 p-4 ring-1 ring-inset ring-ink-200">
                                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Time remaining</p>
                                <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $payment->expires_at->diffForHumans(null, true) }}</p>
                                <p class="mt-1 text-xs text-ink-500">Quote expires {{ $payment->expires_at->format('H:i') }}</p>
                            </div>
                        </div>

                        <div class="mt-4" x-data="{ copied: false }">
                            <p class="label">Send USDT ({{ $payment->network }}) to this address</p>
                            <div class="flex gap-2">
                                <code class="flex-1 break-all rounded-xl bg-ink-900 px-4 py-3 font-mono text-xs text-white">{{ $payment->pay_to_address }}</code>
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $payment->pay_to_address }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="btn-secondary flex-none">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak class="text-emerald-600">Copied</span>
                                </button>
                            </div>
                            <p class="hint">Send only USDT on the {{ $payment->network }} network. Other tokens or networks will be lost.</p>
                        </div>

                        <form method="POST" action="{{ route('orders.payment', $order) }}" class="mt-6 border-t border-ink-100 pt-6">
                            @csrf
                            <label class="label" for="tx_hash">Transaction hash</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input id="tx_hash" name="tx_hash" class="input flex-1 font-mono text-sm"
                                       placeholder="Paste the hash from your wallet" required minlength="10">
                                <button class="btn-primary flex-none">I've sent the payment</button>
                            </div>
                            <p class="hint">We verify it on-chain automatically — usually within a couple of minutes.</p>
                        </form>
                    </div>
                </div>
            @elseif ($payment && $payment->status === 'submitted')
                <div class="card p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-amber-50 ring-1 ring-inset ring-amber-200">
                            <svg class="h-5 w-5 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-ink-900">Verifying your payment</h2>
                            <p class="mt-1 text-sm text-ink-500">
                                We're checking the TRON network for your transaction. This page updates automatically once confirmed —
                                your cloud phone is provisioned right after.
                            </p>
                            <p class="mt-3 break-all font-mono text-xs text-ink-500">{{ $payment->tx_hash }}</p>
                        </div>
                    </div>
                </div>
            @elseif ($payment && $payment->status === 'expired')
                <div class="card border-l-4 border-l-red-400 p-6">
                    <h2 class="text-base font-semibold text-ink-900">Payment window expired</h2>
                    <p class="mt-1 text-sm text-ink-500">This quote is no longer valid. Place a new order to get a fresh one.</p>
                    <a href="{{ route('plans.index') }}" class="btn-primary mt-5">Browse plans</a>
                </div>
            @endif

            @if ($order->status === 'failed')
                <div class="card border-l-4 border-l-red-400 p-6">
                    <h2 class="text-base font-semibold text-ink-900">Provisioning problem</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        Your payment went through, but the device couldn't be created. Our team has been notified — please contact support.
                    </p>
                    @if ($email = \App\Models\Setting::get('support_email'))
                        <a href="mailto:{{ $email }}?subject=Order {{ $order->reference }}" class="btn-secondary mt-5">Contact support</a>
                    @endif
                </div>
            @endif

            {{-- Devices --}}
            @if ($order->cloudInstances->isNotEmpty())
                <div class="card p-6">
                    <h2 class="text-base font-semibold text-ink-900">Your devices from this order</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($order->cloudInstances as $instance)
                            <li class="flex items-center gap-3 rounded-xl bg-ink-50 p-3">
                                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white ring-1 ring-ink-200">
                                    <svg class="h-4 w-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="6" y="2" width="12" height="20" rx="2.5" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-xs font-medium text-ink-900">{{ $instance->pad_code ?? 'Provisioning…' }}</p>
                                    <p class="text-xs text-ink-500">{{ $instance->statusLabel() }}</p>
                                </div>
                                <a href="{{ route('instances.index') }}" class="btn-secondary btn-sm">Manage</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Summary --}}
        <div class="lg:col-span-1">
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-ink-900">Summary</h2>
                    <x-order-status :status="$order->status" />
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Plan</dt>
                        <dd class="text-right font-medium text-ink-900">{{ $order->sku?->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Duration</dt>
                        <dd class="font-medium text-ink-900">{{ $order->sku?->duration_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Quantity</dt>
                        <dd class="font-medium text-ink-900">{{ $order->quantity }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-ink-100 pt-3">
                        <dt class="font-medium text-ink-900">Total</dt>
                        <dd class="text-lg font-extrabold text-ink-900">${{ number_format($order->total_price, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
