<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a submitted USDT-BEP20 transaction hash against BscScan's public
 * API, mirroring TronUsdtVerifier for BNB Smart Chain. Uses the "BEP20 token
 * transfer events by address" endpoint, which returns decoded transfer
 * amounts the same way TronGrid's TRC20 endpoint does:
 * https://docs.bscscan.com/api-endpoints/tokens#get-a-list-of-bep-20-token-transfer-events-by-address
 */
class BscUsdtVerifier
{
    public function verify(CryptoPayment $payment): bool
    {
        if (! $payment->tx_hash) {
            return false;
        }

        return $this->verifyTransfer($payment->tx_hash, $payment->pay_to_address, (float) $payment->amount_crypto);
    }

    public function verifyTransfer(string $txHash, string $payToAddress, float $amountCrypto): bool
    {
        $apiKey = config('crypto.bscscan_api_key');

        if (! $apiKey) {
            Log::warning('bscscan.not_configured');

            return false;
        }

        $response = Http::timeout(20)
            ->get(rtrim(config('crypto.bscscan_base_url'), '/').'/api', [
                'module' => 'account',
                'action' => 'tokentx',
                'contractaddress' => config('crypto.usdt_bep20_contract'),
                'address' => $payToAddress,
                'sort' => 'desc',
                'apikey' => $apiKey,
            ]);

        if (! $response->successful()) {
            Log::warning('bscscan.request_failed', ['pay_to_address' => $payToAddress, 'status' => $response->status()]);

            return false;
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== '1') {
            return false;
        }

        foreach ($body['result'] ?? [] as $transfer) {
            if (strcasecmp((string) ($transfer['hash'] ?? ''), $txHash) !== 0) {
                continue;
            }

            if (strcasecmp((string) ($transfer['to'] ?? ''), $payToAddress) !== 0) {
                continue;
            }

            $decimals = (int) ($transfer['tokenDecimal'] ?? 18);
            $receivedAmount = ((float) ($transfer['value'] ?? 0)) / (10 ** $decimals);

            $tolerance = (float) config('crypto.amount_tolerance_percent') / 100;
            $minAcceptable = $amountCrypto * (1 - $tolerance);

            return $receivedAmount >= $minAcceptable;
        }

        return false;
    }
}
