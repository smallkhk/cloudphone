<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Live chat" subtitle="Every conversation the AI assistant has had with a visitor.">
            <x-slot name="actions">
                <a href="{{ route('admin.settings.edit', 'assistant') }}" class="btn-secondary">Assistant settings</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-stat-card label="Conversations" :value="number_format($stats['conversations'])" />
        <x-stat-card label="Messages" :value="number_format($stats['messages'])" />
        <x-stat-card label="Active today" :value="number_format($stats['today'])" tone="green" />
        <x-stat-card label="Tokens used" :value="number_format($stats['tokens'])" tone="amber" />
    </div>

    @if (! config('assistant.enabled') || ! filled(config('assistant.api_key')))
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            <svg class="h-5 w-5 flex-none text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3L13.71 4a2 2 0 00-3.42 0L3.36 16a2 2 0 001.71 3z" />
            </svg>
            <span class="flex-1">Live chat is switched off, so the widget isn't showing on your site.</span>
            <a href="{{ route('admin.settings.edit', 'assistant') }}" class="btn-secondary btn-sm">Turn it on</a>
        </div>
    @endif

    <div class="card mt-6">
        <form method="GET" class="flex gap-2 p-5">
            <input name="q" value="{{ $search }}" class="input max-w-sm" placeholder="Search messages or customer…">
            <button class="btn-secondary">Search</button>
            @if ($search)
                <a href="{{ route('admin.chat.index') }}" class="btn-ghost btn-sm">Clear</a>
            @endif
        </form>

        <div class="table-wrap border-t border-ink-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>Conversation</th>
                        <th>Visitor</th>
                        <th>Messages</th>
                        <th>Last active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversations as $conversation)
                        <tr>
                            <td>
                                <a href="{{ route('admin.chat.show', $conversation) }}" class="text-sm font-medium text-ink-900 hover:text-brand-600">
                                    {{ $conversation->titleOrExcerpt() }}
                                </a>
                            </td>
                            <td class="text-sm text-ink-600">
                                @if ($conversation->user)
                                    <a href="{{ route('admin.users.show', $conversation->user) }}" class="hover:text-brand-600">{{ $conversation->user->email }}</a>
                                @else
                                    <span class="badge-gray">Guest</span>
                                @endif
                            </td>
                            <td class="text-sm text-ink-600">{{ $conversation->messages_count }}</td>
                            <td class="text-sm text-ink-500">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.chat.show', $conversation) }}" class="btn-secondary btn-sm">Read</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-14 text-center">
                                <p class="text-sm text-ink-500">No conversations yet.</p>
                                <p class="mt-1 text-xs text-ink-400">They'll appear here as soon as a visitor uses the chat bubble.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($conversations->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">{{ $conversations->links() }}</div>
        @endif
    </div>
</x-admin-layout>
