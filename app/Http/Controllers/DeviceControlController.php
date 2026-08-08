<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    public function __construct(protected VmosCloudPhoneService $vmos)
    {
    }

    public function show(CloudInstance $instance)
    {
        $this->authorizeOwner($instance);

        $details = null;
        $apps = null;
        $detailsError = null;

        if ($instance->pad_code) {
            try {
                $details = $this->vmos->padInfo($instance->pad_code)['data'] ?? null;
            } catch (Throwable $e) {
                $detailsError = $e->getMessage();
            }

            // Installed apps are slow to fetch, so cache briefly per device.
            $apps = Cache::remember("device.{$instance->id}.apps", now()->addMinutes(2), function () use ($instance) {
                try {
                    return $this->vmos->listInstalledApps([$instance->pad_code])['data'] ?? [];
                } catch (Throwable) {
                    return [];
                }
            });
        }

        return view('instances.show', [
            'instance' => $instance->load('sku', 'tasks'),
            'details' => $details,
            'detailsError' => $detailsError,
            'apps' => $apps,
            'countries' => $this->countryList(),
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
            $response = $this->vmos->checkProxyIp(
                $data['ip'], (int) $data['port'], $data['account'] ?? null, $data['password'] ?? null, $data['proxy_name']
            );

            $info = $response['data'] ?? [];
            $where = collect([$info['city'] ?? null, $info['country'] ?? null])->filter()->implode(', ');

            return back()->with('status', 'Proxy is reachable'.($where ? " — appears to be in {$where}." : '.'));
        } catch (Throwable $e) {
            return back()->with('error', 'Proxy check failed: '.$e->getMessage());
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
            return back()->with('error', 'VMOS rejected that request: '.$e->getMessage());
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

    /** Country list for SIM generation, cached since it rarely changes. */
    protected function countryList(): array
    {
        return Cache::remember('vmos.countries', now()->addDay(), function () {
            try {
                return collect($this->vmos->countries()['data'] ?? [])
                    ->mapWithKeys(fn ($c) => [$c['code'] => $c['name']])
                    ->sort()
                    ->all();
            } catch (Throwable) {
                // Fall back to a common subset so the form still works offline.
                return [
                    'US' => 'United States', 'GB' => 'United Kingdom', 'HK' => 'Hong Kong',
                    'SG' => 'Singapore', 'NG' => 'Nigeria', 'ZA' => 'South Africa',
                    'IN' => 'India', 'PH' => 'Philippines', 'ID' => 'Indonesia',
                    'BR' => 'Brazil', 'DE' => 'Germany', 'FR' => 'France',
                    'AE' => 'United Arab Emirates', 'CA' => 'Canada', 'AU' => 'Australia',
                ];
            }
        });
    }
}
