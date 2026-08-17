<?php

namespace App\Services\Vmos;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cached list of countries VMOS supports (…/padApi/country), used everywhere
 * a customer picks a region: SIM regeneration on an existing device, and
 * (checkout) which region a new cloud phone is provisioned in.
 *
 * Pulled out of DeviceControlController, which had this exact logic already —
 * one cached source instead of guessing the response shape twice.
 */
class VmosRegionCatalog
{
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
}
