<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use App\Models\WalletDeposit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds USDT-BEP20 payments that arrived at the receiving address without the
 * customer pasting a tx hash, and attaches each one to its quote — after which
 * the normal receipt-based verification confirms it like any submitted hash.
 *
 * Every customer pays the same shared address, so a transfer is only attached
 * when exactly one open quote fits it: same address, amount within the
 * tolerance, and created before the transfer landed. Two open quotes for the
 * same amount is ambiguous — rather than guess on real money, those wait for
 * the customer to paste their hash, exactly as before.
 */
class Bep20AutoMatcher
{
    public function __construct(
        protected BscUsdtVerifier $bsc,
        protected CryptoPaymentService $payments,
        protected WalletDepositService $deposits,
    ) {}

    public function run(): int
    {
        $open = collect()
            ->concat(CryptoPayment::where('network', 'BEP20')->where('status', CryptoPayment::STATUS_AWAITING_PAYMENT)
                ->whereNull('tx_hash')->where('expires_at', '>', now())->get())
            ->concat(WalletDeposit::where('network', 'BEP20')->where('status', WalletDeposit::STATUS_AWAITING_PAYMENT)
                ->whereNull('tx_hash')->where('expires_at', '>', now())->get());

        if ($open->isEmpty()) {
            return 0;
        }

        $tolerance = (float) config('crypto.amount_tolerance_percent') / 100;
        $matched = 0;

        foreach ($open->groupBy(fn ($q) => strtolower($q->pay_to_address)) as $address => $quotes) {
            try {
                $transfers = $this->bsc->incomingTransfers($address);
            } catch (Throwable $e) {
                Log::warning('bep20.auto_match_scan_failed', ['address' => $address, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($transfers as $transfer) {
                if (TxHash::alreadyUsed($transfer['hash'])) {
                    continue;
                }

                $fits = $quotes->filter(fn ($q) => $q->created_at->timestamp <= $transfer['timestamp'] + 60
                    && abs($transfer['amount'] - (float) $q->amount_crypto) <= (float) $q->amount_crypto * $tolerance);

                if ($fits->count() !== 1) {
                    if ($fits->count() > 1) {
                        Log::info('bep20.auto_match_ambiguous', ['tx' => $transfer['hash'], 'candidates' => $fits->count()]);
                    }

                    continue;
                }

                $quote = $fits->first();

                $quote instanceof CryptoPayment
                    ? $this->payments->submitTransactionHash($quote, $transfer['hash'])
                    : $this->deposits->submitTransactionHash($quote, $transfer['hash']);

                $quotes = $quotes->reject(fn ($q) => $q === $quote);
                $matched++;
            }
        }

        return $matched;
    }
}
