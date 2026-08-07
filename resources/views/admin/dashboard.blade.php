<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Dashboard" :subtitle="'Overview of ' . config('app.name')">
            <x-slot name="actions">
                <a href="{{ route('admin.settings.edit') }}" class="btn-secondary">Settings</a>
                <a href="{{ route('admin.skus.index') }}" class="btn-primary">Manage plans</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    {{-- Setup checklist — only while something is still missing --}}
    @unless (collect($setupComplete)->every(fn ($done) => $done))
        <div class="card mb-8 border-l-4 border-l-amber-400 p-6">
            <h2 class="text-base font-semibold text-ink-900">Finish setting up your store</h2>
            <p class="mt-1 text-sm text-ink-500">These steps need to be done before customers can buy and receive cloud phones.</p>

            <ul class="mt-5 space-y-3">
                @php
                    $steps = [
                        'vmos' => ['Connect your VMOS account', 'Add your Access Key and Secret Key so orders can be provisioned.', route('admin.settings.edit', 'vmos')],
                        'crypto' => ['Add your USDT wallet', 'Set the TRC20 address customers pay to.', route('admin.settings.edit', 'payments')],
                        'skus' => ['Publish some plans', 'Sync the catalogue from VMOS and set your prices.', route('admin.skus.index')],
                        'mail' => ['Configure email (optional)', 'Needed for password resets and notifications.', route('admin.settings.edit', 'mail')],
                    ];
                @endphp

                @foreach ($steps as $key => [$title, $body, $url])
                    <li class="flex items-start gap-3">
                        @if ($setupComplete[$key])
                            <svg class="mt-0.5 h-5 w-5 flex-none text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            <span class="mt-1 h-3.5 w-3.5 flex-none rounded-full border-2 border-ink-300"></span>
                        @endif
                        <div class="flex-1">
                            <p class="text-sm font-medium {{ $setupComplete[$key] ? 'text-ink-400 line-through' : 'text-ink-900' }}">{{ $title }}</p>
                            @unless ($setupComplete[$key])
                                <p class="text-xs text-ink-500">{{ $body }}</p>
                            @endunless
                        </div>
                        @unless ($setupComplete[$key])
                            <a href="{{ $url }}" class="btn-secondary btn-sm">Set up</a>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endunless

    {{-- Stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Revenue (all time)" :value="'$' . number_format($stats['revenue_total'], 2)"
                     :sub="'$' . number_format($stats['revenue_month'], 2) . ' this month'" tone="green"
                     icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.1 0 2.1.4 2.6 1M12 8V6m0 10v2m0-2c-1.1 0-2.1-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-stat-card label="Orders" :value="number_format($stats['orders_total'])"
                     :sub="$stats['orders_pending'] . ' awaiting payment'" tone="brand"
                     icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />

        <x-stat-card label="Cloud phones" :value="number_format($stats['devices_total'])"
                     :sub="$stats['devices_online'] . ' online · ' . $stats['devices_stock'] . ' in stock'" tone="brand"
                     icon="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />

        <x-stat-card label="Customers" :value="number_format($stats['users_total'])"
                     :sub="'+' . $stats['users_month'] . ' this month'" tone="brand"
                     icon="M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
    </div>

    {{-- Attention needed --}}
    @if ($stats['payments_awaiting'] || $stats['orders_failed'] || $stats['devices_expiring'])
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @if ($stats['payments_awaiting'])
                <a href="{{ route('admin.orders.index', ['status' => 'pending_payment']) }}" class="card p-5 transition hover:shadow-lg">
                    <p class="text-sm font-medium text-amber-700">Payments to verify</p>
                    <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $stats['payments_awaiting'] }}</p>
                    <p class="mt-1 text-xs text-ink-500">Customers submitted a transaction hash</p>
                </a>
            @endif
            @if ($stats['orders_failed'])
                <a href="{{ route('admin.orders.index', ['status' => 'failed']) }}" class="card p-5 transition hover:shadow-lg">
                    <p class="text-sm font-medium text-red-700">Failed orders</p>
                    <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $stats['orders_failed'] }}</p>
                    <p class="mt-1 text-xs text-ink-500">Paid but provisioning didn't complete</p>
                </a>
            @endif
            @if ($stats['devices_expiring'])
                <a href="{{ route('admin.devices.index') }}" class="card p-5 transition hover:shadow-lg">
                    <p class="text-sm font-medium text-amber-700">Expiring within 7 days</p>
                    <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $stats['devices_expiring'] }}</p>
                    <p class="mt-1 text-xs text-ink-500">Devices due for renewal</p>
                </a>
            @endif
        </div>
    @endif

    {{-- Recent activity --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="text-base font-semibold text-ink-900">Recent orders</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
            </div>
            <div class="table-wrap border-t border-ink-100">
                <table class="table">
                    <thead>
                        <tr><th>Order</th><th>Customer</th><th>Plan</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-600 hover:underline">{{ $order->reference }}</a></td>
                                <td class="text-ink-600">{{ $order->user?->email ?? '—' }}</td>
                                <td class="text-ink-600">{{ $order->sku?->name ?? '—' }}</td>
                                <td class="font-medium">${{ number_format($order->total_price, 2) }}</td>
                                <td><x-order-status :status="$order->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-ink-500">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="text-base font-semibold text-ink-900">New customers</h2>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
            </div>
            <ul class="divide-y divide-ink-100 border-t border-ink-100">
                @forelse ($recentUsers as $user)
                    <li>
                        <a href="{{ route('admin.users.show', $user) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-ink-50">
                            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink-900">{{ $user->name }}</span>
                                <span class="block truncate text-xs text-ink-500">{{ $user->email }}</span>
                            </span>
                            <span class="flex-none text-xs text-ink-400">{{ $user->created_at->diffForHumans(short: true) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-ink-500">No customers yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-admin-layout>
