<?php

namespace App\Console\Commands;

use App\Models\CryptoPayment;
use App\Models\Order;
use App\Services\Payments\BscUsdtVerifier;
use App\Services\Payments\TronUsdtVerifier;
use App\Services\Provisioning\OrderProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyCryptoPayments extends Command
{
    protected $signature = 'crypto:verify-payments';

    protected $description = 'Check submitted crypto payments on-chain, confirm them, and provision paid orders';

    public function handle(TronUsdtVerifier $tron, BscUsdtVerifier $bsc, OrderProvisioner $provisioner): int
    {
        $submitted = CryptoPayment::query()
            ->where('status', CryptoPayment::STATUS_SUBMITTED)
            ->whereIn('network', ['TRC20', 'BEP20'])
            ->get();

        foreach ($submitted as $payment) {
            try {
                $verifier = $payment->network === 'BEP20' ? $bsc : $tron;

                if ($verifier->verify($payment)) {
                    $payment->update([
                        'status' => CryptoPayment::STATUS_CONFIRMED,
                        'confirmed_at' => now(),
                    ]);

                    $order = $payment->order;
                    $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

                    $provisioner->provision($order->fresh());

                    $this->info("Confirmed payment #{$payment->id} and provisioned order {$order->reference}.");
                }
            } catch (Throwable $e) {
                Log::error('crypto.verify_payment_failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
                $this->error("Failed verifying payment #{$payment->id}: {$e->getMessage()}");
            }
        }

        // Expire quotes nobody paid in time.
        CryptoPayment::query()
            ->where('status', CryptoPayment::STATUS_AWAITING_PAYMENT)
            ->where('expires_at', '<', now())
            ->update(['status' => CryptoPayment::STATUS_EXPIRED]);

        return self::SUCCESS;
    }
}
