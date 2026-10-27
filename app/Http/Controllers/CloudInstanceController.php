<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\Provisioning\CloudInstanceSyncer;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CloudInstanceController extends Controller
{
    public function index(CloudInstanceSyncer $syncer)
    {
        $instances = Auth::user()->cloudInstances()->with('sku')->latest()->get();

        // Don't make a customer wait for the next cron pass to see a device
        // VMOS has already finished — check the pending ones right now, at
        // most once every 10 seconds per customer (the page auto-refreshes).
        $pending = $instances->filter(fn ($i) => ! $i->pad_code && $i->equipment_id);

        if ($pending->isNotEmpty() && Cache::add('instance-sync:'.Auth::id(), true, 10)) {
            $syncer->sync($pending);
            $instances = Auth::user()->cloudInstances()->with('sku')->latest()->get();
        }

        return view('instances.index', [
            'instances' => $instances,
            'hasPending' => $instances->contains(fn ($i) => ! $i->pad_code),
        ]);
    }

    public function restart(CloudInstance $instance, VmosCloudPhoneService $vmos)
    {
        $this->authorizeOwner($instance);
        abort_unless($instance->pad_code, 422, 'This cloud phone is still being provisioned.');

        $response = $vmos->restart([$instance->pad_code]);
        $this->recordTask($instance, InstanceTask::TYPE_RESTART, $response);

        return back()->with('status', 'Restart requested.');
    }

    public function reset(CloudInstance $instance, VmosCloudPhoneService $vmos)
    {
        $this->authorizeOwner($instance);
        abort_unless($instance->pad_code, 422, 'This cloud phone is still being provisioned.');

        $response = $vmos->reset([$instance->pad_code]);
        $this->recordTask($instance, InstanceTask::TYPE_RESET, $response);

        return back()->with('status', 'Reset requested — this clears all data on the device.');
    }

    public function screenshot(CloudInstance $instance, VmosCloudPhoneService $vmos)
    {
        $this->authorizeOwner($instance);
        abort_unless($instance->pad_code, 422, 'This cloud phone is still being provisioned.');

        $response = $vmos->screenshot([$instance->pad_code]);
        $url = $response['data'][0]['url'] ?? null;

        if ($url) {
            $instance->update(['screenshot_url' => $url]);
        }

        return back()->with('status', $url ? 'Screenshot refreshed.' : 'Could not fetch a screenshot right now.');
    }

    protected function authorizeOwner(CloudInstance $instance): void
    {
        abort_unless($instance->user_id === Auth::id(), 403);
    }

    protected function recordTask(CloudInstance $instance, string $type, array $response): void
    {
        $entry = $response['data'][0] ?? [];

        $instance->tasks()->create([
            'vmos_task_id' => $entry['taskId'] ?? null,
            'type' => $type,
            'status' => InstanceTask::STATUS_PENDING,
            'result' => $entry,
        ]);
    }
}
