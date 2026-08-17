<?php

namespace App\Services\Provisioning;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sets a device's SIM/carrier to match the region the customer picked at
 * checkout, the instant the device's padCode is known — so the customer
 * never has to manually re-pick the same region again in "Phone number &
 * SIM" just to get a matching carrier; it's already done for them.
 *
 * VMOS's createMoneyOrder `countryCode` parameter isn't confirmed to
 * guarantee the SIM actually matches on its own, so this calls updateSim()
 * explicitly rather than assuming — the same action a customer could take by
 * hand from the device panel, just automatic and using their own choice.
 */
class SimProvisioner
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function apply(CloudInstance $instance): void
    {
        $countryCode = $instance->order?->country_code;

        if (! $countryCode || $instance->tasks()->where('type', 'update_sim')->exists()) {
            return;
        }

        try {
            $response = $this->vmos->updateSim($instance->pad_code, $countryCode);

            $instance->tasks()->create([
                'vmos_task_id' => $response['data']['taskId'] ?? null,
                'type' => 'update_sim',
                'status' => InstanceTask::STATUS_PENDING,
                'result' => is_array($response['data'] ?? null) ? $response['data'] : [],
            ]);
        } catch (Throwable $e) {
            Log::error('sim_provisioning.failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }
    }
}
