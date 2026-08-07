<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Devices" subtitle="Every cloud phone on your VMOS account, and who it belongs to.">
            <x-slot name="actions">
                <form method="POST" action="{{ route('admin.devices.import') }}">
                    @csrf
                    <button class="btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                        </svg>
                        Import from VMOS
                    </button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-stat-card label="Total devices" :value="$stats['total']" />
        <x-stat-card label="Allocated" :value="$stats['allocated']" tone="green" />
        <x-stat-card label="In stock" :value="$stats['unallocated']" tone="amber" />
        <x-stat-card label="Online now" :value="$stats['online']" tone="green" />
    </div>

    <div class="card mt-6">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input name="q" value="{{ $search }}" class="input max-w-xs flex-1" placeholder="Search pad code, nickname…">
                <select name="filter" class="input max-w-[10rem]" onchange="this.form.submit()">
                    <option value="">All devices</option>
                    <option value="unallocated" @selected($filter === 'unallocated')>In stock</option>
                    <option value="allocated" @selected($filter === 'allocated')>Allocated</option>
                    <option value="online" @selected($filter === 'online')>Online</option>
                </select>
                <button class="btn-secondary">Filter</button>
            </form>
        </div>

        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Assigned to</th>
                        <th>Expires</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody x-data="{ open: null }">
                    @forelse ($instances as $device)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-ink-100">
                                        <svg class="h-4 w-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="6" y="2" width="12" height="20" rx="2.5" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs font-medium text-ink-900">{{ $device->pad_code ?? 'Provisioning…' }}</p>
                                        <p class="truncate text-xs text-ink-500">{{ $device->nickname ?? $device->sku?->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $device->online ? 'badge-green' : 'badge-gray' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $device->online ? 'bg-emerald-500' : 'bg-ink-400' }}"></span>
                                    {{ $device->statusLabel() }}
                                </span>
                            </td>
                            <td>
                                @if ($device->user)
                                    <a href="{{ route('admin.users.show', $device->user) }}" class="text-sm font-medium text-brand-600 hover:underline">
                                        {{ $device->user->email }}
                                    </a>
                                    @if ($device->allocated_at)
                                        <p class="text-xs text-ink-400">Allocated {{ $device->allocated_at->diffForHumans() }}</p>
                                    @endif
                                @else
                                    <span class="badge-amber">In stock</span>
                                @endif
                            </td>
                            <td class="text-sm {{ $device->isExpired() ? 'text-red-600 font-medium' : 'text-ink-600' }}">
                                {{ $device->expires_at?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="open = open === {{ $device->id }} ? null : {{ $device->id }}" class="btn-secondary btn-sm">
                                        {{ $device->user ? 'Reassign' : 'Allocate' }}
                                    </button>
                                    @if ($device->user)
                                        <form method="POST" action="{{ route('admin.devices.deallocate', $device) }}"
                                              onsubmit="return confirm('Return this device to stock? The customer will lose access.')">
                                            @csrf
                                            <button class="btn-ghost btn-sm text-ink-500">Unassign</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Allocation panel, full width beneath its row --}}
                        <tr x-show="open === {{ $device->id }}" x-cloak class="!border-0">
                            <td colspan="5" class="!py-0">
                                <div x-collapse x-show="open === {{ $device->id }}">
                                    <form method="POST" action="{{ route('admin.devices.allocate', $device) }}"
                                          class="mb-3 rounded-xl bg-ink-50 p-4 ring-1 ring-inset ring-ink-200">
                                        @csrf
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label class="label text-xs" for="user_{{ $device->id }}">Assign to customer</label>
                                                <select id="user_{{ $device->id }}" name="user_id" class="input text-sm" required>
                                                    <option value="">Select a customer…</option>
                                                    @foreach ($users as $u)
                                                        <option value="{{ $u->id }}" @selected($device->user_id === $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="label text-xs" for="nick_{{ $device->id }}">Nickname (optional)</label>
                                                <input id="nick_{{ $device->id }}" name="nickname" class="input text-sm" value="{{ $device->nickname }}">
                                            </div>
                                            <div>
                                                <label class="label text-xs" for="note_{{ $device->id }}">Internal note</label>
                                                <input id="note_{{ $device->id }}" name="allocation_note" class="input text-sm" value="{{ $device->allocation_note }}">
                                            </div>
                                        </div>
                                        <div class="mt-4 flex justify-end gap-2">
                                            <button type="button" @click="open = null" class="btn-ghost btn-sm">Cancel</button>
                                            <button class="btn-primary btn-sm">Confirm allocation</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-14 text-center">
                                <p class="text-sm text-ink-500">No devices yet.</p>
                                <p class="mt-1 text-xs text-ink-400">Devices appear here after a customer order, or use “Import from VMOS”.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($instances->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">{{ $instances->links() }}</div>
        @endif
    </div>
</x-admin-layout>
