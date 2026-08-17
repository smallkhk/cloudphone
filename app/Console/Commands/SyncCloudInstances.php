<?php

namespace App\Console\Commands;

use App\Models\CloudInstance;
use App\Services\Provisioning\ProxyProvisioner;
use App\Services\Vmos\VmosCloudPhoneService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCloudInstances extends Command
{
    protected $signature = 'vmos:sync-instances';

    protected $description = 'Refresh pad_code/status/expiry for cloud instances from VMOS (fills in padCode once provisioning finishes)';

    public function handle(VmosCloudPhoneService $vmos, ProxyProvisioner $proxies): int
    {
        $instances = CloudInstance::whereNotNull('equipment_id')->get()->keyBy('equipment_id');

        if ($instances->isEmpty()) {
            $this->info('No instances to sync.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($instances->chunk(100) as $chunk) {
            $equipmentIds = $chunk->keys()->map(fn ($id) => (int) $id)->all();

            $response = $vmos->listPads(equipmentIds: $equipmentIds);

            foreach ($response['data'] ?? [] as $pad) {
                $instance = $instances->get((string) ($pad['equipmentId'] ?? ''));

                if (! $instance) {
                    continue;
                }

                $justGotPadCode = ! $instance->pad_code && filled($pad['padCode'] ?? null);

                $instance->update([
                    'pad_code' => $pad['padCode'] ?? $instance->pad_code,
                    'pad_status' => $pad['cvmStatus'] ?? $instance->pad_status,
                    'online' => (bool) ($pad['status'] ?? 0),
                    'screenshot_url' => $pad['screenshotLink'] ?? $instance->screenshot_url,
                    'expires_at' => isset($pad['signExpirationTimeTamp'])
                        ? Carbon::createFromTimestampMs($pad['signExpirationTimeTamp'])
                        : $instance->expires_at,
                    'last_synced_at' => now(),
                ]);

                // A proxy add-on can only be applied once the padCode exists —
                // this is the first moment that's true.
                if ($justGotPadCode && $instance->order?->hasProxy()) {
                    $proxies->apply($instance->fresh());
                }

                $updated++;
            }
        }

        $this->info("Synced {$updated} instance(s).");

        return self::SUCCESS;
    }
}
