<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My orders" subtitle="Your purchase history and payment status.">
            <x-slot name="actions">
                <a href="{{ route('plans.index') }}" class="btn-primary">Buy a device</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($orders->isEmpty())
        <div class="card p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50">
                <svg class="h-7 w-7 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h2 class="mt-6 text-lg font-semibold text-ink-900">No orders yet</h2>
            <p class="mt-2 text-sm text-ink-500">Your purchases will show up here.</p>
            <a href="{{ route('plans.index') }}" class="btn-primary mt-7">Browse plans</a>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Order</th><th>Plan</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="font-mono text-xs font-medium text-ink-900">{{ $order->reference }}</td>
                                <td class="text-sm text-ink-600">{{ $order->sku?->name }} &times;{{ $order->quantity }}</td>
                                <td class="text-sm font-medium">${{ number_format($order->total_price, 2) }}</td>
                                <td><x-order-status :status="$order->status" /></td>
                                <td class="text-sm text-ink-500">{{ $order->created_at->format('d M Y') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="btn-secondary btn-sm">
                                        {{ $order->status === 'pending_payment' ? 'Pay now' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">{{ $orders->links() }}</div>
            @endif
        </div>
    @endif
</x-app-layout>
