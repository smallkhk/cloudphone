@php
    // Rendered on every page, but only mounts when the owner has switched the
    // assistant on and stored an API key.
    $assistantEnabled = (bool) config('assistant.enabled') && filled(config('assistant.api_key'));
@endphp

@if ($assistantEnabled)
<div x-data="chatWidget()" x-cloak class="fixed bottom-5 right-5 z-[60] print:hidden">

    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="mb-3 flex h-[min(34rem,calc(100vh-7rem))] w-[min(23rem,calc(100vw-2.5rem))] origin-bottom-right flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-ink-900/10">

        {{-- Header --}}
        <div class="flex items-center gap-3 bg-ink-950 px-4 py-3.5 text-white">
            <span class="relative flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5M21 12a8 8 0 01-8 8H7l-4 3v-5.2A8 8 0 1121 12z" />
                </svg>
                <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-ink-950 bg-emerald-400"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ \App\Models\Setting::get('site_name', config('app.name')) }} support</p>
                <p class="text-xs text-ink-400">AI assistant · replies instantly</p>
            </div>
            <button @click="reset()" class="rounded-lg p-1.5 text-ink-400 transition-colors hover:bg-white/10 hover:text-white" title="Start a new chat" aria-label="Start a new chat">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006 5.3M4 15a8 8 0 0014 3.7" />
                </svg>
            </button>
            <button @click="open = false" class="rounded-lg p-1.5 text-ink-400 transition-colors hover:bg-white/10 hover:text-white" aria-label="Close chat">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Transcript --}}
        <div x-ref="scroll" class="flex-1 space-y-3 overflow-y-auto bg-ink-50 px-4 py-4">
            <div class="flex gap-2">
                <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3.5 py-2.5 text-sm leading-relaxed text-ink-700 shadow-sm ring-1 ring-ink-900/5">
                    <span x-text="greeting"></span>
                </div>
            </div>

            <template x-for="(m, i) in messages" :key="i">
                <div class="flex" :class="m.role === 'user' ? 'justify-end' : ''">
                    <div class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm"
                         :class="m.role === 'user'
                            ? 'rounded-br-sm bg-brand-600 text-white'
                            : 'rounded-tl-sm bg-white text-ink-700 ring-1 ring-ink-900/5'"
                         x-text="m.content"></div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="sending" class="flex">
                <div class="flex items-center gap-1 rounded-2xl rounded-tl-sm bg-white px-4 py-3 shadow-sm ring-1 ring-ink-900/5">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-400" style="animation-delay:0ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-400" style="animation-delay:150ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-400" style="animation-delay:300ms"></span>
                </div>
            </div>

            <p x-show="error" x-text="error" class="rounded-xl bg-red-50 px-3.5 py-2.5 text-xs text-red-700 ring-1 ring-inset ring-red-600/15"></p>
        </div>

        {{-- Suggested questions, shown until the visitor says something --}}
        <div x-show="messages.length === 0" class="flex flex-wrap gap-1.5 border-t border-ink-100 bg-white px-3 pt-3">
            <template x-for="s in suggestions" :key="s">
                <button @click="send(s)"
                        class="rounded-full border border-ink-200 px-3 py-1.5 text-xs text-ink-600 transition-colors hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                        x-text="s"></button>
            </template>
        </div>

        {{-- Composer --}}
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-ink-100 bg-white p-3">
            <textarea x-model="draft" x-ref="input" rows="1" maxlength="2000"
                      @keydown.enter.prevent="$event.shiftKey ? (draft += '\n') : send()"
                      @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                      placeholder="Ask a question…"
                      class="max-h-[7.5rem] flex-1 resize-none rounded-xl border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500"></textarea>
            <button type="submit" :disabled="sending || !draft.trim()"
                    class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-brand-600 text-white transition-colors hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="Send message">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </button>
        </form>

        <p class="bg-white px-4 pb-3 text-center text-[11px] leading-tight text-ink-400">
            AI assistant — it can make mistakes. Never share passwords or wallet keys.
        </p>
    </div>

    {{-- Launcher --}}
    <button @click="toggle()"
            class="ml-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-xl shadow-brand-600/30 transition-all duration-200 hover:scale-105 hover:bg-brand-700"
            :aria-label="open ? 'Close chat' : 'Open chat'">
        <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5M21 12a8 8 0 01-8 8H7l-4 3v-5.2A8 8 0 1121 12z" />
        </svg>
        <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<script>
    function chatWidget() {
        return {
            open: false,
            loaded: false,
            sending: false,
            draft: '',
            error: '',
            messages: [],
            greeting: @json(config('assistant.greeting')),
            suggestions: [
                'What plans do you have?',
                'How do I pay with USDT?',
                'Can I change the phone number?',
            ],

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.load();
                    this.$nextTick(() => this.$refs.input?.focus());
                }
            },

            async load() {
                if (this.loaded) return;
                this.loaded = true;
                try {
                    const res = await fetch(@json(route('chat.history')), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.messages = data.messages || [];
                    if (data.greeting) this.greeting = data.greeting;
                    this.scroll();
                } catch (e) {
                    // A failed history fetch shouldn't stop them asking a question.
                }
            },

            async send(text) {
                const body = (text || this.draft).trim();
                if (!body || this.sending) return;

                this.error = '';
                this.messages.push({ role: 'user', content: body });
                this.draft = '';
                this.sending = true;
                this.$nextTick(() => { if (this.$refs.input) this.$refs.input.style.height = 'auto'; });
                this.scroll();

                try {
                    const res = await fetch(@json(route('chat.store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ message: body }),
                    });
                    const data = await res.json();

                    if (!res.ok) {
                        this.error = data.error || data.message || 'Something went wrong. Please try again.';
                    } else {
                        this.messages.push({ role: 'assistant', content: data.reply });
                    }
                } catch (e) {
                    this.error = 'Connection lost. Check your internet and try again.';
                } finally {
                    this.sending = false;
                    this.scroll();
                }
            },

            async reset() {
                this.messages = [];
                this.error = '';
                try {
                    await fetch(@json(route('chat.reset')), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    });
                } catch (e) {
                    // Local transcript is cleared either way.
                }
            },

            scroll() {
                this.$nextTick(() => {
                    const el = this.$refs.scroll;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
        };
    }
</script>
@endif
