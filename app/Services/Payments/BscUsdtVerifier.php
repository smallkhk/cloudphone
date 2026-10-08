<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a submitted USDT-BEP20 transaction hash directly against a public
 * BNB Smart Chain RPC node — no BscScan/Etherscan API key needed.
 *
 * BscScan's API (and every other Etherscan-family explorer, now merged into
 * "Etherscan V2") requires a registered API key for every request, even on
 * the free tier — unlike TronGrid, there's no keyless path through them. The
 * chain's own public RPC nodes have no such requirement: this fetches the
 * transaction receipt (`eth_getTransactionReceipt`) and reads the ERC20/BEP20
 * `Transfer(address,address,uint256)` event straight out of its logs —
 * everything the explorer's API would have told us, read from the chain
 * itself instead of a third party's indexer.
 */
class BscUsdtVerifier
{
    /** keccak256("Transfer(address,address,uint256)") — the standard ERC20/BEP20 Transfer event signature. */
    protected const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /** Binance-Peg USDT uses 18 decimals (unlike Tron's USDT, which uses 6). */
    protected const DECIMALS = 18;

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
        $response = Http::timeout(20)->post(config('crypto.bsc_rpc_url'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_getTransactionReceipt',
            'params' => [$txHash],
        ]);

        if (! $response->successful()) {
            Log::warning('bsc_rpc.request_failed', ['pay_to_address' => $payToAddress, 'status' => $response->status()]);

            return false;
        }

        $receipt = $response->json('result');

        // A pending/unmined tx, an unknown hash, or a reverted one (status != 0x1) — none count as paid.
        if (! $receipt || ($receipt['status'] ?? null) !== '0x1') {
            return false;
        }

        $contract = strtolower((string) config('crypto.usdt_bep20_contract'));
        $wantTo = strtolower($payToAddress);
        $tolerance = (float) config('crypto.amount_tolerance_percent') / 100;
        $minAcceptable = $amountCrypto * (1 - $tolerance);

        foreach ($receipt['logs'] ?? [] as $log) {
            if (strtolower($log['address'] ?? '') !== $contract) {
                continue;
            }

            $topics = $log['topics'] ?? [];

            if (strtolower($topics[0] ?? '') !== self::TRANSFER_TOPIC || count($topics) < 3) {
                continue;
            }

            // Indexed address params are left-padded to 32 bytes — the real
            // address is the last 40 hex chars.
            $toAddress = '0x'.substr(strtolower($topics[2]), -40);

            if ($toAddress !== $wantTo) {
                continue;
            }

            $receivedAmount = $this->hexToDecimal($log['data'] ?? '0x0') / (10 ** self::DECIMALS);

            if ($receivedAmount >= $minAcceptable) {
                return true;
            }
        }

        return false;
    }

    /** hexdec() already returns a float for values beyond PHP_INT_MAX, which is all we need for a tolerance-based comparison. */
    protected function hexToDecimal(string $hex): float
    {
        $hex = ltrim(str_replace('0x', '', $hex), '0');

        return $hex === '' ? 0.0 : (float) hexdec($hex);
    }
}
