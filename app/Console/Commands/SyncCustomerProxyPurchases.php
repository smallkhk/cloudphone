<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Sku;
use App\Services\Provisioning\StandaloneProxyProvisioner;
use Illuminate\Console\Command;

/**
 * Standalone proxy purchases are async on VMOS's side (see
 * StandaloneProxyProvisioner) — this finishes off any order still awaiting a
 * terminal purchase status.
 */
class SyncCustomerProxyPurchases extends Command
{
    protected $signature = 'vmos:sync-customer-proxy-purchases';

    protected $description = 'Poll still-processing standalone proxy purchases and finalize them once VMOS reports a terminal status';

    public function handle(StandaloneProxyProvisioner $provisioner): int
    {
        $pending = Order::query()
            ->where('status', Order::STATUS_PROVISIONING)
            ->whereHas('sku', fn ($q) => $q->where('type', Sku::TYPE_PROXY))
            ->whereNotNull('vmos_order_id')
            ->get();

        foreach ($pending as $order) {
            $provisioner->pollAndFinalize($order);
        }

        $this->info("Checked {$pending->count()} pending standalone proxy purchase(s).");

        return self::SUCCESS;
    }
}
