<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;

class SyncCloudSkus extends Command
{
    protected $signature = 'vmos:sync-skus {--android-versions=13,14 : Comma-separated Android versions to sync}';

    protected $description = 'Pull the VMOS SKU/package catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        $versions = array_filter(explode(',', (string) $this->option('android-versions')));
        $seen = 0;
        $created = 0;

        foreach ($versions as $version) {
            $response = $vmos->listGoods(androidVersion: (int) $version);
            $configs = $response['data']['configs'] ?? [];

            foreach ($configs as $config) {
                foreach ($config['goodTimes'] ?? [] as $goodTime) {
                    $sku = Sku::updateOrCreate(
                        [
                            'vmos_good_id' => $goodTime['id'],
                            'android_version' => (string) ($config['androidVersion'] ?? $version),
                        ],
                        [
                            'vmos_config_id' => $config['configId'],
                            'name' => $config['configName'],
                            'config_model' => $config['configModel'] ?? null,
                            'duration_label' => $goodTime['showContent'] ?? null,
                            'duration_minutes' => $goodTime['goodTime'],
                            'vmos_cost_price' => $goodTime['currentPrice'],
                            'sell_out' => (bool) ($config['sellOutFlag'] ?? false),
                            'raw_payload' => ['config' => $config, 'goodTime' => $goodTime],
                            'synced_at' => now(),
                        ]
                    );

                    if ($sku->wasRecentlyCreated) {
                        // Default 30% markup on first import; admin can adjust afterwards.
                        $sku->update([
                            'price' => round($goodTime['currentPrice'] * 1.3, 2),
                            'active' => true,
                        ]);
                        $created++;
                    }

                    $seen++;
                }
            }
        }

        $this->info("Synced {$seen} SKUs ({$created} new).");

        return self::SUCCESS;
    }
}
