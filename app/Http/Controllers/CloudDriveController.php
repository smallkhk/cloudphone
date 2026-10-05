<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Standalone Cloud Drive page — the same account-wide storage already shown
 * on each device's Cloud Drive tab (see DeviceControlController), just with
 * its own URL instead of only being reachable through one specific device.
 * Files/storage are identical no matter which door you walk in through,
 * since VMOS's Cloud Drive endpoints are account-wide, not per-device.
 *
 * Backups are the one thing here that genuinely needs a specific device
 * (VMOS's addBackup takes a padCode), so this page asks the customer to pick
 * one of their own owned devices before backing up — the device tab doesn't
 * need to ask because it already knows which device it's on.
 */
class CloudDriveController extends Controller
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function index()
    {
        $devices = CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->get(['id', 'pad_code', 'nickname']);

        $storage = Cache::remember('vmos.cloud_drive.storage', now()->addMinutes(2), function () {
            try {
                return $this->vmos->storageInfo()['data'] ?? null;
            } catch (Throwable) {
                return null;
            }
        });

        $files = Cache::remember('vmos.cloud_drive.files', now()->addMinutes(2), function () {
            try {
                return $this->vmos->listFiles()['data'] ?? [];
            } catch (Throwable) {
                return [];
            }
        });

        $storageGoods = [];
        if (Auth::user()?->is_admin) {
            try {
                $storageGoods = $this->vmos->storageGoods()['data'] ?? [];
            } catch (Throwable) {
                $storageGoods = [];
            }
        }

        $backupTasks = InstanceTask::whereIn('cloud_instance_id', $devices->pluck('id'))
            ->where('type', 'backup')
            ->with('cloudInstance')
            ->latest()
            ->take(10)
            ->get();

        return view('cloud-drive.index', compact('devices', 'storage', 'files', 'storageGoods', 'backupTasks'));
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            // VMOS's uploadFile only accepts an actual file body, not a URL —
            // download it here and re-upload the bytes. Nothing is written to
            // our own disk: the bytes live in memory only for this request.
            $download = Http::timeout(30)->get($data['url']);

            if (! $download->successful()) {
                throw new RuntimeException("Could not download that URL (HTTP {$download->status()}).");
            }

            $fileName = $data['file_name'] ?: (basename(parse_url($data['url'], PHP_URL_PATH) ?: '') ?: 'file');

            $this->vmos->uploadCloudFile($download->body(), $fileName);
            Cache::forget('vmos.cloud_drive.files');
            Cache::forget('vmos.cloud_drive.storage');

            return back()->with('status', 'File uploaded to Cloud Drive.');
        } catch (Throwable $e) {
            return back()->with('error', 'That request was rejected: '.$e->getMessage());
        }
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['integer'],
        ]);

        try {
            $this->vmos->deleteCloudFiles($data['file_ids']);
            Cache::forget('vmos.cloud_drive.files');
            Cache::forget('vmos.cloud_drive.storage');

            return back()->with('status', 'File deleted.');
        } catch (Throwable $e) {
            return back()->with('error', 'That request was rejected: '.$e->getMessage());
        }
    }

    public function backup(Request $request)
    {
        $data = $request->validate(['pad_code' => ['required', 'string']]);

        $instance = CloudInstance::where('user_id', Auth::id())->where('pad_code', $data['pad_code'])->first();
        abort_unless($instance, 403);

        try {
            $response = $this->vmos->createBackup([$instance->pad_code]);

            $entry = $response['data'][0] ?? $response['data'] ?? [];
            $instance->tasks()->create([
                'vmos_task_id' => is_array($entry) ? ($entry['taskId'] ?? null) : null,
                'type' => 'backup',
                'status' => InstanceTask::STATUS_PENDING,
                'result' => is_array($entry) ? $entry : ['raw' => $entry],
            ]);

            return back()->with('status', 'Backup started.');
        } catch (Throwable $e) {
            return back()->with('error', 'That request was rejected: '.$e->getMessage());
        }
    }

    public function backupProgress(InstanceTask $task)
    {
        $task->loadMissing('cloudInstance');
        abort_unless($task->cloudInstance && $task->cloudInstance->user_id === Auth::id(), 403);
        abort_unless($task->type === 'backup', 404);

        $batchId = $task->result['batchId'] ?? $task->result['data']['batchId'] ?? null;

        if (! $batchId) {
            return back()->with('error', 'No batch ID recorded for this backup — check Admin → API diagnostics.');
        }

        try {
            $progress = $this->vmos->backupProgress((string) $batchId)['data'] ?? [];
            $task->update(['result' => array_merge($task->result ?? [], ['progress' => $progress])]);

            return back()->with('status', 'Backup progress refreshed.');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not check backup progress: '.$e->getMessage());
        }
    }

    /** Admin-only — buying storage charges the VMOS account balance, same as buying a proxy. */
    public function buyStorage(Request $request)
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $data = $request->validate([
            'storage_id' => ['required', 'integer'],
            'auto_renew' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->vmos->buyStorage((int) $data['storage_id'], $request->boolean('auto_renew'));
            Cache::forget('vmos.cloud_drive.storage');

            return back()->with('status', 'Storage purchased — it may take a moment to reflect in the balance.');
        } catch (Throwable $e) {
            return back()->with('error', 'That request was rejected: '.$e->getMessage());
        }
    }
}
