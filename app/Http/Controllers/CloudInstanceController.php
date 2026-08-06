<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Auth;

class CloudInstanceController extends Controller
{
    public function index()
    {
        $instances = Auth::user()->cloudInstances()->with('sku')->latest()->get();

        return view('instances.index', compact('instances'));
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
