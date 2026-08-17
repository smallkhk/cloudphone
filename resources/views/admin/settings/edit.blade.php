@php
    $tabs = [
        'site' => ['Site', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10'],
        'payments' => ['Payments', 'M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z'],
        'vmos' => ['VMOS API', 'M5 12h14M12 5l7 7-7 7'],
        'mail' => ['Email', 'M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
        'assistant' => ['AI live chat', 'M8 10h8M8 14h5M21 12a8 8 0 01-8 8H7l-4 3v-5.2A8 8 0 1121 12z'],
    ];

    // Secrets are never echoed back; show whether one is stored instead.
    $stored = fn ($key) => filled($settings[$key] ?? null);
@endphp

<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="Settings" subtitle="Configure your store without editing files on the server." />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-4">
        {{-- Tabs --}}
        <nav class="lg:col-span-1">
            <div class="card overflow-hidden p-2">
                @foreach ($tabs as $key => [$label, $icon])
                    <a href="{{ route('admin.settings.edit', $key) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $tab === $key ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100' }}">
                        <svg class="h-5 w-5 flex-none {{ $tab === $key ? 'text-brand-600' : 'text-ink-400' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                        </svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="card mt-4 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Database</p>
                <p class="mt-2 text-xs leading-relaxed text-ink-500">
                    Database credentials stay in the server's <code class="rounded bg-ink-100 px-1 py-0.5">.env</code> file —
                    the app needs them to reach this settings table in the first place, so they can't be edited here.
                </p>
                <p class="mt-3 text-xs text-ink-500">Connected to</p>
                <p class="break-all font-mono text-xs font-medium text-ink-700">
                    {{ basename(config('database.connections.'.config('database.default').'.database')) }}
                </p>
            </div>
        </nav>

        <div class="lg:col-span-3">
            {{-- Site ------------------------------------------------------- --}}
            @if ($tab === 'site')
                <form method="POST" action="{{ route('admin.settings.site') }}" class="card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold text-ink-900">Site details</h2>
                    <p class="mt-1 text-sm text-ink-500">Branding and contact information shown across the public site.</p>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label" for="site_name">Site name</label>
                            <input id="site_name" name="site_name" class="input" required
                                   value="{{ old('site_name', $settings['site_name'] ?? config('app.name')) }}">
                            <p class="hint">Appears in the header, page titles and emails.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label" for="site_tagline">Tagline</label>
                            <textarea id="site_tagline" name="site_tagline" rows="2" class="input">{{ old('site_tagline', $settings['site_tagline'] ?? '') }}</textarea>
                            <p class="hint">One-line description used in the footer and search results.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label" for="site_url">Site URL</label>
                            <input id="site_url" name="site_url" type="url" class="input" placeholder="https://yourdomain.com"
                                   value="{{ old('site_url', $settings['site_url'] ?? config('app.url')) }}">
                            <p class="hint">Used to build links in emails and the VMOS callback URL.</p>
                        </div>

                        <div>
                            <label class="label" for="support_email">Support email</label>
                            <input id="support_email" name="support_email" type="email" class="input"
                                   value="{{ old('support_email', $settings['support_email'] ?? '') }}">
                        </div>

                        <div>
                            <label class="label" for="default_markup_percent">Default markup %</label>
                            <input id="default_markup_percent" name="default_markup_percent" type="number" step="1" min="0" class="input"
                                   value="{{ old('default_markup_percent', $settings['default_markup_percent'] ?? 30) }}">
                            <p class="hint">Applied to newly synced plans.</p>
                        </div>

                        <div>
                            <label class="label" for="support_telegram">Telegram link</label>
                            <input id="support_telegram" name="support_telegram" class="input" placeholder="https://t.me/yourhandle"
                                   value="{{ old('support_telegram', $settings['support_telegram'] ?? '') }}">
                        </div>

                        <div>
                            <label class="label" for="support_whatsapp">WhatsApp link</label>
                            <input id="support_whatsapp" name="support_whatsapp" class="input" placeholder="https://wa.me/1234567890"
                                   value="{{ old('support_whatsapp', $settings['support_whatsapp'] ?? '') }}">
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary">Save site settings</button>
                    </div>
                </form>
            @endif

            {{-- Payments --------------------------------------------------- --}}
            @if ($tab === 'payments')
                <form method="POST" action="{{ route('admin.settings.payments') }}" class="card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold text-ink-900">Crypto payments</h2>
                    <p class="mt-1 text-sm text-ink-500">Customers pay in USDT on the TRON network. Payments are matched on-chain by transaction hash.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label class="label" for="crypto_usdt_trc20_address">USDT (TRC20) receiving address</label>
                            <input id="crypto_usdt_trc20_address" name="crypto_usdt_trc20_address" class="input font-mono"
                                   placeholder="T..." value="{{ old('crypto_usdt_trc20_address', $settings['crypto_usdt_trc20_address'] ?? '') }}">
                            <p class="hint">Your own wallet. Every customer sends here, matched by their transaction hash.</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="crypto_payment_window_minutes">Payment window (minutes)</label>
                                <input id="crypto_payment_window_minutes" name="crypto_payment_window_minutes" type="number" min="5" max="1440" class="input"
                                       value="{{ old('crypto_payment_window_minutes', $settings['crypto_payment_window_minutes'] ?? 30) }}">
                                <p class="hint">How long a quote stays valid before expiring.</p>
                            </div>

                            <div>
                                <label class="label" for="crypto_amount_tolerance_percent">Underpayment tolerance %</label>
                                <input id="crypto_amount_tolerance_percent" name="crypto_amount_tolerance_percent" type="number" step="0.1" min="0" max="10" class="input"
                                       value="{{ old('crypto_amount_tolerance_percent', $settings['crypto_amount_tolerance_percent'] ?? 0.5) }}">
                                <p class="hint">Accepts slightly short payments from fee rounding.</p>
                            </div>
                        </div>

                        <div>
                            <label class="label" for="trongrid_api_key">
                                TronGrid API key
                                @if ($stored('trongrid_api_key'))
                                    <span class="badge-green ml-1">Stored</span>
                                @endif
                            </label>
                            <input id="trongrid_api_key" name="trongrid_api_key" type="password" class="input"
                                   placeholder="{{ $stored('trongrid_api_key') ? 'Leave blank to keep current key' : 'Optional' }}" autocomplete="off">
                            <p class="hint">Optional — raises the rate limit for on-chain checks. Free at trongrid.io.</p>
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary">Save payment settings</button>
                    </div>
                </form>
            @endif

            {{-- VMOS -------------------------------------------------------- --}}
            @if ($tab === 'vmos')
                <form method="POST" action="{{ route('admin.settings.vmos') }}" class="card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold text-ink-900">VMOS Cloud API</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        From the VMOS console, under <span class="font-medium">Developer &rarr; API</span>.
                        These credentials let this site buy and control cloud phones on your account.
                    </p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label class="label" for="vmos_base_url">API base URL</label>
                            <input id="vmos_base_url" name="vmos_base_url" type="url" class="input font-mono"
                                   value="{{ old('vmos_base_url', $settings['vmos_base_url'] ?? config('vmos.base_url')) }}">
                        </div>

                        <div>
                            <label class="label" for="vmos_access_key">
                                Access Key ID
                                @if ($stored('vmos_access_key'))
                                    <span class="badge-green ml-1">Stored</span>
                                @endif
                            </label>
                            <input id="vmos_access_key" name="vmos_access_key" type="password" class="input" autocomplete="off"
                                   placeholder="{{ $stored('vmos_access_key') ? 'Leave blank to keep current key' : 'ak_xxxxxxxxxxxx' }}">
                        </div>

                        <div>
                            <label class="label" for="vmos_secret_key">
                                Secret Access Key
                                @if ($stored('vmos_secret_key'))
                                    <span class="badge-green ml-1">Stored</span>
                                @endif
                            </label>
                            <input id="vmos_secret_key" name="vmos_secret_key" type="password" class="input" autocomplete="off"
                                   placeholder="{{ $stored('vmos_secret_key') ? 'Leave blank to keep current key' : 'sk_xxxxxxxxxxxx' }}">
                            <p class="hint">Encrypted before being stored, and never shown again after saving.</p>
                        </div>

                        <div>
                            <label class="label" for="vmos_price_unit">Price unit VMOS quotes in</label>
                            <select id="vmos_price_unit" name="vmos_price_unit" class="input">
                                <option value="cents" @selected(($settings['vmos_price_unit'] ?? 'cents') === 'cents')>Cents — a $4.99 plan arrives as 499</option>
                                <option value="units" @selected(($settings['vmos_price_unit'] ?? '') === 'units')>Whole units — a $4.99 plan arrives as 4.99</option>
                            </select>
                            <p class="hint">
                                VMOS documents proxy prices in cents, so plan prices are almost certainly cents too.
                                Confirm on the <a href="{{ route('admin.diagnostics.index', ['probe' => 'catalogue_all']) }}" class="font-medium text-brand-600 hover:underline">diagnostics page</a>
                                before syncing, or your prices will be 100&times; off.
                            </p>
                        </div>

                        <div>
                            <label class="label" for="vmos_webhook_token">
                                Callback token
                                @if ($stored('vmos_webhook_token'))
                                    <span class="badge-green ml-1">Stored</span>
                                @endif
                            </label>
                            <input id="vmos_webhook_token" name="vmos_webhook_token" type="password" class="input" autocomplete="off"
                                   placeholder="{{ $stored('vmos_webhook_token') ? 'Leave blank to keep current token' : 'Any long random string' }}">
                            <p class="hint">
                                Then set your callback URL in the VMOS console to:<br>
                                <code class="mt-1 inline-block break-all rounded bg-ink-100 px-1.5 py-1 font-mono text-[11px]">{{ url('/api/vmos/callback') }}?token=<em>your-token</em></code>
                            </p>
                        </div>
                    </div>

                    <div class="mt-7 flex flex-wrap justify-end gap-2 border-t border-ink-100 pt-5">
                        <button class="btn-primary">Save credentials</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.settings.test-vmos') }}" class="card mt-4 flex flex-wrap items-center justify-between gap-4 p-5">
                    @csrf
                    <div>
                        <p class="text-sm font-semibold text-ink-900">Test connection</p>
                        <p class="text-xs text-ink-500">Makes a real signed API call to confirm your keys work.</p>
                    </div>
                    <button class="btn-secondary">Run test</button>
                </form>
            @endif

            {{-- Mail ------------------------------------------------------- --}}
            @if ($tab === 'mail')
                <form method="POST" action="{{ route('admin.settings.mail') }}" class="card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold text-ink-900">Email delivery</h2>
                    <p class="mt-1 text-sm text-ink-500">Used for password resets and customer notifications. Your cPanel email account works here.</p>

                    <div class="mt-6 space-y-5" x-data="{ mailer: '{{ old('mail_mailer', $settings['mail_mailer'] ?? config('mail.default')) }}' }">
                        <div>
                            <label class="label" for="mail_mailer">Delivery method</label>
                            <select id="mail_mailer" name="mail_mailer" x-model="mailer" class="input">
                                <option value="smtp">SMTP (recommended)</option>
                                <option value="sendmail">Sendmail (server default)</option>
                                <option value="log">Log only — writes to storage/logs, sends nothing</option>
                            </select>
                        </div>

                        <div x-show="mailer === 'smtp'" x-cloak class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="mail_host">SMTP host</label>
                                <input id="mail_host" name="mail_host" class="input" placeholder="mail.yourdomain.com"
                                       value="{{ old('mail_host', $settings['mail_host'] ?? '') }}">
                            </div>
                            <div>
                                <label class="label" for="mail_port">Port</label>
                                <input id="mail_port" name="mail_port" type="number" class="input" placeholder="465"
                                       value="{{ old('mail_port', $settings['mail_port'] ?? '') }}">
                            </div>
                            <div>
                                <label class="label" for="mail_username">Username</label>
                                <input id="mail_username" name="mail_username" class="input" autocomplete="off"
                                       value="{{ old('mail_username', $settings['mail_username'] ?? '') }}">
                            </div>
                            <div>
                                <label class="label" for="mail_password">
                                    Password
                                    @if ($stored('mail_password'))
                                        <span class="badge-green ml-1">Stored</span>
                                    @endif
                                </label>
                                <input id="mail_password" name="mail_password" type="password" class="input" autocomplete="off"
                                       placeholder="{{ $stored('mail_password') ? 'Leave blank to keep current' : '' }}">
                            </div>
                            <div>
                                <label class="label" for="mail_encryption">Encryption</label>
                                <select id="mail_encryption" name="mail_encryption" class="input">
                                    @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $v => $l)
                                        <option value="{{ $v }}" @selected(old('mail_encryption', $settings['mail_encryption'] ?? 'ssl') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 border-t border-ink-100 pt-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="mail_from_address">From address</label>
                                <input id="mail_from_address" name="mail_from_address" type="email" class="input" placeholder="noreply@yourdomain.com"
                                       value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}">
                            </div>
                            <div>
                                <label class="label" for="mail_from_name">From name</label>
                                <input id="mail_from_name" name="mail_from_name" class="input"
                                       value="{{ old('mail_from_name', $settings['mail_from_name'] ?? config('app.name')) }}">
                            </div>
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary">Save email settings</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="card mt-4 p-5">
                    @csrf
                    <p class="text-sm font-semibold text-ink-900">Send a test email</p>
                    <p class="text-xs text-ink-500">Confirms your settings actually deliver.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input name="test_email" type="email" class="input flex-1" placeholder="you@example.com"
                               value="{{ auth()->user()->email }}" required>
                        <button class="btn-secondary">Send test</button>
                    </div>
                </form>
            @endif

            {{-- AI assistant ------------------------------------------------ --}}
            @if ($tab === 'assistant')
                <form method="POST" action="{{ route('admin.settings.assistant') }}" class="card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold text-ink-900">AI live chat</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        A chat bubble on every page, answered by Claude. It reads your live plans, prices, payment
                        flow and — for signed-in customers — their own devices and orders, so it stays correct
                        without you retraining anything.
                    </p>

                    <div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                        <p class="font-semibold">This costs money per message.</p>
                        <p class="mt-1 text-xs leading-relaxed">
                            Replies are billed to your own Anthropic account at
                            <a href="https://console.anthropic.com/settings/billing" target="_blank" rel="noopener" class="underline">console.anthropic.com</a>,
                            separately from any Claude subscription. Set a spend limit there, and keep the hourly
                            message cap below sensible. Turn the toggle off to hide the widget entirely.
                        </p>
                    </div>

                    <div class="mt-6 space-y-5">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="assistant_enabled" value="1" class="mt-0.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                                   @checked(old('assistant_enabled', $settings['assistant_enabled'] ?? '0') === '1')>
                            <span>
                                <span class="block text-sm font-medium text-ink-900">Show live chat on the site</span>
                                <span class="block text-xs text-ink-500">Visitors and customers both get the chat bubble.</span>
                            </span>
                        </label>

                        {{-- Provider ------------------------------------------ --}}
                        @php
                            $presets = \App\Services\Chat\Providers\OpenAiCompatibleProvider::PRESETS;
                            $currentProvider = old('assistant_provider', $settings['assistant_provider'] ?? config('assistant.provider'));
                        @endphp

                        <div x-data="{
                                provider: '{{ $currentProvider }}',
                                preset: '{{ old('assistant_openai_preset', $settings['assistant_openai_preset'] ?? config('assistant.openai_preset')) }}',
                                presets: {{ Js::from(collect($presets)->map(fn ($p) => ['label' => $p[0], 'url' => $p[1], 'model' => $p[2], 'keys' => $p[3]])) }},
                                apply() {
                                    const p = this.presets[this.preset];
                                    if (p && p.url) { this.$refs.baseUrl.value = p.url; this.$refs.model.value = p.model; }
                                },
                             }">
                            <label class="label">Who answers</label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    'claude' => ['Claude (Anthropic)', 'Best quality. Paid per message, prompt caching supported.'],
                                    'openai' => ['Another provider', 'Groq, Google Gemini, OpenRouter, DeepSeek… several have free tiers.'],
                                ] as $value => [$label, $blurb])
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-colors"
                                           :class="provider === '{{ $value }}' ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:bg-ink-50'">
                                        <input type="radio" name="assistant_provider" value="{{ $value }}" x-model="provider"
                                               class="mt-0.5 border-ink-300 text-brand-600 focus:ring-brand-500">
                                        <span>
                                            <span class="block text-sm font-medium text-ink-900">{{ $label }}</span>
                                            <span class="block text-xs text-ink-500">{{ $blurb }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Claude fields --}}
                            <div x-show="provider === 'claude'" x-cloak class="mt-5 space-y-5">
                                <div>
                                    <label class="label" for="anthropic_api_key">Anthropic API key</label>
                                    <input id="anthropic_api_key" name="anthropic_api_key" type="password" class="input"
                                           placeholder="{{ $stored('anthropic_api_key') ? '•••••••• stored — leave blank to keep' : 'sk-ant-…' }}"
                                           autocomplete="new-password">
                                    <p class="hint">Create one at console.anthropic.com → API keys. Stored encrypted; never shown again.</p>
                                </div>

                                <div>
                                    <label class="label" for="assistant_model">Model</label>
                                    <select id="assistant_model" name="assistant_model" class="input">
                                        @foreach ([
                                            'claude-opus-5' => 'Claude Opus 5 — smartest, most expensive',
                                            'claude-sonnet-5' => 'Claude Sonnet 5 — balanced',
                                            'claude-haiku-4-5' => 'Claude Haiku 4.5 — cheapest, fastest',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('assistant_model', $settings['assistant_model'] ?? config('assistant.model')) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="hint">Haiku is plenty for routine questions and costs a fraction as much.</p>
                                </div>
                            </div>

                            {{-- OpenAI-compatible fields --}}
                            <div x-show="provider === 'openai'" x-cloak class="mt-5 space-y-5">
                                <div class="rounded-xl bg-ink-50 p-4 text-xs leading-relaxed text-ink-600 ring-1 ring-inset ring-ink-200">
                                    Works with anything that speaks the OpenAI <code>/chat/completions</code> format.
                                    Free tiers are rate-limited and may use your conversations for training —
                                    check the provider's terms before pointing customer chats at one.
                                </div>

                                <div>
                                    <label class="label" for="assistant_openai_preset">Provider</label>
                                    <select id="assistant_openai_preset" name="assistant_openai_preset" class="input"
                                            x-model="preset" @change="apply()">
                                        @foreach ($presets as $key => $p)
                                            <option value="{{ $key }}">{{ $p[0] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="hint">
                                        Picking one fills in the endpoint and a sensible model below.
                                        <template x-if="presets[preset] && presets[preset].keys">
                                            <span>Get a key at <span class="font-mono" x-text="presets[preset].keys"></span>.</span>
                                        </template>
                                    </p>
                                </div>

                                <div>
                                    <label class="label" for="assistant_openai_base_url">API endpoint</label>
                                    <input id="assistant_openai_base_url" name="assistant_openai_base_url" x-ref="baseUrl" class="input font-mono text-xs"
                                           value="{{ old('assistant_openai_base_url', $settings['assistant_openai_base_url'] ?? config('assistant.openai_base_url')) }}">
                                    <p class="hint">Base URL only — <code>/chat/completions</code> is appended for you.</p>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label class="label" for="assistant_openai_model">Model</label>
                                        <input id="assistant_openai_model" name="assistant_openai_model" x-ref="model" class="input font-mono text-xs"
                                               value="{{ old('assistant_openai_model', $settings['assistant_openai_model'] ?? config('assistant.openai_model')) }}">
                                    </div>
                                    <div>
                                        <label class="label" for="assistant_openai_api_key">API key</label>
                                        <input id="assistant_openai_api_key" name="assistant_openai_api_key" type="password" class="input"
                                               placeholder="{{ $stored('assistant_openai_api_key') ? '•••••••• stored — leave blank to keep' : 'Paste your key' }}"
                                               autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="assistant_rate_limit_per_hour">Messages per visitor per hour</label>
                                <input id="assistant_rate_limit_per_hour" name="assistant_rate_limit_per_hour" type="number" min="1" max="500" class="input"
                                       value="{{ old('assistant_rate_limit_per_hour', $settings['assistant_rate_limit_per_hour'] ?? config('assistant.rate_limit_per_hour')) }}">
                                <p class="hint">Your cap against one person running up a bill.</p>
                            </div>

                            <div>
                                <label class="label" for="assistant_max_tokens">Max reply length (tokens)</label>
                                <input id="assistant_max_tokens" name="assistant_max_tokens" type="number" min="256" max="8192" step="128" class="input"
                                       value="{{ old('assistant_max_tokens', $settings['assistant_max_tokens'] ?? config('assistant.max_tokens')) }}">
                                <p class="hint">1200 is roughly 900 words. Shorter replies cost less.</p>
                            </div>
                        </div>

                        <div>
                            <label class="label" for="assistant_greeting">Opening message</label>
                            <textarea id="assistant_greeting" name="assistant_greeting" rows="2" class="input">{{ old('assistant_greeting', $settings['assistant_greeting'] ?? config('assistant.greeting')) }}</textarea>
                            <p class="hint">The first thing a visitor sees when they open the chat.</p>
                        </div>

                        <div>
                            <label class="label" for="assistant_knowledge">What else should it know?</label>
                            <textarea id="assistant_knowledge" name="assistant_knowledge" rows="12" class="input font-mono text-xs"
                                      placeholder="Refund policy: no refunds once a device is provisioned; contact support within 24h if the device never came online.&#10;Delivery: devices are usually ready within 2 minutes of payment confirming.&#10;We do not support: banking apps, anything requiring Google Play certification.&#10;Bulk orders: 10+ devices — email us for a discount.">{{ old('assistant_knowledge', $settings['assistant_knowledge'] ?? '') }}</textarea>
                            <p class="hint">
                                Plain text, one fact per line. This is how you teach it your business rules —
                                refunds, delivery times, what you don't support, bulk pricing, anything customers keep
                                asking. It takes priority over everything else the assistant knows.
                            </p>
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end border-t border-ink-100 pt-5">
                        <button class="btn-primary">Save assistant settings</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.settings.test-assistant') }}" class="card mt-4 flex flex-wrap items-center gap-3 p-5">
                    @csrf
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-ink-900">Test the assistant</p>
                        <p class="text-xs text-ink-500">Sends one real question and shows you the answer. Costs a fraction of a cent.</p>
                    </div>
                    <button class="btn-secondary">Send test question</button>
                </form>

                <div class="card mt-4 p-5">
                    <p class="text-sm font-semibold text-ink-900">Read what customers are asking</p>
                    <p class="mt-1 text-xs text-ink-500">Every conversation is saved so you can see where the assistant helped — and where it didn't.</p>
                    <a href="{{ route('admin.chat.index') }}" class="btn-secondary btn-sm mt-3">Open chat transcripts</a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
