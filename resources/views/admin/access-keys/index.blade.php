<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Access codes" subtitle="Codes the desktop app asks for before it will open the site. The website itself stays unlocked in a browser." />
    </x-slot>

    <div class="card mb-6 p-6">
        <h2 class="text-base font-semibold text-ink-900">Generate a new code</h2>
        <p class="mt-1 text-sm text-ink-500">Give this code to whoever should be able to unlock the desktop app.</p>
        <form method="POST" action="{{ route('admin.access-keys.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1" style="min-width: 200px">
                <label class="label" for="label">Label (optional)</label>
                <input type="text" id="label" name="label" class="input" placeholder="e.g. Jane's laptop">
            </div>
            <div class="flex-1" style="min-width: 200px">
                <label class="label" for="expires_at">Expires (optional)</label>
                <input type="datetime-local" id="expires_at" name="expires_at" class="input">
            </div>
            <button class="btn-primary">Generate code</button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-ink-50 text-left text-xs font-semibold uppercase tracking-wider text-ink-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Uses</th>
                    <th class="px-4 py-3">Last used</th>
                    <th class="px-4 py-3">Expires</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                @forelse ($keys as $key)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $key->code }}</td>
                        <td class="px-4 py-3 text-ink-600">{{ $key->label ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if (! $key->is_active)
                                <span class="badge-red">Revoked</span>
                            @elseif ($key->expires_at && $key->expires_at->isPast())
                                <span class="badge-amber">Expired</span>
                            @else
                                <span class="badge-green">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-600">{{ $key->used_count }}</td>
                        <td class="px-4 py-3 text-ink-500">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-ink-500">{{ $key->expires_at?->format('M j, Y H:i') ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.access-keys.toggle', $key) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button class="btn-ghost btn-sm">{{ $key->is_active ? 'Revoke' : 'Re-enable' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.access-keys.destroy', $key) }}" class="inline"
                                  onsubmit="return confirm('Delete this access code? Anyone still using it will be locked out immediately.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-ink-500">No access codes yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
