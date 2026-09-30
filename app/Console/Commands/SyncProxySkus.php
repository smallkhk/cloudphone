<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls VMOS's static residential proxy products + regions and upserts one
 * Sku per (product, country) combination — the same two-axis catalogue the
 * checkout proxy add-on (OrderController::resolveProxy) already reads live,
 * just synced into the skus table so a standalone purchase can be priced and
 * browsed the normal way instead of building a bespoke picker.
 */
class SyncProxySkus extends Command
{
    protected $signature = 'vmos:sync-proxy-skus';

    protected $description = 'Pull the VMOS static proxy catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            $this->error('VMOS credentials are not set. Add them under Admin → Settings → VMOS.');

            return self::FAILURE;
        }

        try {
            $products = $vmos->staticProxyGoods()['data'] ?? [];
            $regions = $vmos->staticProxyRegions()['data'] ?? [];
        } catch (Throwable $e) {
            $this->error('VMOS request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($products) || empty($regions)) {
            $this->warn('VMOS returned no proxy products or regions. Check Admin → Diagnostics for the raw response.');

            return self::SUCCESS;
        }

        $seen = 0;
        $created = 0;
        $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);

        foreach ($regions as $region) {
            $countryCode = strtoupper((string) ($region['country'] ?? ''));

            if ($countryCode === '') {
                continue;
            }

            $countryLabel = $region['countryZh'] ?? $countryCode;

            foreach ($products as $product) {
                $goodId = $product['proxyGoodId'] ?? null;

                if ($goodId === null) {
                    continue;
                }

                $sku = Sku::firstOrNew([
                    'type' => Sku::TYPE_PROXY,
                    'vmos_good_id' => $goodId,
                    // Distinct per country, same reasoning as Sku::TYPE_CLOUD_NUMBER's
                    // android_version placeholder — see Sku::TYPE_PROXY's docblock.
                    'android_version' => 'px-'.strtolower($countryCode),
                ]);

                $isNew = ! $sku->exists;
                $cost = round(((float) ($product['proxyGoodPrice'] ?? 0)) / 100, 2);

                $sku->fill([
                    'vmos_config_id' => 0,
                    'name' => ($product['proxyGoodName'] ?? 'Static proxy')." — {$countryLabel}",
                    'config_model' => $countryCode,
                    'default_country_code' => $countryCode,
                    'duration_label' => $product['proxyGoodName'] ?? null,
                    'duration_minutes' => 0,
                    'vmos_cost_price' => $cost,
                    'sell_out' => false,
                    'raw_payload' => ['product' => $product, 'region' => $region],
                    'synced_at' => now(),
                ]);

                if ($isNew) {
                    $sku->price = round($cost * $markup, 2);
                    $sku->active = true;
                    $created++;
                }

                $sku->save();
                $seen++;
            }
        }

        $this->info("Synced {$seen} proxy plan(s) — {$created} new.");

        return self::SUCCESS;
    }
}
