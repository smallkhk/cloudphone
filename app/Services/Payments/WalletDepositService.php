<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\User;
use App\Models\WalletDeposit;
use RuntimeException;

/**
 * Creates a USDT deposit quote for topping up a customer's wallet balance —
 * same shape as CryptoPaymentService, just not tied to an order.
 */
class WalletDepositService
{
    public function create(User $user, float $amountUsd, string $network): WalletDeposit
    {
        $address = match ($network) {
            'TRC20' => config('crypto.usdt_trc20_address'),
            'BEP20' => config('crypto.usdt_bep20_address'),
            default => throw new RuntimeException("Unsupported crypto network: {$network}"),
        };

        if (empty($address)) {
            throw new PaymentsNotConfiguredException(
                'No receiving wallet address configured for '.$network.
                ' — set it under Admin → Settings → Payments.'
            );
        }

        return $user->walletDeposits()->create([
            'currency' => 'USDT',
            'network' => $network,
            'pay_to_address' => $address,
            'amount_crypto' => $amountUsd,
            'amount_usd' => $amountUsd,
            'status' => WalletDeposit::STATUS_AWAITING_PAYMENT,
            'expires_at' => now()->addMinutes((int) config('crypto.payment_window_minutes')),
        ]);
    }

    public function submitTransactionHash(WalletDeposit $deposit, string $txHash): WalletDeposit
    {
        if ($deposit->status !== WalletDeposit::STATUS_AWAITING_PAYMENT) {
            throw new RuntimeException('This deposit is no longer awaiting a transaction hash.');
        }

        $deposit->update([
            'tx_hash' => $txHash,
            'status' => WalletDeposit::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $deposit->fresh();
    }
}
