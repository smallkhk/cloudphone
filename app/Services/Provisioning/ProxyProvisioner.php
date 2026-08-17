<?php

namespace App\Services\Provisioning;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies an order's proxy add-on (picked at checkout) to its newly
 * provisioned device — either the customer's own proxy, or a VMOS residential
 * proxy bought alongside it.
 *
 * Two separate steps, because VMOS's own APIs force it:
 *
 * 1. purchase() — runs at device-provisioning time (CloudPhoneProvisioner),
 *    right after the device order itself. Buying a VMOS proxy doesn't need a
 *    padCode, so this can happen immediately. A failure here does NOT fail
 *    the device order — the customer still gets their phone; the proxy
 *    add-on is flagged failed and (if paid for) is the owner's to refund or
 *    retry manually.
 * 2. apply() — runs once the device's padCode is known (hooked into
 *    SyncCloudInstances, the same place padCode itself gets filled in), since
 *    both setCustomProxy and attachProxies require one.
 *
 * VMOS's createProxyOrder response doesn't document returning a usable proxy
 * ID the way createMoneyOrder returns an equipmentId — Admin → Proxies' own
 * purchase flow never uses one, it just re-lists owned proxies afterward. So
 * apply() for a VMOS proxy has to positively *find* the just-bought proxy in
 * that list (unused, matching country) rather than reference it directly. If
 * that match is ever ambiguous, this deliberately does NOT guess — it flags
 * the order for the admin to attach manually from Admin → Proxies, where the
 * existing working attach flow already lives.
 */
class ProxyProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function purchase(Order $order): void
    {
        if ($order->proxy_mode !== Order::PROXY_MODE_VMOS) {
            if ($order->proxy_mode === Order::PROXY_MODE_CUSTOM) {
                $order->update(['proxy_status' => Order::PROXY_STATUS_PENDING]);
            }

            return;
        }

        $config = $order->proxy_config ?? [];

        try {
            $response = $this->vmos->buyStaticProxy(
                proxyGoodId: (int) $config['good_id'],
                country: (string) $config['country'],
                proxyAddress: (string) $config['proxy_address'],
                num: 1,
                autoRenew: false,
            );

            $order->update([
                'proxy_status' => Order::PROXY_STATUS_PURCHASED,
                'proxy_config' => array_merge($config, ['purchase_response' => $response['data'] ?? null]),
            ]);
        } catch (Throwable $e) {
            Log::error('proxy_provisioning.purchase_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            $order->update([
                'proxy_status' => Order::PROXY_STATUS_FAILED,
                'proxy_error' => 'Purchase failed: '.$e->getMessage(),
            ]);
        }
    }

    /** Called once $instance->pad_code is known. Safe to call repeatedly — no-ops once attached. */
    public function apply(CloudInstance $instance): void
    {
        $order = $instance->order;

        if (! $order || ! $instance->pad_code || in_array($order->proxy_status, [null, Order::PROXY_STATUS_ATTACHED], true)) {
            return;
        }

        try {
            match ($order->proxy_mode) {
                Order::PROXY_MODE_CUSTOM => $this->applyCustom($instance, $order),
                Order::PROXY_MODE_VMOS => $this->applyVmos($instance, $order),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('proxy_provisioning.apply_failed', ['order_id' => $order->id, 'instance_id' => $instance->id, 'error' => $e->getMessage()]);

            $order->update([
                'proxy_status' => Order::PROXY_STATUS_FAILED,
                'proxy_error' => 'Could not attach: '.$e->getMessage(),
            ]);
        }
    }

    protected function applyCustom(CloudInstance $instance, Order $order): void
    {
        $config = $order->proxy_config ?? [];

        $this->vmos->setCustomProxy(
            padCodes: [$instance->pad_code],
            ip: (string) $config['ip'],
            port: (int) $config['port'],
            account: $config['account'] ?? null,
            password: $config['password'] ?? null,
            proxyName: $config['proxy_name'] ?? 'socks5',
            proxyType: $config['proxy_type'] ?? 'proxy',
        );

        $order->update(['proxy_status' => Order::PROXY_STATUS_ATTACHED]);
    }

    protected function applyVmos(CloudInstance $instance, Order $order): void
    {
        if ($order->proxy_status !== Order::PROXY_STATUS_PURCHASED) {
            // Not bought yet (or the purchase failed) — nothing to attach.
            return;
        }

        $config = $order->proxy_config ?? [];
        $proxyId = $this->findUnattachedProxy((string) ($config['country'] ?? ''));

        if (! $proxyId) {
            $order->update([
                'proxy_status' => Order::PROXY_STATUS_FAILED,
                'proxy_error' => "Bought but couldn't be positively matched to attach automatically — "
                    .'attach it manually from Admin → Proxies (the proxy is real and paid for, just needs a human to pick it).',
            ]);

            return;
        }

        $this->vmos->attachProxies([$instance->pad_code], [$proxyId]);

        $order->update([
            'proxy_status' => Order::PROXY_STATUS_ATTACHED,
            'proxy_config' => array_merge($config, ['matched_proxy_id' => $proxyId]),
        ]);
    }

    /**
     * Finds a VMOS-owned proxy that's unused and matches the purchased
     * region, excluding any proxy already claimed by another order — the
     * closest thing to "the one we just bought" without VMOS handing back a
     * direct ID.
     */
    protected function findUnattachedProxy(string $country): ?int
    {
        $owned = collect($this->vmos->listStaticProxies()['data']['records'] ?? []);

        $alreadyClaimed = Order::query()
            ->whereNotNull('proxy_config')
            ->get(['proxy_config'])
            ->map(fn (Order $o) => $o->proxy_config['matched_proxy_id'] ?? null)
            ->filter()
            ->all();

        $candidate = $owned
            ->filter(fn ($p) => (int) ($p['proxyUseNumber'] ?? 1) === 0)
            ->when($country !== '', fn ($c) => $c->filter(fn ($p) => strcasecmp((string) ($p['proxyCountry'] ?? ''), $country) === 0))
            ->reject(fn ($p) => in_array($p['proxyId'] ?? null, $alreadyClaimed, true))
            ->first();

        return $candidate['proxyId'] ?? null;
    }
}
