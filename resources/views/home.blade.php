<x-marketing-layout>
    {{-- Hero --------------------------------------------------------------- --}}
    <section class="relative overflow-hidden bg-ink-950">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 h-[38rem] w-[38rem] -translate-x-1/2 rounded-full bg-brand-600/25 blur-3xl"></div>
            <div class="absolute right-0 top-40 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.07)_1px,transparent_0)] [background-size:32px_32px]"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 pb-24 pt-32 sm:px-6 sm:pt-40 lg:px-8">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div class="animate-fade-up">
                    <span class="badge bg-white/10 text-brand-200 ring-white/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Instant setup · No physical devices
                    </span>

                    <h1 class="mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Real Android phones,<br>
                        <span class="bg-gradient-to-r from-brand-300 via-indigo-300 to-sky-300 bg-clip-text text-transparent">
                            running in the cloud
                        </span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-300">
                        Launch unlimited cloud phones in seconds. Each one is a fully isolated Android device with its
                        own IP, IMEI and fingerprint — perfect for multi-account management, social media automation
                        and app testing. Runs 24/7 without touching your own phone.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="btn-primary px-6 py-3 text-base shadow-glow">
                            Get started free
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        <a href="{{ route('plans.index') }}" class="btn px-6 py-3 text-base bg-white/10 text-white ring-1 ring-inset ring-white/20 hover:bg-white/15">
                            View pricing
                        </a>
                    </div>

                    <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-8">
                        @foreach ([['99.9%', 'Uptime'], ['<50ms', 'Latency'], ['24/7', 'Always on']] as [$value, $label])
                            <div>
                                <dt class="text-2xl font-bold text-white">{{ $value }}</dt>
                                <dd class="mt-1 text-sm text-ink-400">{{ $label }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Phone mockup --}}
                <div class="relative mx-auto hidden w-full max-w-sm lg:block">
                    <div class="animate-float rounded-[2.5rem] border-[10px] border-ink-800 bg-ink-900 p-1 shadow-2xl ring-1 ring-white/10">
                        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-600 via-indigo-600 to-ink-900 aspect-[9/19]">
                            <div class="absolute left-1/2 top-2 h-5 w-24 -translate-x-1/2 rounded-full bg-ink-950"></div>

                            <div class="flex h-full flex-col p-5 pt-10 text-white">
                                <div class="flex items-center justify-between text-[10px] font-medium opacity-80">
                                    <span>9:41</span>
                                    <span class="flex items-center gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Cloud · Online
                                    </span>
                                </div>

                                <div class="mt-6 grid grid-cols-4 gap-3">
                                    @foreach (['bg-white/25','bg-white/20','bg-white/25','bg-white/15','bg-white/20','bg-white/25','bg-white/15','bg-white/20'] as $tone)
                                        <div class="aspect-square rounded-xl {{ $tone }} backdrop-blur"></div>
                                    @endforeach
                                </div>

                                <div class="mt-auto space-y-2 rounded-2xl bg-black/30 p-3 backdrop-blur">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="opacity-70">Device</span><span class="font-semibold">Galaxy S23 Ultra</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="opacity-70">Location</span><span class="font-semibold">Hong Kong</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="opacity-70">Status</span>
                                        <span class="font-semibold text-emerald-300">Running</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features ----------------------------------------------------------- --}}
    <section id="features" class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Features</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">
                    Everything a real device does — without the device
                </h2>
                <p class="mt-4 text-lg text-ink-500">
                    Each cloud phone is a genuine Android environment with its own hardware fingerprint,
                    so apps behave exactly as they would on a physical handset.
                </p>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        ['Isolated environments', 'Every phone has its own IMEI, MAC, GPS and device fingerprint. No shared state, no cross-contamination between accounts.', 'M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z'],
                        ['Change IP & location', 'Assign residential or datacenter proxies per device and set GPS to match. Look like a local user anywhere in the world.', 'M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2a15 15 0 010 20 15 15 0 010-20z'],
                        ['One-click new device', 'Reset a phone to factory state with a brand-new hardware identity in seconds. Start fresh whenever you need to.', 'M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006 5.3M4 15a8 8 0 0014 3.7'],
                        ['Multi-account at scale', 'Run dozens of accounts across TikTok, WhatsApp, Instagram and more — each in its own dedicated phone.', 'M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['Automation & ADB', 'Full ADB access plus visual flow automation. Schedule tasks, run scripts, and drive devices programmatically.', 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
                        ['Access from anywhere', 'Control your phones from a browser, desktop or mobile. Your devices keep running even when you close the tab.', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ];
                @endphp

                @foreach ($features as [$title, $body, $path])
                    <div class="card group p-6 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 transition-colors group-hover:bg-brand-600 group-hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-ink-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Use cases ---------------------------------------------------------- --}}
    <section id="use-cases" class="bg-ink-50 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Use cases</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Built for how you actually work</h2>
            </div>

            <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @php
                    $cases = [
                        ['Social media marketing', 'Grow and manage many accounts across platforms without bans from device fingerprint overlap.'],
                        ['E-commerce & dropshipping', 'Run separate storefront accounts and regional marketplaces from one dashboard.'],
                        ['App testing & QA', 'Test across Android versions, screen sizes and locales without buying a device lab.'],
                        ['Crypto & airdrops', 'Keep wallets and campaign accounts fully isolated, each on its own clean device.'],
                    ];
                @endphp

                @foreach ($cases as $i => [$title, $body])
                    <div class="card p-6">
                        <span class="text-3xl font-extrabold text-brand-200">0{{ $i + 1 }}</span>
                        <h3 class="mt-3 text-base font-semibold text-ink-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing ------------------------------------------------------------ --}}
    <section id="pricing" class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Pricing</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Simple, transparent plans</h2>
                <p class="mt-4 text-lg text-ink-500">Pay in USDT. No contracts — cancel or let it expire any time.</p>
            </div>

            @if ($featured->isEmpty())
                <div class="card mx-auto mt-12 max-w-lg p-8 text-center">
                    <p class="text-ink-600">Plans are being set up. Check back shortly.</p>
                    <a href="{{ route('register') }}" class="btn-primary mt-5">Create an account</a>
                </div>
            @else
                <div class="mx-auto mt-16 grid max-w-5xl gap-6 lg:grid-cols-3">
                    @foreach ($featured as $i => $sku)
                        <div class="card relative flex flex-col p-7 {{ $i === 1 ? 'ring-2 ring-brand-500 lg:-mt-4 lg:mb-4' : '' }}">
                            @if ($i === 1)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                                    Most popular
                                </span>
                            @endif

                            <h3 class="text-base font-semibold text-ink-900">{{ $sku->name }}</h3>
                            <p class="mt-1 text-sm text-ink-500">Android {{ $sku->android_version }}</p>

                            <p class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold tracking-tight text-ink-900">${{ number_format($sku->price, 2) }}</span>
                                <span class="text-sm text-ink-500">/ {{ $sku->duration_label }}</span>
                            </p>

                            <ul class="mt-6 flex-1 space-y-3 text-sm text-ink-600">
                                @foreach (['Dedicated Android instance', 'Unique device fingerprint', 'Full ADB & automation access', 'Runs 24/7 in the cloud', 'Restart & reset any time'] as $item)
                                    <li class="flex gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 flex-none text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ route('plans.index') }}" class="{{ $i === 1 ? 'btn-primary' : 'btn-secondary' }} mt-8 w-full">
                                Choose plan
                            </a>
                        </div>
                    @endforeach
                </div>

                <p class="mt-10 text-center">
                    <a href="{{ route('plans.index') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                        See all plans and devices &rarr;
                    </a>
                </p>
            @endif
        </div>
    </section>

    {{-- FAQ ---------------------------------------------------------------- --}}
    <section id="faq" class="bg-ink-50 py-24 sm:py-32">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Frequently asked questions</h2>

            <div class="mt-14 space-y-4" x-data="{ open: 0 }">
                @php
                    $faqs = [
                        ['What exactly is a cloud phone?', 'A real Android operating system running on server hardware in a data centre. You control it from your browser or our app, but it behaves like a separate physical phone — its own storage, apps, IP address and hardware identifiers.'],
                        ['How fast do I get my device?', 'Instantly. Once your payment is confirmed on-chain, your cloud phone is provisioned automatically and appears in your dashboard, usually within a couple of minutes.'],
                        ['How do I pay?', 'We accept USDT on the TRON network (TRC20). At checkout you get a wallet address and exact amount — send it, paste your transaction hash, and your order confirms automatically once the network verifies it.'],
                        ['Can I change the IP or location?', 'Yes. You can attach residential or datacenter proxies per device and set GPS coordinates to match, so apps see a consistent local user.'],
                        ['What happens when my plan expires?', 'The device stops and its data is retained for a grace period. Renew before expiry to keep everything exactly as it was — enable auto-renew and you never have to think about it.'],
                        ['Is there a device or account limit?', 'No hard limit. Buy as many cloud phones as you need and manage them all from one dashboard.'],
                    ];
                @endphp

                @foreach ($faqs as $i => [$q, $a])
                    <div class="card overflow-hidden">
                        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                                class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left"
                                :aria-expanded="(open === {{ $i }}).toString()">
                            <span class="text-base font-semibold text-ink-900">{{ $q }}</span>
                            <svg class="h-5 w-5 flex-none text-ink-400 transition-transform duration-200"
                                 :class="open === {{ $i }} ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse x-cloak>
                            <p class="px-6 pb-5 text-sm leading-relaxed text-ink-600">{{ $a }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA ---------------------------------------------------------------- --}}
    <section class="relative overflow-hidden bg-ink-950 py-20">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute left-1/2 top-1/2 h-96 w-[40rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-600/25 blur-3xl"></div>
        </div>
        <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Ready to launch your first cloud phone?</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-ink-300">
                Create an account, pick a plan, and have a fully isolated Android device running in minutes.
            </p>
            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="btn-primary px-7 py-3 text-base shadow-glow">Get started</a>
                <a href="{{ route('plans.index') }}" class="btn px-7 py-3 text-base bg-white/10 text-white ring-1 ring-inset ring-white/20 hover:bg-white/15">Browse plans</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
