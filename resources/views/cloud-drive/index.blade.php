<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cloud Drive" subtitle="Shared storage across your whole account — the same files and space no matter which device you manage them from." />
    </x-slot>

    <div class="mb-6 flex items-start gap-3 rounded-xl bg-ink-50 p-4 text-sm text-ink-600 ring-1 ring-inset ring-ink-200">
        <svg class="h-5 w-5 flex-none text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>
            This is the same Cloud Drive shown on each device's own "Manage → Cloud Drive" tab — files and storage
            here are account-wide, not per device, so anything you add or remove shows up everywhere.
        </span>
    </div>

    @php
        $used = $storage['storageUsedAvail'] ?? null;
        $total = $storage['storageCapacityLimit'] ?? null;
    @endphp

    <div class="card p-6">
        <h3 class="text-base font-semibold text-ink-900">Storage</h3>
        @if ($used !== null && $total !== null)
            <p class="mt-2 text-sm text-ink-600">{{ number_format($used / 1073741824, 2) }} GB / {{ number_format($total / 1073741824, 2) }} GB used</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-100">
                <div class="h-full bg-brand-600" style="width: {{ $total > 0 ? min(100, round($used / $total * 100)) : 0 }}%"></div>
            </div>
        @else
            <p class="mt-2 text-sm text-ink-500">Capacity not available right now.</p>
        @endif

        @if (auth()->user()->is_admin && ! empty($storageGoods))
            <form method="POST" action="{{ route('cloud-drive.buy-storage') }}"
                  class="mt-5 flex flex-wrap items-end gap-3 border-t border-ink-100 pt-5"
                  onsubmit="return confirm('This charges your VMOS balance. Continue?')">
                @csrf
                <div class="flex-1">
                    <label class="label" for="storage_id">Buy more storage (admin)</label>
                    <select id="storage_id" name="storage_id" class="input">
                        @foreach ($storageGoods as $good)
                            <option value="{{ $good['storageId'] ?? '' }}">
                                {{ $good['storageName'] ?? 'Storage package' }}
                                @if (isset($good['payPrice'])) — ${{ number_format($good['payPrice'] / 100, 2) }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="auto_renew" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    Auto-renew
                </label>
                <button class="btn-secondary">Buy</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('cloud-drive.upload') }}" class="card mt-6 p-6">
        @csrf
        <h3 class="text-base font-semibold text-ink-900">Upload a file</h3>
        <p class="mt-1 text-sm text-ink-500">Paste a direct link — it's downloaded and stored on your Cloud Drive.</p>
        <div class="mt-5 space-y-3">
            <div>
                <label class="label" for="drive_url">File URL</label>
                <input id="drive_url" name="url" type="url" class="input" placeholder="https://example.com/file.pdf" required>
            </div>
            <div>
                <label class="label" for="file_name">File name (optional)</label>
                <input id="file_name" name="file_name" class="input" placeholder="document.pdf">
            </div>
        </div>
        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
            <button class="btn-primary">Upload</button>
        </div>
    </form>

    <div class="card mt-6">
        <h3 class="px-5 py-4 text-base font-semibold text-ink-900">Files</h3>
        @php $fileList = collect($files ?? []); @endphp

        @if ($fileList->isEmpty())
            <p class="border-t border-ink-100 px-5 py-8 text-center text-sm text-ink-500">No files yet.</p>
        @else
            <ul class="divide-y divide-ink-100 border-t border-ink-100">
                @foreach ($fileList as $file)
                    @php $fileId = $file['fileId'] ?? null; @endphp
                    @continue(! $fileId)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $file['appName'] ?? $fileId }}</p>
                        </div>
                        <form method="POST" action="{{ route('cloud-drive.delete') }}"
                              onsubmit="return confirm('Delete this file?')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="file_ids[]" value="{{ $fileId }}">
                            <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card mt-6 p-6">
        <h3 class="text-base font-semibold text-ink-900">Backups</h3>
        <p class="mt-1 text-sm text-ink-500">Back up a specific device's entire disk to your Cloud Drive.</p>

        @if ($devices->isEmpty())
            <p class="mt-4 text-sm text-ink-500">You don't have any provisioned devices to back up yet.</p>
        @else
            <form method="POST" action="{{ route('cloud-drive.backup') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="label" for="backup_pad_code">Device</label>
                    <select id="backup_pad_code" name="pad_code" class="input" required>
                        <option value="">Choose a device…</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->pad_code }}">{{ $device->nickname ?: $device->pad_code }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-secondary">Back up now</button>
            </form>
        @endif

        @if ($backupTasks->isEmpty())
            <p class="mt-4 text-sm text-ink-500">No backups yet.</p>
        @else
            <ul class="mt-4 space-y-2">
                @foreach ($backupTasks as $task)
                    <li class="flex items-center justify-between rounded-lg bg-ink-50 p-3 text-sm">
                        <span class="text-ink-600">
                            <span class="font-mono">{{ $task->cloudInstance?->nickname ?: $task->cloudInstance?->pad_code }}</span>
                            — {{ $task->created_at->format('d M H:i') }}
                            @if ($progress = $task->result['progress'] ?? null)
                                — {{ is_array($progress) ? json_encode($progress) : $progress }}
                            @endif
                        </span>
                        <form method="POST" action="{{ route('cloud-drive.backup-progress', $task) }}">
                            @csrf
                            <button class="btn-ghost btn-sm">Check progress</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
