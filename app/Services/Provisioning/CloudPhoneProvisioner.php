<?php

namespace App\Services\Provisioning;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a paid Order into a real VMOS cloud phone purchase, and records the
 * resulting equipment/instances locally so the customer can see & manage them.
 *
 * Note: createMoneyOrder only returns an equipmentId; the padCode is assigned
 * once VMOS finishes provisioning and is filled in later by SyncCloudInstances
 * (or the VMOS callback webhook).
 */
class CloudPhoneProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos, protected ProxyProvisioner $proxies) {}

    public function provision(Order $order): void
    {
        $order->loadMissing('sku');
        $sku = $order->sku;

        $order->update(['status' => Order::STATUS_PROVISIONING]);

        try {
            $response = $this->vmos->createOrder(
                goodId: $sku->vmos_good_id,
                goodNum: $order->quantity,
                androidVersionName: 'Android'.$sku->android_version,
                autoRenew: $order->auto_renew ?? true,
                countryCode: $order->country_code ?? $sku->default_country_code,
            );

            $entries = $response['data'] ?? [];

            DB::transaction(function () use ($order, $sku, $entries) {
                foreach ($entries as $entry) {
                    CloudInstance::create([
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'sku_id' => $sku->id,
                        'equipment_id' => $entry['equipmentId'] ?? null,
                        'auto_renew' => $order->auto_renew,
                    ]);
                }

                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'vmos_order_id' => $entries[0]['orderId'] ?? null,
                    'vmos_equipment_id' => collect($entries)->pluck('equipmentId')->filter()->implode(','),
                    'provisioned_at' => now(),
                ]);
            });

            // A proxy add-on failing must never take the device order down with
            // it — the customer paid for a phone above all else, and gets one
            // regardless of whether the proxy purchase succeeds.
            if ($order->hasProxy()) {
                $this->proxies->purchase($order->fresh());
            }
        } catch (Throwable $e) {
            Log::error('provisioning.failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update([
                'status' => Order::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
