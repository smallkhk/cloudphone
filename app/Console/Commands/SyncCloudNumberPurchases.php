<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Sku;
use App\Services\Provisioning\CloudNumberProvisioner;
use Illuminate\Console\Command;

/**
 * Cloud Number purchases are async on VMOS's side (see CloudNumberProvisioner)
 * — this finishes off any order still awaiting a terminal purchase status.
 */
class SyncCloudNumberPurchases extends Command
{
    protected $signature = 'vmos:sync-cloud-number-purchases';

    protected $description = 'Poll still-processing Cloud Number purchases and finalize them once VMOS reports a terminal status';

    public function handle(CloudNumberProvisioner $provisioner): int
    {
        $pending = Order::query()
            ->where('status', Order::STATUS_PROVISIONING)
            ->whereHas('sku', fn ($q) => $q->where('type', Sku::TYPE_CLOUD_NUMBER))
            ->whereNotNull('vmos_order_id')
            ->get();

        foreach ($pending as $order) {
            $provisioner->pollAndFinalize($order);
        }

        $this->info("Checked {$pending->count()} pending cloud number purchase(s).");

        return self::SUCCESS;
    }
}
