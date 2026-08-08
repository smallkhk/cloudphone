@php
    $tabs = [
        'site' => ['Site', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10'],
        'payments' => ['Payments', 'M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z'],
        'vmos' => ['VMOS API', 'M5 12h14M12 5l7 7-7 7'],
        'mail' => ['Email', 'M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
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
        </div>
    </div>
</x-admin-layout>
