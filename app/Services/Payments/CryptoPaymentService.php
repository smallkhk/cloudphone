<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\CryptoPayment;
use App\Models\Order;
use RuntimeException;

/**
 * Creates the USDT (TRC20) payment quote for an order. A single shared receiving
 * address is used (configured in .env); individual payments are reconciled by the
 * customer-submitted transaction hash, not by a unique per-order address.
 */
class CryptoPaymentService
{
    public function createForOrder(Order $order, string $network = 'TRC20', string $currency = 'USDT'): CryptoPayment
    {
        $address = match ($network) {
            'TRC20' => config('crypto.usdt_trc20_address'),
            default => throw new RuntimeException("Unsupported crypto network: {$network}"),
        };

        if (empty($address)) {
            throw new PaymentsNotConfiguredException(
                'No receiving wallet address configured for '.$network.
                ' — set it under Admin → Settings → Payments.'
            );
        }

        return $order->payments()->create([
            'currency' => $currency,
            'network' => $network,
            'pay_to_address' => $address,
            // USDT is a USD stablecoin, so the crypto amount tracks the USD price 1:1.
            'amount_crypto' => $order->total_price,
            'amount_usd' => $order->total_price,
            'status' => CryptoPayment::STATUS_AWAITING_PAYMENT,
            'expires_at' => now()->addMinutes((int) config('crypto.payment_window_minutes')),
        ]);
    }

    public function submitTransactionHash(CryptoPayment $payment, string $txHash): CryptoPayment
    {
        if ($payment->status !== CryptoPayment::STATUS_AWAITING_PAYMENT) {
            throw new RuntimeException('This payment is no longer awaiting a transaction hash.');
        }

        $payment->update([
            'tx_hash' => $txHash,
            'status' => CryptoPayment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $payment->fresh();
    }
}
