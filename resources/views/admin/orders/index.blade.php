<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Orders" subtitle="Every purchase, its payment state, and provisioning result." />
    </x-slot>

    <div class="card">
        <form method="GET" class="flex flex-wrap gap-2 p-5">
            <input name="q" value="{{ $search }}" class="input max-w-xs flex-1" placeholder="Search reference or email…">
            <select name="status" class="input max-w-[12rem]" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'provisioning' => 'Provisioning', 'completed' => 'Completed', 'failed' => 'Failed', 'canceled' => 'Canceled'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Filter</button>
        </form>

        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr><th>Reference</th><th>Customer</th><th>Plan</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="font-mono text-xs font-medium text-ink-900">{{ $order->reference }}</td>
                            <td class="text-sm text-ink-600">{{ $order->user?->email ?? '—' }}</td>
                            <td class="text-sm text-ink-600">{{ $order->sku?->name ?? '—' }} &times;{{ $order->quantity }}</td>
                            <td class="text-sm font-medium">${{ number_format($order->total_price, 2) }}</td>
                            <td><x-order-status :status="$order->status" /></td>
                            <td class="text-sm text-ink-500">{{ $order->created_at->format('d M Y') }}</td>
                            <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn-secondary btn-sm">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-14 text-center text-sm text-ink-500">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-admin-layout>
