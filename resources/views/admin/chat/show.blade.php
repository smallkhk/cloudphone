<x-admin-layout>
    <x-slot name="header">
        <x-page-header :title="$conversation->titleOrExcerpt()"
                       :subtitle="'Started '.$conversation->created_at->format('j M Y, H:i')">
            <x-slot name="actions">
                <a href="{{ route('admin.chat.index') }}" class="btn-secondary">Back to transcripts</a>
                <form method="POST" action="{{ route('admin.chat.destroy', $conversation) }}">
                    @csrf @method('DELETE')
                    <button class="btn-danger" onclick="return confirm('Delete this conversation permanently?')">Delete</button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            {{-- Who's answering --}}
            <div class="card flex flex-wrap items-center gap-3 p-4">
                @if ($conversation->human_handling)
                    <span class="badge-green">You're handling this</span>
                    <p class="flex-1 text-xs text-ink-500">
                        The assistant won't reply while this is on{{ $conversation->agent ? ' · taken over by '.$conversation->agent->name : '' }}.
                    </p>
                    <form method="POST" action="{{ route('admin.chat.handling', $conversation) }}">
                        @csrf
                        <input type="hidden" name="human_handling" value="0">
                        <button class="btn-secondary btn-sm">Hand back to the assistant</button>
                    </form>
                @else
                    <span class="badge-blue">Assistant is answering</span>
                    <p class="flex-1 text-xs text-ink-500">Take over to reply yourself and pause the assistant.</p>
                    <form method="POST" action="{{ route('admin.chat.handling', $conversation) }}">
                        @csrf
                        <input type="hidden" name="human_handling" value="1">
                        <button class="btn-primary btn-sm">Take over</button>
                    </form>
                @endif
            </div>

            <div class="card space-y-4 p-5">
                @foreach ($messages as $message)
                    <div class="flex" @class(['justify-end' => $message->role === 'user'])>
                        <div @class([
                            'max-w-[80%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-relaxed',
                            'rounded-br-sm bg-brand-600 text-white' => $message->role === 'user',
                            'rounded-tl-sm bg-emerald-50 text-ink-800 ring-1 ring-inset ring-emerald-600/15' => $message->role === 'agent',
                            'rounded-tl-sm bg-ink-100 text-ink-800' => $message->role === 'assistant',
                        ])>
                            {{ $message->content }}
                            <span @class([
                                'mt-1.5 block text-[11px]',
                                'text-brand-100' => $message->role === 'user',
                                'text-emerald-700' => $message->role === 'agent',
                                'text-ink-400' => $message->role === 'assistant',
                            ])>
                                {{ ['user' => 'Visitor', 'agent' => 'You', 'assistant' => 'Assistant'][$message->role] ?? $message->role }}
                                · {{ $message->created_at->format('H:i') }}
                            </span>
                        </div>
                    </div>

                    @if ($message->error)
                        <p class="rounded-xl bg-red-50 px-4 py-2.5 text-xs text-red-700 ring-1 ring-inset ring-red-600/15">
                            This message failed: {{ $message->error }}
                        </p>
                    @endif
                @endforeach
            </div>

            {{-- Reply as a human --}}
            <form method="POST" action="{{ route('admin.chat.reply', $conversation) }}" class="card p-4">
                @csrf
                <label class="label" for="message">Reply to this customer</label>
                <textarea id="message" name="message" rows="3" required maxlength="4000" class="input"
                          placeholder="Type your reply…">{{ old('message') }}</textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <p class="flex-1 text-xs text-ink-500">
                        Appears in their chat window within a few seconds, labelled <strong>Support team</strong>.
                        Sending also takes the conversation over from the assistant.
                    </p>
                    <button class="btn-primary btn-sm">Send reply</button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-ink-900">Visitor</h2>
                @if ($conversation->user)
                    <p class="mt-2 text-sm text-ink-700">{{ $conversation->user->name }}</p>
                    <p class="text-xs text-ink-500">{{ $conversation->user->email }}</p>
                    <a href="{{ route('admin.users.show', $conversation->user) }}" class="btn-secondary btn-sm mt-3">Open customer</a>
                @else
                    <p class="mt-2 text-sm text-ink-500">Not signed in — this was a guest on the public site.</p>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="text-sm font-semibold text-ink-900">Usage</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink-500">Messages</dt>
                        <dd class="font-medium text-ink-900">{{ $messages->count() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">Input tokens</dt>
                        <dd class="font-medium text-ink-900">{{ number_format($conversation->input_tokens) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">Output tokens</dt>
                        <dd class="font-medium text-ink-900">{{ number_format($conversation->output_tokens) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">Last active</dt>
                        <dd class="font-medium text-ink-900">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="text-sm font-semibold text-ink-900">Answered badly?</h2>
                <p class="mt-2 text-xs leading-relaxed text-ink-500">
                    If the assistant got something wrong here, add the correct fact to
                    <strong>What else should it know?</strong> in the assistant settings — it applies to every
                    conversation from then on.
                </p>
                <a href="{{ route('admin.settings.edit', 'assistant') }}" class="btn-secondary btn-sm mt-3">Edit its knowledge</a>
            </div>
        </div>
    </div>
</x-admin-layout>
