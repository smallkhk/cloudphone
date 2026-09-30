<?php

namespace App\Services\Provisioning;

use App\Models\CustomerProxy;
use App\Models\Order;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Turns a paid Order for a Sku::TYPE_PROXY into a real, standalone VMOS
 * static-residential-proxy purchase the customer can attach to any of their
 * own devices — not tied to a specific device order the way the checkout
 * proxy add-on (ProxyProvisioner, Order.proxy_*) is.
 *
 * Same async-purchase shape as that add-on, since it's the same underlying
 * VMOS endpoint (createProxyOrder): the purchase call only *accepts* and
 * returns a taskId, which must be polled via proxyOrderStatus() until
 * FINISHED (or NEEDS_REVIEW, which needs a human) before the proxy reliably
 * shows up in listStaticProxies(). provision() makes the initial call and an
 * immediate poll attempt; if not terminal yet, the order stays
 * `provisioning` and SyncCustomerProxyPurchases (every minute) retries
 * pollAndFinalize() until it resolves.
 *
 * VMOS still doesn't hand back a direct proxy id from the purchase call
 * itself, so the delivered proxy/proxies are found the same way
 * ProxyProvisioner does: the newest unused, country-matching entries in
 * listStaticProxies(), excluding anything already claimed either by another
 * standalone purchase (CustomerProxy) or by the device-checkout add-on
 * (Order.proxy_config.matched_proxy_id) — both draw from the same VMOS proxy
 * inventory pool. If fewer than were paid for can be positively matched,
 * this deliberately does NOT guess on the remainder — same rule as
 * ProxyProvisioner: it's real, paid-for inventory, so a human resolves it
 * rather than risk attaching the wrong proxy to the wrong customer.
 */
class StandaloneProxyProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function provision(Order $order): void
    {
        $order->loadMissing('sku');
        $sku = $order->sku;

        $order->update(['status' => Order::STATUS_PROVISIONING]);

        // Stable per order — a retried provision() call reuses the same
        // idempotency key rather than buying a second batch of proxies.
        $clientRequestId = 'order-'.$order->id.'-standalone-proxy';

        try {
            $response = $this->vmos->buyStaticProxy(
                proxyGoodId: (int) $sku->vmos_good_id,
                country: (string) $sku->default_country_code,
                proxyAddress: (string) $sku->default_country_code,
                clientRequestId: $clientRequestId,
                num: $order->quantity,
                autoRenew: false,
            );

            $taskId = $response['data']['taskId'] ?? null;

            if (! $taskId) {
                throw new RuntimeException('The provider accepted the purchase without a tracking id, so it cannot be confirmed automatically.');
            }

            $order->update(['vmos_order_id' => $taskId]);

            $this->pollAndFinalize($order->fresh());
        } catch (Throwable $e) {
            Log::error('standalone_proxy_provisioning.purchase_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update(['status' => Order::STATUS_FAILED, 'error_message' => $e->getMessage()]);
        }
    }

    /** Called by SyncCustomerProxyPurchases for an order still awaiting a terminal purchase status. */
    public function pollAndFinalize(Order $order): void
    {
        $order->loadMissing('sku');

        if (! $order->vmos_order_id || $order->status !== Order::STATUS_PROVISIONING) {
            return;
        }

        try {
            $status = $this->vmos->proxyOrderStatus($order->vmos_order_id)['data']['status'] ?? null;
        } catch (Throwable $e) {
            Log::error('standalone_proxy_provisioning.poll_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return;
        }

        if ($status === 'NEEDS_REVIEW') {
            $order->update([
                'status' => Order::STATUS_FAILED,
                'error_message' => 'Purchase needs manual review on the provider side — contact support; it will not be automatically resubmitted.',
            ]);

            return;
        }

        if ($status !== 'FINISHED') {
            // PENDING/PROCESSING — polled again next pass.
            return;
        }

        $this->recordDeliveredProxies($order);
    }

    protected function recordDeliveredProxies(Order $order): void
    {
        $sku = $order->sku;

        try {
            $owned = collect($this->vmos->listStaticProxies(size: 100)['data']['records'] ?? []);
        } catch (Throwable $e) {
            Log::error('standalone_proxy_provisioning.list_lookup_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return; // Stays `provisioning` — retried next pass.
        }

        $claimed = array_merge(CustomerProxy::claimedVmosProxyIds(), $this->legacyClaimedProxyIds());

        $matched = $owned
            ->filter(fn ($p) => (int) ($p['proxyUseNumber'] ?? 1) === 0)
            ->filter(fn ($p) => strcasecmp((string) ($p['proxyCountry'] ?? ''), (string) $sku->default_country_code) === 0)
            ->reject(fn ($p) => in_array($p['proxyId'] ?? null, $claimed, true))
            ->values()
            ->take($order->quantity);

        DB::transaction(function () use ($matched, $order, $sku) {
            foreach ($matched as $proxy) {
                CustomerProxy::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'sku_id' => $sku->id,
                    'vmos_proxy_id' => $proxy['proxyId'] ?? null,
                    'host' => $proxy['proxyHost'] ?? null,
                    'port' => $proxy['proxyPort'] ?? null,
                    'account' => $proxy['account'] ?? null,
                    'country_code' => $sku->default_country_code,
                    'purchase_client_token' => $order->vmos_order_id,
                    'purchase_status' => CustomerProxy::PURCHASE_COMPLETED,
                    'raw_payload' => ['list_record' => $proxy],
                    'delivered_at' => now(),
                ]);
            }
        });

        if ($matched->count() >= $order->quantity) {
            $order->update(['status' => Order::STATUS_COMPLETED, 'provisioned_at' => now()]);

            return;
        }

        $order->update([
            'status' => Order::STATUS_FAILED,
            'error_message' => $matched->isEmpty()
                ? "Bought but couldn't be positively matched to your inventory yet — contact support, it's paid for and just needs a human to locate it."
                : "Bought {$order->quantity}, but only {$matched->count()} could be positively matched — contact support about the rest; they're paid for.",
        ]);
    }

    /** Proxy ids already claimed by the device-checkout add-on — see ProxyProvisioner. */
    protected function legacyClaimedProxyIds(): array
    {
        return Order::query()
            ->whereNotNull('proxy_config')
            ->get(['proxy_config'])
            ->map(fn (Order $o) => $o->proxy_config['matched_proxy_id'] ?? null)
            ->filter()
            ->all();
    }
}
