<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VmosWebhookController extends Controller
{
    // See: https://cloud.vmoscloud.com/vmoscloud/doc/en/server/callback.html
    protected const TYPE_INSTANCE_STATUS = 999;

    protected const TYPE_RESTART = 1000;

    protected const TYPE_RESET = 1001;

    public function handle(Request $request)
    {
        // VMOS does not sign callback requests, so we authenticate with a shared
        // secret appended to the callback URL configured in the VMOS console.
        $expected = config('vmos.webhook_token');
        if ($expected && $request->query('token') !== $expected) {
            abort(403, 'Invalid webhook token.');
        }

        $payload = $request->all();
        Log::info('vmos.callback', $payload);

        $type = (int) ($payload['taskBusinessType'] ?? 0);
        $padCode = $payload['padCode'] ?? null;

        if (! $padCode) {
            return response()->json(['ok' => true]);
        }

        match ($type) {
            self::TYPE_INSTANCE_STATUS => $this->handleInstanceStatus($padCode, $payload),
            self::TYPE_RESTART, self::TYPE_RESET => $this->handleTaskResult($padCode, $payload),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    protected function handleInstanceStatus(string $padCode, array $payload): void
    {
        CloudInstance::where('pad_code', $padCode)->update([
            'pad_status' => $payload['padStatus'] ?? null,
            'online' => (bool) ($payload['padConnectStatus'] ?? 0),
            'last_synced_at' => now(),
        ]);
    }

    protected function handleTaskResult(string $padCode, array $payload): void
    {
        $instance = CloudInstance::where('pad_code', $padCode)->first();

        if (! $instance) {
            return;
        }

        $taskStatus = (int) ($payload['taskStatus'] ?? 0);
        $status = $taskStatus === 3
            ? InstanceTask::STATUS_COMPLETED
            : ($taskStatus < 0 ? InstanceTask::STATUS_FAILED : InstanceTask::STATUS_PENDING);

        $query = $instance->tasks();

        if (isset($payload['taskId'])) {
            $query->where('vmos_task_id', $payload['taskId']);
        }

        $task = $query->latest('id')->first();

        $task?->update(['status' => $status, 'result' => $payload]);
    }
}
