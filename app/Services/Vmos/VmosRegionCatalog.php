<?php

namespace App\Services\Vmos;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Region lists for two different jobs, which turned out NOT to be the same
 * list — confirmed from the owner's own VMOS console screenshots:
 *
 * - options(): the live …/padApi/country list. Used for SIM regeneration on
 *   an already-owned device, where VMOS genuinely does support a much wider
 *   set of countries than it sells NEW devices in.
 * - purchaseOptions(): the region picker VMOS's own "Buy/Renew" page
 *   actually shows when buying a device — a small fixed set. Calling
 *   options() there showed the customer dozens of regions VMOS's own
 *   purchase flow doesn't offer, which is exactly what got reported. There's
 *   no confirmed live endpoint that returns this specific list, so it's kept
 *   as a fixed list matching the real console rather than guessing at an API
 *   call that returns the wrong thing again.
 */
class VmosRegionCatalog
{
    /** Exactly what VMOS's own "Select Region" purchase selector shows. Update by hand if VMOS adds one. */
    public const PURCHASE_REGIONS = [
        'HK' => 'Hong Kong',
        'PH' => 'Philippines',
        'US' => 'United States',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'BR' => 'Brazil',
        'DE' => 'Germany',
        'SG' => 'Singapore',
        'ID' => 'Indonesia',
        'TW' => 'Taiwan',
    ];

    public function __construct(protected VmosCloudPhoneService $vmos) {}

    /** @return array<string, string> country code => name, cached since it rarely changes. */
    public function options(): array
    {
        return Cache::remember('vmos.countries', now()->addDay(), function () {
            try {
                return collect($this->vmos->countries()['data'] ?? [])
                    ->mapWithKeys(fn ($c) => [$c['code'] => $c['name']])
                    ->sort()
                    ->all();
            } catch (Throwable) {
                // Fall back to a common subset so forms still work offline.
                return [
                    'US' => 'United States', 'GB' => 'United Kingdom', 'HK' => 'Hong Kong',
                    'SG' => 'Singapore', 'NG' => 'Nigeria', 'ZA' => 'South Africa',
                    'IN' => 'India', 'PH' => 'Philippines', 'ID' => 'Indonesia',
                    'BR' => 'Brazil', 'DE' => 'Germany', 'FR' => 'France',
                    'AE' => 'United Arab Emirates', 'CA' => 'Canada', 'AU' => 'Australia',
                ];
            }
        });
    }

    /** @return array<string, string> country code => name — the checkout/purchase region list. */
    public function purchaseOptions(): array
    {
        return self::PURCHASE_REGIONS;
    }
}
