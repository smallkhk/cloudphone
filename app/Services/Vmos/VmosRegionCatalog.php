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
    /**
     * Exactly what VMOS's own "Select Region" purchase selector shows.
     * Update (2026-09): VMOS expanded this from 10 to 18 regions — confirmed
     * from a fresh console screenshot, adding Vietnam, Malaysia, France,
     * Italy, Spain, Thailand, the UK and Australia. Update by hand if VMOS
     * adds more.
     */
    public const PURCHASE_REGIONS = [
        'HK' => 'Hong Kong',
        'US' => 'United States',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'DE' => 'Germany',
        'SG' => 'Singapore',
        'BR' => 'Brazil',
        'VN' => 'Vietnam',
        'ID' => 'Indonesia',
        'MY' => 'Malaysia',
        'TW' => 'Taiwan',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'TH' => 'Thailand',
        'GB' => 'United Kingdom',
        'PH' => 'Philippines',
        'AU' => 'Australia',
    ];

    /**
     * Extra common markets seen in VMOS's wider (but unconfirmed-as-a-fixed-
     * list) proxy/SIM region data, beyond the 18 confirmed device-purchase
     * regions above — used only for display labels, never for validation.
     */
    protected const EXTRA_NAMES = [
        'NG' => 'Nigeria', 'ZA' => 'South Africa', 'IN' => 'India',
        'AE' => 'United Arab Emirates', 'CA' => 'Canada', 'MX' => 'Mexico',
        'NL' => 'Netherlands', 'PL' => 'Poland', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'AR' => 'Argentina', 'CO' => 'Colombia', 'CL' => 'Chile', 'EG' => 'Egypt',
        'SA' => 'Saudi Arabia', 'TR' => 'Turkey', 'RU' => 'Russia', 'PK' => 'Pakistan',
        'BD' => 'Bangladesh', 'NZ' => 'New Zealand', 'PT' => 'Portugal', 'BE' => 'Belgium',
        'AT' => 'Austria', 'IE' => 'Ireland', 'DK' => 'Denmark', 'NO' => 'Norway',
        'FI' => 'Finland', 'GR' => 'Greece', 'CZ' => 'Czechia', 'RO' => 'Romania',
        'IL' => 'Israel', 'KE' => 'Kenya', 'MA' => 'Morocco', 'CN' => 'China',
    ];

    public function __construct(protected VmosCloudPhoneService $vmos) {}

    /** Best-effort full country name for a 2-letter code — falls back to the code itself if unknown. Display only. */
    public static function nameFor(?string $code): string
    {
        $code = strtoupper((string) $code);

        return self::PURCHASE_REGIONS[$code] ?? self::EXTRA_NAMES[$code] ?? $code;
    }

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
