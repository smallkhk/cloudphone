<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a submitted USDT-TRC20 transaction hash against TronGrid's public API.
 *
 * We use the "TRC20 transfers for an account" endpoint because it returns
 * already-decoded transfer amounts (no manual event-log parsing needed):
 * https://developers.tron.network/reference/get-trc20-transaction-info-by-account-address
 */
class TronUsdtVerifier
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
        $headers = [];
        if ($apiKey = config('crypto.trongrid_api_key')) {
            $headers['TRON-PRO-API-KEY'] = $apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(20)
            ->get(rtrim(config('crypto.trongrid_base_url'), '/')."/v1/accounts/{$payToAddress}/transactions/trc20", [
                'only_confirmed' => 'true',
                'only_to' => 'true',
                'contract_address' => config('crypto.usdt_trc20_contract'),
                'limit' => 50,
            ]);

        if (! $response->successful()) {
            Log::warning('trongrid.request_failed', ['pay_to_address' => $payToAddress, 'status' => $response->status()]);

            return false;
        }

        $transfers = $response->json('data', []);

        foreach ($transfers as $transfer) {
            if (($transfer['transaction_id'] ?? null) !== $txHash) {
                continue;
            }

            if (($transfer['to'] ?? null) !== $payToAddress) {
                continue;
            }

            $decimals = (int) ($transfer['token_info']['decimals'] ?? 6);
            $receivedAmount = ((float) ($transfer['value'] ?? 0)) / (10 ** $decimals);

            $tolerance = (float) config('crypto.amount_tolerance_percent') / 100;
            $minAcceptable = $amountCrypto * (1 - $tolerance);

            return $receivedAmount >= $minAcceptable;
        }

        return false;
    }
}
