<?php

namespace App\Console\Commands;

use App\Models\CloudInstance;
use App\Services\Provisioning\CloudInstanceSyncer;
use Illuminate\Console\Command;

class SyncCloudInstances extends Command
{
    protected $signature = 'vmos:sync-instances';

    protected $description = 'Refresh pad_code/status/expiry for cloud instances from VMOS (fills in padCode once provisioning finishes)';

    public function handle(CloudInstanceSyncer $syncer): int
    {
        $instances = CloudInstance::whereNotNull('equipment_id')->get();

        if ($instances->isEmpty()) {
            $this->info('No instances to sync.');

            return self::SUCCESS;
        }

        $updated = $syncer->sync($instances);

        $this->info("Synced {$updated} instance(s).");

        return self::SUCCESS;
    }
}
