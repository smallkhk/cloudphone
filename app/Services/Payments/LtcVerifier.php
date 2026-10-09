<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a submitted native LTC transaction hash against BlockCypher's
 * public Litecoin API — keyless, same "no registered API key" principle as
 * the BSC RPC node used for BEP20 (an optional token, like TronGrid's, only
 * raises the rate limit).
 */
class LtcVerifier
{
    public function verify(CryptoPayment $payment): bool
    {
        if (! $payment->tx_hash) {
            return false;
        }

        return $this->verifyTransfer($payment->tx_hash, $payment->pay_to_address, (float) $payment->amount_crypto);
    }

    /** Same check, usable outside a CryptoPayment (e.g. a wallet top-up). */
    public function verifyTransfer(string $txHash, string $payToAddress, float $amountCrypto): bool
    {
        $query = [];
        if ($token = config('crypto.blockcypher_api_token')) {
            $query['token'] = $token;
        }

        $response = Http::timeout(20)->get(rtrim(config('crypto.blockcypher_ltc_base_url'), '/')."/txs/{$txHash}", $query);

        if (! $response->successful()) {
            Log::warning('blockcypher.request_failed', ['pay_to_address' => $payToAddress, 'status' => $response->status()]);

            return false;
        }

        $tx = $response->json();

        // Unconfirmed, a double-spend attempt, or the transaction doesn't exist — none count as paid.
        if (($tx['double_spend'] ?? false) || ($tx['confirmations'] ?? 0) < (int) config('crypto.ltc_min_confirmations')) {
            return false;
        }

        $receivedSatoshis = 0;
        foreach ($tx['outputs'] ?? [] as $output) {
            if (in_array($payToAddress, $output['addresses'] ?? [], true)) {
                $receivedSatoshis += (int) ($output['value'] ?? 0);
            }
        }

        $receivedAmount = $receivedSatoshis / 100_000_000;

        $tolerance = (float) config('crypto.ltc_amount_tolerance_percent') / 100;
        $minAcceptable = $amountCrypto * (1 - $tolerance);

        return $receivedAmount >= $minAcceptable;
    }
}
