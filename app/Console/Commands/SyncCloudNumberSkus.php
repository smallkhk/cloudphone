<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls VMOS's Cloud Number catalogue (one call returns every country's
 * plans + live stock at once — see VmosCloudPhoneService::cloudNumberSkus).
 *
 * Note: for the US specifically, cloudNumberSkus() without an areaCode only
 * returns the 213 area code's plans (VMOS's own default) — the other three
 * supported codes (480/702/813) are never synced. Low priority to fix unless
 * US demand shows up; would just mean looping areaCode too.
 */
class SyncCloudNumberSkus extends Command
{
    protected $signature = 'vmos:sync-cloud-number-skus';

    protected $description = 'Pull the VMOS Cloud Number catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            $this->error('VMOS credentials are not set. Add them under Admin → Settings → VMOS.');

            return self::FAILURE;
        }

        try {
            $response = $vmos->cloudNumberSkus();
        } catch (Throwable $e) {
            $this->error('VMOS request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $countries = $response['data'] ?? [];

        if (empty($countries)) {
            $this->warn('VMOS returned no cloud number countries. Check Admin → Diagnostics for the raw response.');

            return self::SUCCESS;
        }

        $seen = 0;
        $created = 0;
        $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);

        foreach ($countries as $country) {
            $countryCode = strtoupper((string) ($country['countryCode'] ?? ''));

            if ($countryCode === '') {
                continue;
            }

            $available = ($country['status'] ?? null) === 'AVAILABLE';

            foreach ($country['skus'] ?? [] as $plan) {
                $planId = $plan['planId'] ?? null;

                if ($planId === null) {
                    continue;
                }

                $sku = Sku::firstOrNew([
                    'type' => Sku::TYPE_CLOUD_NUMBER,
                    'vmos_good_id' => $planId,
                    // Distinct per country so this table's existing
                    // unique(vmos_good_id, android_version) index can't
                    // collide a planId that repeats across countries — see
                    // Sku::TYPE_CLOUD_NUMBER's docblock.
                    'android_version' => 'cn-'.strtolower($countryCode),
                ]);

                $isNew = ! $sku->exists;
                $days = (int) ($plan['durationDays'] ?? 0);

                $sku->fill([
                    'vmos_config_id' => 0,
                    'name' => "Cloud Number — {$countryCode}",
                    'config_model' => $countryCode,
                    'default_country_code' => $countryCode,
                    'duration_label' => $days === 1 ? '1 day' : "{$days} days",
                    'duration_minutes' => $days * 1440,
                    'vmos_cost_price' => round(((int) ($plan['chargeCents'] ?? 0)) / 100, 2),
                    'sell_out' => ! $available,
                    'raw_payload' => ['country' => $country, 'plan' => $plan],
                    'synced_at' => now(),
                ]);

                if ($isNew) {
                    $sku->price = round((float) $sku->vmos_cost_price * $markup, 2);
                    $sku->active = true;
                    $created++;
                }

                $sku->save();
                $seen++;
            }
        }

        $this->info("Synced {$seen} cloud number plan(s) — {$created} new.");

        return self::SUCCESS;
    }
}
