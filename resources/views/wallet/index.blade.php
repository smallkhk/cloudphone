@php
    $pendingDeposit = $deposits->firstWhere('id', session('newDepositId'))
        ?? $deposits->firstWhere('status', 'awaiting_payment');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Wallet" subtitle="Top up your balance and pay for renewals instantly, without a fresh crypto payment every time." />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Pending deposit quote --}}
            @if ($pendingDeposit)
                <div class="card overflow-hidden">
                    <div class="border-b border-ink-100 bg-gradient-to-r from-brand-600 to-indigo-600 px-6 py-5">
                        <h2 class="text-lg font-semibold text-white">Complete your deposit</h2>
                        <p class="mt-1 text-sm text-brand-100">Send the exact amount below, then paste your transaction hash.</p>
                    </div>

                    <div class="p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-ink-50 p-4 ring-1 ring-inset ring-ink-200">
                                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Amount to send</p>
                                <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ number_format($pendingDeposit->amount_crypto, 2) }} <span class="text-base font-semibold text-ink-500">USDT</span></p>
                                <p class="mt-1 text-xs text-ink-500">Network: {{ $pendingDeposit->network }}</p>
                            </div>
                            <div class="rounded-xl bg-ink-50 p-4 ring-1 ring-inset ring-ink-200">
                                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Time remaining</p>
                                <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ $pendingDeposit->expires_at->diffForHumans(null, true) }}</p>
                                <p class="mt-1 text-xs text-ink-500">Quote expires {{ $pendingDeposit->expires_at->format('H:i') }}</p>
                            </div>
                        </div>

                        <div class="mt-4" x-data="{ copied: false }">
                            <p class="label">Send USDT ({{ $pendingDeposit->network }}) to this address</p>
                            <div class="flex gap-2">
                                <code class="flex-1 break-all rounded-xl bg-ink-900 px-4 py-3 font-mono text-xs text-white">{{ $pendingDeposit->pay_to_address }}</code>
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $pendingDeposit->pay_to_address }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="btn-secondary flex-none">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak class="text-emerald-600">Copied</span>
                                </button>
                            </div>
                            <p class="hint">Send only USDT on the {{ $pendingDeposit->network }} network. Other tokens or networks will be lost.</p>
                        </div>

                        <form method="POST" action="{{ route('wallet.deposit.tx', $pendingDeposit) }}" class="mt-6 border-t border-ink-100 pt-6">
                            @csrf
                            <label class="label" for="tx_hash">Transaction hash</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input id="tx_hash" name="tx_hash" class="input flex-1 font-mono text-sm"
                                       placeholder="Paste the hash from your wallet" required minlength="10">
                                <button class="btn-primary flex-none">I've sent the payment</button>
                            </div>
                            <p class="hint">We verify it on-chain automatically — usually within a couple of minutes.</p>
                        </form>
                    </div>
                </div>
            @else
                {{-- Deposit form --}}
                <div class="card p-6">
                    <h2 class="text-base font-semibold text-ink-900">Top up your balance</h2>
                    @if (empty($networksAvailable))
                        <p class="mt-2 text-sm text-ink-500">Deposits are temporarily unavailable. Please contact support.</p>
                    @else
                        <form method="POST" action="{{ route('wallet.deposit') }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="label" for="amount_usd">Amount (USD)</label>
                                <input id="amount_usd" name="amount_usd" type="number" min="5" max="100000" step="0.01" class="input" placeholder="50.00" required>
                            </div>
                            <div>
                                <label class="label" for="network">Network</label>
                                <select id="network" name="network" class="input">
                                    @foreach (array_keys($networksAvailable) as $network)
                                        <option value="{{ $network }}">USDT ({{ $network }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn-primary">Get deposit address</button>
                        </form>
                        <p class="hint mt-3">Minimum deposit $5. Your balance updates automatically once the transaction is confirmed on-chain.</p>
                    @endif
                </div>
            @endif

            {{-- Deposit history --}}
            <div class="card overflow-hidden">
                <div class="border-b border-ink-100 p-5">
                    <h2 class="text-base font-semibold text-ink-900">Deposit history</h2>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($deposits as $deposit)
                        <div class="flex items-center justify-between p-5">
                            <div>
                                <p class="text-sm font-semibold text-ink-900">${{ number_format($deposit->amount_usd, 2) }} <span class="text-ink-400">· {{ $deposit->network }}</span></p>
                                <p class="text-xs text-ink-500">{{ $deposit->created_at->format('d M Y, H:i') }}</p>
                            </div>
                            <span class="{{ match ($deposit->status) {
                                'confirmed' => 'badge-green',
                                'failed', 'expired' => 'badge-red',
                                'submitted' => 'badge-blue',
                                default => 'badge-amber',
                            } }}">{{ ucfirst(str_replace('_', ' ', $deposit->status)) }}</span>
                        </div>
                    @empty
                        <p class="p-5 text-sm text-ink-500">No deposits yet.</p>
                    @endforelse
                </div>
                @if ($deposits->hasPages())
                    <div class="border-t border-ink-100 p-4">{{ $deposits->links() }}</div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Current balance</p>
                <p class="mt-2 text-3xl font-extrabold text-ink-900">${{ number_format($balance, 2) }}</p>
                <p class="mt-2 text-xs text-ink-500">Spend it at checkout on the Plans page — pick "Pay with wallet balance" instead of a fresh crypto payment.</p>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-ink-100 p-5">
                    <h2 class="text-base font-semibold text-ink-900">Balance history</h2>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($transactions as $tx)
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-ink-900">{{ $tx->description }}</p>
                                <p class="text-sm font-semibold {{ $tx->amount >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 2) }}
                                </p>
                            </div>
                            <p class="mt-0.5 text-xs text-ink-400">{{ $tx->created_at->format('d M Y, H:i') }} · balance ${{ number_format($tx->balance_after, 2) }}</p>
                        </div>
                    @empty
                        <p class="p-5 text-sm text-ink-500">No activity yet.</p>
                    @endforelse
                </div>
                @if ($transactions->hasPages())
                    <div class="border-t border-ink-100 p-4">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
