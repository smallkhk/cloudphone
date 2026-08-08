<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;
use Throwable;

class SyncCloudSkus extends Command
{
    protected $signature = 'vmos:sync-skus
        {--android-versions= : Comma-separated Android versions; omit to let VMOS return everything}';

    protected $description = 'Pull the VMOS SKU/package catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            $this->error('VMOS credentials are not set. Add them under Admin → Settings → VMOS.');

            return self::FAILURE;
        }

        // An explicit list is opt-in; by default ask VMOS for the whole catalogue
        // so nothing is missed just because we guessed the wrong version numbers.
        $option = (string) $this->option('android-versions');
        $versions = filled($option) ? array_filter(explode(',', $option)) : [null];

        $seen = 0;
        $created = 0;
        $configsFound = 0;

        foreach ($versions as $version) {
            try {
                $response = $vmos->listGoods(androidVersion: $version !== null ? (int) $version : null);
            } catch (Throwable $e) {
                $this->error('VMOS request failed'.($version ? " for Android {$version}" : '').': '.$e->getMessage());

                return self::FAILURE;
            }

            $configs = $response['data']['configs'] ?? [];
            $configsFound += count($configs);

            if (empty($configs)) {
                $label = $version ? "Android {$version}" : 'the default catalogue';
                $this->warn("VMOS returned no device configurations for {$label}.");

                continue;
            }

            foreach ($configs as $config) {
                $goodTimes = $config['goodTimes'] ?? [];

                if (empty($goodTimes)) {
                    $this->warn("Config '".($config['configName'] ?? '?')."' has no purchasable durations — skipped.");

                    continue;
                }

                foreach ($goodTimes as $goodTime) {
                    if (empty($goodTime['id'])) {
                        continue;
                    }

                    $cost = $this->normalisePrice($goodTime['currentPrice'] ?? $goodTime['goodPrice'] ?? 0);

                    $sku = Sku::firstOrNew([
                        'vmos_good_id' => $goodTime['id'],
                        'android_version' => (string) ($config['androidVersion'] ?? $version ?? ''),
                    ]);

                    $isNew = ! $sku->exists;

                    $sku->fill([
                        'vmos_config_id' => $config['configId'] ?? 0,
                        'name' => $config['configName'] ?? 'Cloud Phone',
                        'config_model' => $config['configModel'] ?? null,
                        'duration_label' => $goodTime['showContent'] ?? null,
                        'duration_minutes' => $goodTime['goodTime'] ?? 0,
                        'vmos_cost_price' => $cost,
                        'sell_out' => (bool) ($config['sellOutFlag'] ?? false),
                        'raw_payload' => ['config' => array_diff_key($config, ['goodTimes' => null]), 'goodTime' => $goodTime],
                        'synced_at' => now(),
                    ]);

                    // Only seed the selling price on first import — re-syncing must
                    // never overwrite prices the admin has set by hand (this runs
                    // hourly on a schedule).
                    if ($isNew) {
                        $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);
                        $sku->price = round($cost * $markup, 2);
                        $sku->active = true;
                        $created++;
                    }

                    $sku->save();

                    $seen++;
                }
            }
        }

        if ($configsFound === 0) {
            $this->error('No device configurations came back from VMOS at all. Check Admin → Diagnostics to see the raw API response.');

            return self::FAILURE;
        }

        $this->info("Synced {$seen} plan(s) from {$configsFound} device configuration(s) — {$created} new.");

        return self::SUCCESS;
    }

    /**
     * VMOS quotes proxy prices in cents and its plan prices appear to use the
     * same minor unit, so a "$4.99/month" package arrives as 499. Dividing
     * blindly would be wrong if an account is quoted in whole units, so the
     * unit is configurable and defaults to cents.
     */
    protected function normalisePrice(mixed $raw): float
    {
        $value = (float) $raw;

        return Setting::get('vmos_price_unit', 'cents') === 'cents'
            ? round($value / 100, 2)
            : round($value, 2);
    }
}
