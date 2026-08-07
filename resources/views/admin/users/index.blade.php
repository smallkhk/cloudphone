<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Users" subtitle="Manage customer accounts and administrators.">
            <x-slot name="actions">
                <button x-data @click="$dispatch('open-new-user')" class="btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    New user
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    {{-- Create user --}}
    <div x-data="{ open: false }" @open-new-user.window="open = true" x-show="open" x-cloak class="mb-6">
        <form method="POST" action="{{ route('admin.users.store') }}" class="card p-6">
            @csrf
            <h2 class="text-base font-semibold text-ink-900">Create a user</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="new_name">Name</label>
                    <input id="new_name" name="name" class="input" required value="{{ old('name') }}">
                </div>
                <div>
                    <label class="label" for="new_email">Email</label>
                    <input id="new_email" name="email" type="email" class="input" required value="{{ old('email') }}">
                </div>
                <div>
                    <label class="label" for="new_password">Password</label>
                    <input id="new_password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-700">
                        <input type="checkbox" name="is_admin" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        Make administrator
                    </label>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2 border-t border-ink-100 pt-5">
                <button type="button" @click="open = false" class="btn-ghost">Cancel</button>
                <button class="btn-primary">Create user</button>
            </div>
        </form>
    </div>

    <div class="card">
        <form method="GET" class="flex gap-2 p-5">
            <input name="q" value="{{ $search }}" class="input max-w-sm" placeholder="Search name or email…">
            <button class="btn-secondary">Search</button>
        </form>

        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Orders</th><th>Devices</th><th>Joined</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink-900">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-ink-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($user->is_admin)
                                    <span class="badge-blue">Admin</span>
                                @else
                                    <span class="badge-gray">Customer</span>
                                @endif
                            </td>
                            <td class="text-sm text-ink-600">{{ $user->orders_count }}</td>
                            <td class="text-sm text-ink-600">{{ $user->cloud_instances_count }}</td>
                            <td class="text-sm text-ink-500">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn-secondary btn-sm">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-14 text-center text-sm text-ink-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">{{ $users->links() }}</div>
        @endif
    </div>
</x-admin-layout>
