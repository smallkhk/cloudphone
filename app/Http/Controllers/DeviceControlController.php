<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\ProxyChecker;
use App\Services\Vmos\VmosCloudPhoneService;
use App\Services\Vmos\VmosRegionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-device control panel: identity (phone number / GPS / locale), proxy,
 * installed apps and ADB access.
 *
 * Every action here is owner-scoped, and every VMOS call is wrapped so an API
 * error becomes a flash message rather than a 500.
 */
class DeviceControlController extends Controller
{
    public function __construct(
        protected VmosCloudPhoneService $vmos,
        protected VmosRegionCatalog $regions,
        protected ProxyChecker $proxyChecker,
    ) {}

    public function show(CloudInstance $instance)
    {
        $this->authorizeOwner($instance);

        $details = null;
        $apps = null;
        $detailsError = null;

        $storage = null;
        $files = null;
        $storageGoods = null;

        if ($instance->pad_code) {
            try {
                $details = $this->vmos->padInfo($instance->pad_code)['data'] ?? null;
            } catch (Throwable $e) {
                Log::warning('device_control.details_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
                $detailsError = 'Live device info is unavailable right now. Try refreshing in a moment.';
            }

            // Installed apps are slow to fetch, so cache briefly per device.
            $apps = Cache::remember("device.{$instance->id}.apps", now()->addMinutes(2), function () use ($instance) {
                try {
                    return $this->vmos->listInstalledApps([$instance->pad_code])['data'] ?? [];
                } catch (Throwable) {
                    return [];
                }
            });

            $storage = Cache::remember("device.{$instance->id}.storage", now()->addMinutes(2), function () use ($instance) {
                try {
                    return $this->vmos->storageInfo($instance->pad_code)['data'] ?? null;
                } catch (Throwable) {
                    return null;
                }
            });

            $files = Cache::remember("device.{$instance->id}.files", now()->addMinutes(2), function () use ($instance) {
                try {
                    return $this->vmos->listFiles($instance->pad_code)['data'] ?? [];
                } catch (Throwable) {
                    return [];
                }
            });

            if (Auth::user()?->is_admin) {
                try {
                    $storageGoods = $this->vmos->storageGoods()['data'] ?? [];
                } catch (Throwable) {
                    $storageGoods = [];
                }
            }
        }

        return view('instances.show', [
            'instance' => $instance->load('sku', 'tasks'),
            'details' => $details,
            'detailsError' => $detailsError,
            'apps' => $apps,
            'storage' => $storage,
            'files' => $files,
            'storageGoods' => $storageGoods,
            'countries' => $this->regions->options(),
        ]);
    }

    /**
     * Issues a live-screen session for this device.
     *
     * The token is minted server-side and scoped by VMOS to this one padCode,
     * so the browser never sees the account's API keys — only a short-lived
     * credential for a device the signed-in customer already owns.
     */
    public function screenToken(CloudInstance $instance)
    {
        $this->authorizeOwner($instance);

        if (! $instance->pad_code) {
            return response()->json(['error' => 'This device is still being provisioned.'], 409);
        }

        try {
            $token = $this->vmos->stsToken($instance->pad_code)['data']['token'] ?? null;
        } catch (Throwable $e) {
            Log::warning('device_control.screen_token_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not start the live screen session. Please try again.'], 502);
        }

        if (! $token) {
            return response()->json(['error' => 'Did not get a session token back. Please try again.'], 502);
        }

        return response()->json([
            'token' => $token,
            'padCode' => $instance->pad_code,
            'baseUrl' => (string) config('vmos.sdk_base_url'),
            // Identifies this viewer to the streaming service. Stable per
            // customer, so reconnecting resumes rather than opening a second
            // seat on the same device.
            'userId' => 'user-'.Auth::id(),
        ]);
    }

    // --- Identity --------------------------------------------------------

    public function updateSim(Request $request, CloudInstance $instance)
    {
        $data = $request->validate(['country_code' => ['required', 'string', 'size:2']]);

        return $this->run($instance, 'New phone number and SIM details requested — the device will restart.', function () use ($instance, $data) {
            $response = $this->vmos->updateSim($instance->pad_code, strtoupper($data['country_code']));
            $this->recordTask($instance, 'update_sim', $response);
        });
    }

