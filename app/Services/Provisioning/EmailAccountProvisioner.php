<?php

namespace App\Services\Provisioning;

use App\Models\EmailAccount;
use App\Models\Order;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a paid Order for an email-account Sku into a real VMOS email purchase,
 * and records the delivered address/password locally.
 *
 * VMOS doesn't publish the exact response shape for createEmailOrder, so this
 * reads several plausible key names defensively and always keeps the full raw
 * entry in EmailAccount::raw_payload — if the guesses are wrong, the data is
 * still there to fix up without re-buying.
 */
class EmailAccountProvisioner
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
            $response = $this->vmos->createEmailOrder(
                emailTypeId: $sku->vmos_good_id,
                num: $order->quantity,
            );

            $entries = $response['data'] ?? [];

            DB::transaction(function () use ($order, $sku, $entries) {
                foreach ($entries as $entry) {
                    EmailAccount::create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'sku_id' => $sku->id,
                        'vmos_order_id' => $entry['orderId'] ?? $entry['id'] ?? null,
                        'email' => $entry['email'] ?? $entry['emailAddress'] ?? $entry['account'] ?? '(unknown)',
                        'password' => $entry['password'] ?? $entry['pwd'] ?? null,
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
            Log::error('email_provisioning.failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update([
                'status' => Order::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
