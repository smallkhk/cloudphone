<?php

namespace App\Services\Vmos;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cached VMOS static residential proxy products + regions, used to let a
 * customer buy a proxy add-on at checkout (Admin → Proxies already has this
 * exact purchase flow for admin-initiated buys; this is the same data feeding
 * the customer-facing one).
 */
class VmosProxyCatalog
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    /** @return list<array<string, mixed>> raw proxyGoodId/proxyGoodName/proxyGoodPrice/proxyGoodType entries */
    public function products(): array
    {
        return Cache::remember('vmos.proxy_products', now()->addHours(6), function () {
            try {
                return $this->vmos->staticProxyGoods()['data'] ?? [];
            } catch (Throwable) {
                return [];
            }
        });
    }

    /** @return list<array<string, mixed>> raw country/countryZh entries */
    public function regions(): array
    {
        return Cache::remember('vmos.proxy_regions', now()->addDay(), function () {
            try {
                return $this->vmos->staticProxyRegions()['data'] ?? [];
            } catch (Throwable) {
                return [];
            }
        });
    }
}
