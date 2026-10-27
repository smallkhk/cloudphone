<?php

namespace App\Services\Payments;

use App\Models\CryptoPayment;
use App\Models\WalletDeposit;
use Illuminate\Database\Eloquent\Model;

/**
 * One canonical spelling per transaction hash, so the same payment can't be
 * claimed twice by pasting it with/without "0x", in different case, or as a
 * block-explorer link — and a check that it hasn't already been claimed by
 * any order payment or wallet deposit.
 */
class TxHash
{
    public static function canonical(string $input, string $network): string
    {
        $input = trim($input);

        // Pull the 64-hex hash out of whatever was pasted (a bare hash, or a
        // BscScan/Tronscan/explorer URL containing it).
        $hex = preg_match('/(?:0x)?([0-9a-fA-F]{64})/', $input, $m)
            ? strtolower($m[1])
            : preg_replace('/^0x/i', '', strtolower($input));

        // EVM chains (BEP20) write hashes with 0x; Tron and Litecoin don't.
        return $network === 'BEP20' ? '0x'.$hex : $hex;
    }

    public static function alreadyUsed(string $canonicalHash, ?Model $except = null): bool
    {
        $payments = CryptoPayment::where('tx_hash', $canonicalHash);
        $deposits = WalletDeposit::where('tx_hash', $canonicalHash);

        if ($except instanceof CryptoPayment) {
            $payments->whereKeyNot($except->getKey());
        }

        if ($except instanceof WalletDeposit) {
            $deposits->whereKeyNot($except->getKey());
        }

        return $payments->exists() || $deposits->exists();
    }
}
