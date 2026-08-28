<?php

namespace App\Console\Commands;

use App\Models\WalletDeposit;
use App\Models\WalletTransaction;
use App\Services\Payments\BscUsdtVerifier;
use App\Services\Payments\TronUsdtVerifier;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyWalletDeposits extends Command
{
    protected $signature = 'wallet:verify-deposits';

    protected $description = 'Check submitted wallet deposits on-chain and credit confirmed ones to the customer\'s balance';

    public function handle(TronUsdtVerifier $tron, BscUsdtVerifier $bsc, WalletService $wallet): int
    {
        $submitted = WalletDeposit::query()
            ->where('status', WalletDeposit::STATUS_SUBMITTED)
            ->whereIn('network', ['TRC20', 'BEP20'])
            ->get();

        foreach ($submitted as $deposit) {
            try {
                $verifier = $deposit->network === 'BEP20' ? $bsc : $tron;

                if ($verifier->verifyTransfer($deposit->tx_hash, $deposit->pay_to_address, (float) $deposit->amount_crypto)) {
                    $deposit->update([
                        'status' => WalletDeposit::STATUS_CONFIRMED,
                        'confirmed_at' => now(),
                    ]);

                    $wallet->credit(
                        $deposit->user,
                        (float) $deposit->amount_usd,
                        WalletTransaction::TYPE_DEPOSIT,
                        "Wallet top-up via {$deposit->network}",
                        deposit: $deposit,
                    );

                    $this->info("Confirmed deposit #{$deposit->id} and credited {$deposit->user->email}.");
                }
            } catch (Throwable $e) {
                Log::error('wallet.verify_deposit_failed', ['deposit_id' => $deposit->id, 'error' => $e->getMessage()]);
                $this->error("Failed verifying deposit #{$deposit->id}: {$e->getMessage()}");
            }
        }

        // Expire quotes nobody paid in time.
        WalletDeposit::query()
            ->where('status', WalletDeposit::STATUS_AWAITING_PAYMENT)
            ->where('expires_at', '<', now())
            ->update(['status' => WalletDeposit::STATUS_EXPIRED]);

        return self::SUCCESS;
    }
}
