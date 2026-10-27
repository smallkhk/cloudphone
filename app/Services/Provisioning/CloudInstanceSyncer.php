<?php

namespace App\Services\Provisioning;

use App\Models\CloudInstance;
use App\Services\Vmos\VmosCloudPhoneService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls padCode/status/expiry for cloud instances from VMOS. A new device
 * only becomes usable once its padCode is known — createMoneyOrder returns
 * just an equipmentId, and VMOS's status webhook keys on padCode, so it can't
 * announce a device we don't have a padCode for yet. This is the only path.
 */
class CloudInstanceSyncer
{
    public function __construct(
        protected VmosCloudPhoneService $vmos,
        protected ProxyProvisioner $proxies,
        protected SimProvisioner $sim,
    ) {}

    /** @param  Collection<int, CloudInstance>  $instances */
    public function sync(Collection $instances): int
    {
        $byEquipment = $instances->filter(fn ($i) => $i->equipment_id)->keyBy('equipment_id');
        $updated = 0;

        foreach ($byEquipment->chunk(100) as $chunk) {
            // One bad chunk (an expired/deleted device, a VMOS hiccup) must not
            // stop every other customer's devices from syncing.
            try {
                $response = $this->vmos->listPads(equipmentIds: $chunk->keys()->map(fn ($id) => (int) $id)->values()->all());
            } catch (Throwable $e) {
                Log::warning('vmos.sync_instances_failed', ['error' => $e->getMessage()]);

                continue;
            }

            foreach ($response['data'] ?? [] as $pad) {
                $instance = $byEquipment->get((string) ($pad['equipmentId'] ?? ''));

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

                try {
                    // The checkout region's carrier can only be applied once the
                    // padCode exists, and SimProvisioner is idempotent.
                    if ($justGotPadCode) {
                        $this->sim->apply($instance->fresh());
                    }

                    // A VMOS proxy purchase is async on VMOS's side, so keep
                    // retrying each pass — apply() no-ops once resolved.
                    if ($instance->pad_code && $instance->order?->hasProxy()) {
                        $this->proxies->apply($instance->fresh());
                    }
                } catch (Throwable $e) {
                    Log::warning('vmos.sync_instance_followup_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
                }

                $updated++;
            }
        }

        return $updated;
    }
}
