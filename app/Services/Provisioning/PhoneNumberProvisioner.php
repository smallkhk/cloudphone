<?php

namespace App\Services\Provisioning;

use App\Models\Order;
use App\Models\PhoneNumber;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a paid Order for a phone-number Sku into a real VMOS SMS-number
 * purchase, and records the delivered number locally.
 *
 * The underlying VMOS endpoint (createSmsOrder) is UNCONFIRMED — it doesn't
 * appear in VMOS's published API docs, only inferred by mirroring the email
 * endpoints' naming (see VmosCloudPhoneService). Sku::TYPE_PHONE_NUMBER SKUs
 * are synced hidden (active=false) for exactly this reason: nothing reaches
 * this provisioner for a real customer until an admin has confirmed the
 * endpoint works via Admin → Diagnostics and deliberately made a SKU live.
 */
class PhoneNumberProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos)
    {
    }

    public function provision(Order $order): void
    {
        $order->loadMissing('sku');
        $sku = $order->sku;

        $order->update(['status' => Order::STATUS_PROVISIONING]);

        try {
            $response = $this->vmos->createSmsOrder(
                smsTypeId: $sku->vmos_good_id,
                num: $order->quantity,
            );

            $entries = $response['data'] ?? [];

            DB::transaction(function () use ($order, $sku, $entries) {
                foreach ($entries as $entry) {
                    PhoneNumber::create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'sku_id' => $sku->id,
                        'vmos_order_id' => $entry['orderId'] ?? $entry['id'] ?? null,
                        'phone_number' => $entry['phoneNumber'] ?? $entry['phone'] ?? $entry['number'] ?? '(unknown)',
                        'country_code' => $entry['countryCode'] ?? $entry['country'] ?? $sku->default_country_code,
                        'latest_code' => $entry['code'] ?? $entry['verifyCode'] ?? $entry['verificationCode'] ?? null,
                        'raw_payload' => $entry,
                        'delivered_at' => now(),
                    ]);
                }

                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'vmos_order_id' => $entries[0]['orderId'] ?? null,
                    'provisioned_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('phone_provisioning.failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update([
                'status' => Order::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
