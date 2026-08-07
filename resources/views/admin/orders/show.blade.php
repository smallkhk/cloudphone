<x-admin-layout>
    <x-slot name="header">
        <x-page-header :title="'Order ' . $order->reference" :subtitle="$order->created_at->format('d M Y, H:i')">
            <x-slot name="actions">
                <a href="{{ route('admin.orders.index') }}" class="btn-ghost">&larr; All orders</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-ink-900">Order details</h2>
                    <x-order-status :status="$order->status" />
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase tracking-wider text-ink-400">Customer</dt>
                        <dd class="mt-1 text-sm font-medium text-ink-900">
                            @if ($order->user)
                                <a href="{{ route('admin.users.show', $order->user) }}" class="text-brand-600 hover:underline">{{ $order->user->email }}</a>
                            @else — @endif
                        </dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-ink-400">Plan</dt>
                        <dd class="mt-1 text-sm font-medium text-ink-900">{{ $order->sku?->name ?? '—' }} &times;{{ $order->quantity }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-ink-400">Total</dt>
                        <dd class="mt-1 text-sm font-medium text-ink-900">${{ number_format($order->total_price, 2) }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-ink-400">Cost / margin</dt>
                        <dd class="mt-1 text-sm text-ink-600">
                            ${{ number_format($order->sku?->vmos_cost_price ?? 0, 2) }} →
                            <span class="font-medium text-emerald-600">
                                ${{ number_format($order->total_price - (($order->sku?->vmos_cost_price ?? 0) * $order->quantity), 2) }}
                            </span>
                        </dd></div>
                    @if ($order->vmos_order_id)
                        <div><dt class="text-xs uppercase tracking-wider text-ink-400">VMOS order</dt>
                            <dd class="mt-1 font-mono text-xs text-ink-700">{{ $order->vmos_order_id }}</dd></div>
                    @endif
                    @if ($order->error_message)
                        <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wider text-ink-400">Error</dt>
                            <dd class="mt-1 rounded-lg bg-red-50 p-3 text-xs text-red-700">{{ $order->error_message }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card">
                <h2 class="px-6 py-4 text-base font-semibold text-ink-900">Payments</h2>
                <div class="table-wrap border-t border-ink-100">
                    <table class="table">
                        <thead><tr><th>Amount</th><th>Network</th><th>Transaction</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse ($order->payments as $payment)
                                <tr>
                                    <td class="text-sm font-medium">{{ number_format($payment->amount_crypto, 2) }} {{ $payment->currency }}</td>
                                    <td class="text-sm text-ink-600">{{ $payment->network }}</td>
                                    <td class="max-w-[14rem] truncate font-mono text-xs text-ink-600">{{ $payment->tx_hash ?? '—' }}</td>
                                    <td>
                                        <span class="{{ match ($payment->status) {
                                            'confirmed' => 'badge-green',
                                            'submitted' => 'badge-amber',
                                            'expired', 'failed' => 'badge-red',
                                            default => 'badge-gray',
                                        } }}">{{ str($payment->status)->headline() }}</span>
                                    </td>
                                    <td class="text-sm text-ink-500">{{ $payment->created_at->format('d M H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-10 text-center text-sm text-ink-500">No payment recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($order->cloudInstances->isNotEmpty())
                <div class="card">
                    <h2 class="px-6 py-4 text-base font-semibold text-ink-900">Provisioned devices</h2>
                    <div class="table-wrap border-t border-ink-100">
                        <table class="table">
                            <thead><tr><th>Pad code</th><th>Status</th><th>Expires</th></tr></thead>
                            <tbody>
                                @foreach ($order->cloudInstances as $device)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $device->pad_code ?? 'Provisioning…' }}</td>
                                        <td><span class="{{ $device->online ? 'badge-green' : 'badge-gray' }}">{{ $device->statusLabel() }}</span></td>
                                        <td class="text-sm text-ink-600">{{ $device->expires_at?->format('d M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="lg:col-span-1">
            <div class="card p-6">
                <h2 class="text-base font-semibold text-ink-900">Actions</h2>
                <div class="mt-5 space-y-3">
                    @if ($order->status === 'pending_payment')
                        <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}"
                              onsubmit="return confirm('Mark this order as paid without an on-chain match?')">
                            @csrf
                            <button class="btn-secondary w-full">Mark as paid manually</button>
                        </form>
                        <p class="text-xs text-ink-500">Use when a customer paid outside the normal flow.</p>
                    @endif

                    @if (in_array($order->status, ['paid', 'failed'], true))
                        <form method="POST" action="{{ route('admin.orders.provision', $order) }}">
                            @csrf
                            <button class="btn-primary w-full">
                                {{ $order->status === 'failed' ? 'Retry provisioning' : 'Provision now' }}
                            </button>
                        </form>
                        <p class="text-xs text-ink-500">Buys the cloud phone from VMOS for this order.</p>
                    @endif

                    @unless (in_array($order->status, ['completed', 'canceled'], true))
                        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}"
                              onsubmit="return confirm('Cancel this order?')">
                            @csrf
                            <button class="btn-ghost w-full text-red-600 hover:bg-red-50">Cancel order</button>
                        </form>
                    @endunless

                    @if ($order->status === 'completed')
                        <p class="text-sm text-ink-500">This order is complete — nothing further to do.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
