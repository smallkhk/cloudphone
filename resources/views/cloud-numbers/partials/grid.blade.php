@auth
    @if (auth()->user()->is_admin && ! filled(config('crypto.usdt_trc20_address')))
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            <svg class="h-5 w-5 flex-none text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3L13.71 4a2 2 0 00-3.42 0L3.36 16a2 2 0 001.71 3z" />
            </svg>
            <span class="flex-1">
                <strong>Checkout is disabled.</strong> No USDT receiving wallet is set, so customers can't complete a purchase.
            </span>
            <a href="{{ route('admin.settings.edit', 'payments') }}" class="btn-secondary btn-sm">Add wallet</a>
        </div>
    @endif
@endauth

<div class="mb-6 flex items-start gap-3 rounded-xl bg-ink-50 p-4 text-sm text-ink-600 ring-1 ring-inset ring-ink-200">
    <svg class="h-5 w-5 flex-none text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>
        A rented number, not a one-off verification code — see
        <a href="{{ route('phone-numbers.index') }}" class="font-medium text-brand-600 hover:underline">Phone numbers</a>
        for that instead. Buying starts an order that finishes provisioning within a minute or two, then it's ready to
        bind to a cloud phone below.
    </span>
</div>

@if ($skus->isEmpty())
    <div class="card mx-auto max-w-lg p-10 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-ink-100">
            <svg class="h-6 w-6 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
        </div>
        <h2 class="mt-5 text-lg font-semibold text-ink-900">No cloud numbers available yet</h2>
        <p class="mt-2 text-sm text-ink-500">
            @auth
                @if (auth()->user()->is_admin)
                    Sync the catalogue under Plans &amp; pricing → Cloud numbers.
                @else
                    We're setting things up. Please check back shortly.
                @endif
            @else
                We're setting things up. Please check back shortly.
            @endauth
        </p>
        @auth
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.skus.index', ['type' => 'cloud_number']) }}" class="btn-primary mt-6">Go to Plans &amp; pricing</a>
            @endif
        @endauth
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($skus as $sku)
            <div class="card p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                <p class="text-base font-bold tracking-tight text-ink-900">{{ $sku->default_country_code }}</p>
                <p class="text-xs text-ink-500">{{ $sku->duration_label }}</p>
                <p class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold tracking-tight text-ink-900">${{ number_format($sku->price, 2) }}</span>
                </p>
                <p class="mt-1 text-xs text-ink-400">Paid in USDT (TRC20) · auto-renews unless you turn it off</p>

                @auth
                    <form method="POST" action="{{ route('orders.store') }}" class="mt-5">
                        @csrf
                        <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="auto_renew" value="0">
                        <button class="btn-primary w-full">Buy now</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-primary mt-5 w-full">Log in to buy</a>
                @endauth
            </div>
        @endforeach
    </div>
@endif

@auth
    <div class="mt-10">
        <h2 class="text-lg font-semibold text-ink-900">Your cloud numbers</h2>

        @if ($owned->isEmpty())
            <p class="mt-2 text-sm text-ink-500">Numbers you buy will show up here once provisioning finishes.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($owned as $number)
                    <div class="card p-5" x-data="{ showSms: false }">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium text-ink-900">{{ $number->number ?: 'Provisioning…' }}</p>
                                <p class="mt-1 text-xs text-ink-500">{{ $number->sku?->name }}</p>
                                @if ($number->bound_pad_code)
                                    <p class="mt-1 text-xs text-ink-500">
                                        Bound to <span class="font-mono">{{ $number->bound_pad_code }}</span>
                                        — <span class="{{ $number->bind_state === 'SUCCESS' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $number->bind_state ?? 'unknown' }}</span>
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-ink-400">Not bound to a device yet</p>
                                @endif
                            </div>

                            <span class="{{ match ($number->vmos_status) {
                                'normal' => 'badge-green',
                                'soon' => 'badge-amber',
                                'expired', 'disabled' => 'badge-red',
                                default => 'badge-gray',
                            } }}">{{ ucfirst($number->vmos_status ?? $number->purchase_status) }}</span>
                        </div>

                        @if ($number->number)
                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-4">
                                @if (! $number->bound_pad_code && $devices->isNotEmpty())
                                    <form method="POST" action="{{ route('cloud-numbers.bind', $number) }}"
                                          class="flex flex-wrap items-center gap-2"
                                          onsubmit="return confirm('Binding will restart the selected device. Continue?')">
                                        @csrf
                                        <select name="pad_code" class="input text-sm" required>
                                            <option value="">Bind to device…</option>
                                            @foreach ($devices as $device)
                                                <option value="{{ $device->pad_code }}">{{ $device->nickname ?: $device->pad_code }}</option>
                                            @endforeach
                                        </select>
                                        <label class="flex items-center gap-1.5 text-xs text-ink-600">
                                            <input type="checkbox" name="restart_acknowledged" value="1" required
                                                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                            I understand this restarts the device
                                        </label>
                                        <button class="btn-secondary btn-sm">Bind</button>
                                    </form>
                                @elseif ($number->bound_pad_code && $number->bind_state !== 'SUCCESS')
                                    <form method="POST" action="{{ route('cloud-numbers.bind-status', $number) }}">
                                        @csrf
                                        <button class="btn-secondary btn-sm">Check bind progress</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('cloud-numbers.auto-renew', $number) }}">
                                    @csrf
                                    <button class="btn-ghost btn-sm">{{ $number->auto_renew ? 'Turn off auto-renew' : 'Turn on auto-renew' }}</button>
                                </form>

                                <form method="POST" action="{{ route('cloud-numbers.sms', $number) }}">
                                    @csrf
                                    <button class="btn-ghost btn-sm" @click="showSms = true">Check for messages</button>
                                </form>

                                <form method="POST" action="{{ route('cloud-numbers.release', $number) }}"
                                      onsubmit="return confirm('Release this number? No refund for the current period.')">
                                    @csrf
                                    <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Release</button>
                                </form>
                            </div>

                            @if (! empty($number->raw_payload['sms']))
                                <div x-show="showSms" x-cloak class="mt-4 space-y-2 border-t border-ink-100 pt-4">
                                    @foreach ($number->raw_payload['sms'] as $sms)
                                        <div class="rounded-lg bg-ink-50 p-3 text-xs">
                                            <p class="font-medium text-ink-900">{{ $sms['sender'] ?? 'Unknown sender' }}</p>
                                            <p class="mt-0.5 text-ink-600">{{ $sms['content'] ?? '' }}</p>
                                            <p class="mt-0.5 text-ink-400">{{ $sms['receivedTime'] ?? '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endauth
