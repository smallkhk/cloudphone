<?php

namespace App\Services\Provisioning;

use App\Models\CloudNumber;
use App\Models\Order;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a paid Order for a Sku::TYPE_CLOUD_NUMBER into a real VMOS Cloud
 * Number purchase.
 *
 * Unlike every other provisioner here, VMOS's own purchase call
 * (buyCloudNumber) is asynchronous AND idempotent: it only *accepts* the
 * purchase and returns a status that may not be terminal yet (PROCESSING/
 * UNKNOWN), so this can't just buy-and-record in one shot the way
 * PhoneNumberProvisioner does. provision() makes the initial call; if it's
 * not terminal yet, the order is left `provisioning` and
 * SyncCloudNumberPurchases (scheduled every minute) polls
 * cloudNumberPurchaseStatus() with the same clientToken until it resolves.
 * Both paths funnel through finalize() so the outcome is handled identically
 * either way.
 *
 * VMOS's purchase response hands back the delivered number *strings* but not
 * their internal record id (needed later for bind/autoRenew) — same
 * "doesn't hand back a usable id" situation as static proxies, except here
 * matching is exact (by number string) rather than a fuzzy heuristic, so
 * there's no ambiguity to worry about, just a lookup via cloudNumberList().
 */
class CloudNumberProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function provision(Order $order): void
    {
        $order->loadMissing('sku');
        $sku = $order->sku;

        $order->update(['status' => Order::STATUS_PROVISIONING]);

        // Stable per order — a retried provision() call reuses the same
        // token rather than buying a second batch of numbers.
        $clientToken = 'order-'.$order->id.'-cloudnumber';

        try {
            $response = $this->vmos->buyCloudNumber(
                countryCode: (string) $sku->default_country_code,
                planId: (int) $sku->vmos_good_id,
                clientToken: $clientToken,
                quantity: $order->quantity,
                autoRenew: true,
                expectedTotalCents: (int) round((float) $sku->vmos_cost_price * 100) * $order->quantity,
            );

            $this->finalize($order, $sku, $clientToken, $response['data'] ?? []);
        } catch (Throwable $e) {
            Log::error('cloud_number_provisioning.purchase_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update(['status' => Order::STATUS_FAILED, 'error_message' => $e->getMessage()]);
        }
    }

    /** Called by SyncCloudNumberPurchases for an order still awaiting a terminal purchase status. */
    public function pollAndFinalize(Order $order): void
    {
        $order->loadMissing('sku');

        if (! $order->vmos_order_id) {
            return;
        }

        try {
            $response = $this->vmos->cloudNumberPurchaseStatus($order->vmos_order_id);
            $this->finalize($order, $order->sku, $order->vmos_order_id, $response['data'] ?? []);
        } catch (Throwable $e) {
            Log::error('cloud_number_provisioning.poll_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function finalize(Order $order, $sku, string $clientToken, array $data): void
    {
        $status = $data['status'] ?? null;
        $terminal = (bool) ($data['terminal'] ?? false);

        // Not terminal yet — leave the order provisioning and the clientToken
        // recorded so SyncCloudNumberPurchases can pick it up next pass.
        if (! $terminal) {
            $order->update(['vmos_order_id' => $clientToken]);

            return;
        }

        if (in_array($status, ['COMPLETED', 'PARTIAL'], true) && ! empty($data['numbers'])) {
            $this->recordDeliveredNumbers($order, $sku, $clientToken, $data);

            $order->update([
                'status' => Order::STATUS_COMPLETED,
                'vmos_order_id' => $data['orderNo'] ?? $clientToken,
                'provisioned_at' => now(),
            ]);

            return;
        }

        // REJECTED / INSUFFICIENT_BALANCE / PRICE_CHANGED / REFUNDED / any
        // other terminal-but-nothing-delivered outcome. The customer already
        // paid us — this needs a human (refund or manual retry), same as a
        // failed VMOS proxy purchase.
        $order->update([
            'status' => Order::STATUS_FAILED,
            'vmos_order_id' => $data['orderNo'] ?? $clientToken,
            'error_message' => 'Cloud number purchase '.strtolower((string) $status).': '.($data['message'] ?? 'no further detail from the provider.'),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function recordDeliveredNumbers(Order $order, $sku, string $clientToken, array $data): void
    {
        $numbers = $data['numbers'] ?? [];

        // The purchase response gives back number strings but not their
        // internal record ids (needed for bind/autoRenew later) — look them
        // up by exact number match instead of guessing.
        $owned = collect();

        try {
            $owned = collect($this->vmos->cloudNumberList(countryCode: $sku->default_country_code, size: 100)['data']['records'] ?? []);
        } catch (Throwable $e) {
            Log::warning('cloud_number_provisioning.list_lookup_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        foreach ($numbers as $number) {
            $record = $owned->first(fn ($r) => (string) ($r['number'] ?? '') === (string) $number);

            CloudNumber::updateOrCreate(
                ['order_id' => $order->id, 'number' => $number],
                [
                    'user_id' => $order->user_id,
                    'sku_id' => $sku->id,
                    'vmos_number_id' => $record['id'] ?? null,
                    'country_code' => $sku->default_country_code,
                    'purchase_client_token' => $clientToken,
                    'purchase_status' => CloudNumber::PURCHASE_COMPLETED,
                    'auto_renew' => (bool) ($record['autoRenew'] ?? $data['autoRenew'] ?? true),
                    'row_version' => $record['rowVersion'] ?? null,
                    'vmos_status' => $record['status'] ?? 'normal',
                    'expire_time' => $record['expireTime'] ?? null,
                    'raw_payload' => ['purchase' => $data, 'list_record' => $record],
                    'delivered_at' => now(),
                ]
            );
        }
    }
}