    public function updateGps(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $this->run($instance, 'GPS location updated.', function () use ($instance, $data) {
            $this->vmos->setGps([$instance->pad_code], (float) $data['latitude'], (float) $data['longitude']);
        });
    }

    public function updateLocale(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'timezone' => ['nullable', 'timezone'],
            'language' => ['nullable', 'string', 'max:10'],
            'language_country' => ['nullable', 'string', 'max:5'],
        ]);

        return $this->run($instance, 'Language and timezone updated.', function () use ($instance, $data) {
            if (filled($data['timezone'] ?? null)) {
                $this->vmos->setTimezone([$instance->pad_code], $data['timezone']);
            }

            if (filled($data['language'] ?? null)) {
                $this->vmos->setLanguage([$instance->pad_code], $data['language'], $data['language_country'] ?? '');
            }
        });
    }

    public function newDevice(Request $request, CloudInstance $instance)
    {
        $request->validate(['wipe_data' => ['sometimes', 'boolean']]);

        return $this->run($instance, 'New device identity requested. This wipes the device and can take a few minutes.', function () use ($instance, $request) {
            $response = $this->vmos->replaceNewDevice([$instance->pad_code], $request->boolean('wipe_data', true));
            $this->recordTask($instance, 'new_device', $response);
        });
    }

    // --- Proxy -----------------------------------------------------------

    public function setProxy(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'account' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'proxy_name' => ['required', 'in:socks5,http-relay'],
            'proxy_type' => ['required', 'in:proxy,vpn'],
        ]);

        return $this->run($instance, 'Proxy applied to this device.', function () use ($instance, $data) {
            $response = $this->vmos->setCustomProxy(
                padCodes: [$instance->pad_code],
                ip: $data['ip'],
                port: (int) $data['port'],
                account: $data['account'] ?? null,
                password: $data['password'] ?? null,
                proxyName: $data['proxy_name'],
                proxyType: $data['proxy_type'],
            );
            $this->recordTask($instance, 'set_proxy', $response);
        });
    }

    public function clearProxy(CloudInstance $instance)
    {
        return $this->run($instance, 'Proxy disabled — the device is back on its default connection.', function () use ($instance) {
            $this->vmos->disableProxy([$instance->pad_code]);
        });
    }

    public function testProxy(Request $request, CloudInstance $instance)
    {
        $this->authorizeOwner($instance);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'account' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'proxy_name' => ['required', 'in:socks5,http-relay'],
        ]);

        try {
            $result = $this->proxyChecker->check(
                $data['ip'], (int) $data['port'], $data['account'] ?? null, $data['password'] ?? null, $data['proxy_name']
            );

            $where = collect([$result['city'], $result['country']])->filter()->implode(', ');

            return back()->with('status', 'Proxy is reachable'.($where ? " — appears to be in {$where}." : '.'));
        } catch (Throwable $e) {
            Log::warning('device_control.proxy_test_failed', [
                'instance_id' => $instance->id,
                'ip' => $data['ip'],
                'port' => $data['port'],
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Proxy check failed. Double-check the details and try again.');
        }
    }

    // --- Apps ------------------------------------------------------------

    public function installApp(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'apk_url' => ['required', 'url', 'max:2000'],
            'package_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run($instance, 'App download and install started — this can take a minute.', function () use ($instance, $data) {
            $response = $this->vmos->uploadFileFromUrl([$instance->pad_code], $data['apk_url'], $data['package_name'] ?? null);
            $this->recordTask($instance, 'install_app', $response);
            Cache::forget("device.{$instance->id}.apps");
        });
    }

    public function appAction(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:255'],
            'action' => ['required', 'in:start,stop,restart,uninstall'],
        ]);

        $labels = [
            'start' => 'App started.',
            'stop' => 'App stopped.',
            'restart' => 'App restarted.',
            'uninstall' => 'App uninstalled.',
        ];

        return $this->run($instance, $labels[$data['action']], function () use ($instance, $data) {
            $pads = [$instance->pad_code];
            $pkg = $data['package_name'];

            match ($data['action']) {
                'start' => $this->vmos->startApp($pads, $pkg),
                'stop' => $this->vmos->stopApp($pads, $pkg),
                'restart' => $this->vmos->restartApp($pads, $pkg),
                'uninstall' => $this->vmos->uninstallApp($pads, $pkg),
            };

            Cache::forget("device.{$instance->id}.apps");
        });
    }

    public function refreshApps(CloudInstance $instance)
    {
        $this->authorizeOwner($instance);
        Cache::forget("device.{$instance->id}.apps");

        return back()->with('status', 'App list refreshed.');
    }

    // --- Cloud Drive -------------------------------------------------------

    public function uploadDriveFile(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run($instance, 'File download started — it will appear in Cloud Drive shortly.', function () use ($instance, $data) {
            $response = $this->vmos->uploadCloudFile($instance->pad_code, $data['url'], $data['file_name'] ?? null);
            $this->recordTask($instance, 'upload_file', $response);
            Cache::forget("device.{$instance->id}.files");
            Cache::forget("device.{$instance->id}.storage");
        });
    }

    public function deleteDriveFile(Request $request, CloudInstance $instance)
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['string'],
        ]);

        return $this->run($instance, 'File deleted.', function () use ($instance, $data) {
            $this->vmos->deleteCloudFiles($data['file_ids']);
            Cache::forget("device.{$instance->id}.files");
            Cache::forget("device.{$instance->id}.storage");
        });
    }

    public function createBackup(CloudInstance $instance)
    {
        return $this->run($instance, 'Backup started — this can take a while for a large disk.', function () use ($instance) {
            $response = $this->vmos->createBackup([$instance->pad_code]);
            $this->recordTask($instance, 'backup', $response);
        });
    }

    public function backupProgress(CloudInstance $instance, InstanceTask $task)
    {
        $this->authorizeOwner($instance);
        abort_unless($task->cloud_instance_id === $instance->id && $task->type === 'backup', 404);

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
    public function buyStorage(Request $request, CloudInstance $instance)
    {
        $this->authorizeOwner($instance);
        abort_unless(Auth::user()?->is_admin, 403);

        $data = $request->validate([
            'good_id' => ['required', 'integer'],
            'num' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        return $this->run($instance, 'Storage purchased — it may take a moment to reflect in the balance.', function () use ($instance, $data) {
            $this->vmos->buyStorage((int) $data['good_id'], $instance->pad_code, (int) $data['num']);
            Cache::forget("device.{$instance->id}.storage");
        });
    }

    // --- ADB -------------------------------------------------------------

    public function toggleAdb(Request $request, CloudInstance $instance)
    {
        $enable = $request->boolean('enable');

        $this->authorizeOwner($instance);
        abort_unless($instance->pad_code, 422);

        try {
            $this->vmos->toggleAdb([$instance->pad_code], $enable);

            if (! $enable) {
                return back()->with('status', 'ADB disabled.');
            }

            $info = $this->vmos->adbInfo([$instance->pad_code])['data'][0] ?? [];
            $command = isset($info['ip'], $info['port']) ? "adb connect {$info['ip']}:{$info['port']}" : null;

            return back()->with('status', $command
                ? "ADB enabled. Connect with:  {$command}"
                : 'ADB enabled — connection details will appear shortly.');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not change ADB: '.$e->getMessage());
        }
    }

    // --- Helpers ---------------------------------------------------------

    protected function authorizeOwner(CloudInstance $instance): void
    {
        abort_unless($instance->user_id === Auth::id() || Auth::user()?->is_admin, 403);
    }

    /** Runs a VMOS action with ownership + readiness checks and error handling. */
    protected function run(CloudInstance $instance, string $successMessage, callable $action)
    {
        $this->authorizeOwner($instance);

        if (! $instance->pad_code) {
            return back()->with('error', 'This device is still being provisioned.');
        }

        try {
            $action();
        } catch (Throwable $e) {
            return back()->with('error', 'That request was rejected: '.$e->getMessage());
        }

        return back()->with('status', $successMessage);
    }

    protected function recordTask(CloudInstance $instance, string $type, array $response): void
    {
        $entry = $response['data'][0] ?? $response['data'] ?? [];

        $instance->tasks()->create([
            'vmos_task_id' => is_array($entry) ? ($entry['taskId'] ?? null) : null,
            'type' => $type,
            'status' => InstanceTask::STATUS_PENDING,
            'result' => is_array($entry) ? $entry : ['raw' => $entry],
        ]);
    }
}
