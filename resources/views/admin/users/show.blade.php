<x-admin-layout>
    <x-slot name="header">
        <x-page-header :title="$user->name" :subtitle="$user->email">
            <x-slot name="actions">
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">&larr; All users</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Account details --}}
        <div class="lg:col-span-1">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card p-6">
                @csrf @method('PATCH')
                <h2 class="text-base font-semibold text-ink-900">Account</h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label" for="name">Name</label>
                        <input id="name" name="name" class="input" required value="{{ old('name', $user->name) }}">
                    </div>
                    <div>
                        <label class="label" for="email">Email</label>
                        <input id="email" name="email" type="email" class="input" required value="{{ old('email', $user->email) }}">
                    </div>
                    <div>
                        <label class="label" for="password">New password</label>
                        <input id="password" name="password" type="password" class="input" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current">
                    </div>
                    <label class="flex items-center gap-2.5 text-sm text-ink-700">
                        <input type="checkbox" name="is_admin" value="1" @checked($user->is_admin)
                               class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        Administrator
                    </label>
                </div>

                <div class="mt-6 border-t border-ink-100 pt-5">
                    <button class="btn-primary w-full">Save changes</button>
                </div>
            </form>

            <div class="card mt-4 p-6">
                <h2 class="text-base font-semibold text-ink-900">Summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Joined</dt><dd class="font-medium text-ink-900">{{ $user->created_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Orders</dt><dd class="font-medium text-ink-900">{{ $user->orders->count() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Devices</dt><dd class="font-medium text-ink-900">{{ $user->cloudInstances->count() }}</dd></div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">Lifetime spend</dt>
                        <dd class="font-medium text-ink-900">${{ number_format($user->orders->where('status', 'completed')->sum('total_price'), 2) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card mt-4 p-6">
                <h2 class="text-base font-semibold text-ink-900">Wallet balance</h2>
                <p class="mt-1 text-2xl font-extrabold text-ink-900">${{ number_format($user->balance, 2) }}</p>

                <form method="POST" action="{{ route('admin.users.adjust-balance', $user) }}" class="mt-4 space-y-2" x-data="{ direction: 'credit' }">
                    @csrf
                    <div class="flex gap-2">
                        <select name="direction" x-model="direction" class="input text-sm">
                            <option value="credit">Credit</option>
                            <option value="debit">Debit</option>
                        </select>
                        <input name="amount" type="number" step="0.01" min="0.01" class="input text-sm" placeholder="Amount" required>
                    </div>
                    <input name="note" class="input text-sm" placeholder="Reason (shown in their balance history)" required maxlength="255">
                    <button class="btn-secondary btn-sm w-full">Apply adjustment</button>
                </form>

                @if ($user->walletTransactions->isNotEmpty())
                    <div class="mt-4 space-y-2 border-t border-ink-100 pt-4">
                        @foreach ($user->walletTransactions->take(5) as $tx)
                            <div class="flex justify-between text-xs">
                                <span class="text-ink-500">{{ $tx->description }}</span>
                                <span class="font-medium {{ $tx->amount >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="card mt-4 border-l-4 border-l-red-400 p-6"
                  onsubmit="return confirm('Delete this user? Their devices return to stock. This cannot be undone.')">
                @csrf @method('DELETE')
                <h2 class="text-base font-semibold text-ink-900">Delete account</h2>
                <p class="mt-1 text-xs text-ink-500">Their cloud phones are returned to stock, not destroyed.</p>
                <button class="btn-danger btn-sm mt-4">Delete user</button>
            </form>
        </div>

        {{-- Devices + orders --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="flex items-center justify-between px-5 py-4">
                    <h2 class="text-base font-semibold text-ink-900">Allocated devices</h2>
                    <span class="badge-gray">{{ $user->cloudInstances->count() }}</span>
                </div>

                <div class="table-wrap border-t border-ink-100">
                    <table class="table">
                        <thead><tr><th>Device</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($user->cloudInstances as $device)
                                <tr>
                                    <td>
                                        <p class="font-mono text-xs font-medium text-ink-900">{{ $device->pad_code ?? 'Provisioning…' }}</p>
                                        <p class="text-xs text-ink-500">{{ $device->nickname ?? $device->sku?->name ?? '—' }}</p>
                                    </td>
                                    <td>
                                        <span class="{{ $device->online ? 'badge-green' : 'badge-gray' }}">{{ $device->statusLabel() }}</span>
                                    </td>
                                    <td class="text-sm {{ $device->isExpired() ? 'text-red-600 font-medium' : 'text-ink-600' }}">
                                        {{ $device->expires_at?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.devices.deallocate', $device) }}"
                                              onsubmit="return confirm('Return this device to stock?')">
                                            @csrf
                                            <button class="btn-ghost btn-sm text-ink-500">Unassign</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-10 text-center text-sm text-ink-500">No devices allocated.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Allocate from stock --}}
                <div class="border-t border-ink-100 p-5">
                    @if ($availableInstances->isEmpty())
                        <p class="text-xs text-ink-500">
                            No devices in stock to allocate.
                            <a href="{{ route('admin.devices.index') }}" class="font-medium text-brand-600 hover:underline">Import from VMOS</a>
                            to add some.
                        </p>
                    @else
                        <p class="mb-3 text-sm font-medium text-ink-900">Allocate a device from stock</p>
                        <div class="flex flex-col gap-2 sm:flex-row" x-data="{ device: '' }">
                            <select x-model="device" class="input flex-1 text-sm">
                                <option value="">Select a device…</option>
                                @foreach ($availableInstances as $available)
                                    <option value="{{ $available->id }}">
                                        {{ $available->pad_code ?? "Device #{$available->id}" }}
                                        @if ($available->nickname) — {{ $available->nickname }} @endif
                                    </option>
                                @endforeach
                            </select>
                            @foreach ($availableInstances as $available)
                                <form x-show="device === '{{ $available->id }}'" x-cloak method="POST"
                                      action="{{ route('admin.devices.allocate', $available) }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                    <button class="btn-primary w-full sm:w-auto">Allocate to {{ $user->name }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between px-5 py-4">
                    <h2 class="text-base font-semibold text-ink-900">Orders</h2>
                    <span class="badge-gray">{{ $user->orders->count() }}</span>
                </div>
                <div class="table-wrap border-t border-ink-100">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Plan</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse ($user->orders as $order)
                                <tr>
                                    <td><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-600 hover:underline">{{ $order->reference }}</a></td>
                                    <td class="text-sm text-ink-600">{{ $order->sku?->name ?? '—' }}</td>
                                    <td class="text-sm font-medium">${{ number_format($order->total_price, 2) }}</td>
                                    <td><x-order-status :status="$order->status" /></td>
                                    <td class="text-sm text-ink-500">{{ $order->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-10 text-center text-sm text-ink-500">No orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
