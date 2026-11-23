<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Models\CloudNumber;
use App\Models\CustomerProxy;
use App\Models\EmailAccount;
use App\Models\InstanceTask;
use App\Models\Order;
use App\Models\PhoneNumber;
use App\Models\Setting;
use App\Models\Sku;
use App\Services\Provisioning\CloudInstanceSyncer;
use App\Services\Vmos\VmosCloudPhoneService;
use App\Services\Vmos\VmosProxyCatalog;
use App\Services\Vmos\VmosRegionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only screens of the iPhone app. Every change (buy, restart, bind...)
 * goes through the website's own controllers via MobileActionResponse, so the
 * app and the site can't drift apart on business rules.
 */
class DataController extends Controller
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    public function me(): JsonResponse
    {
        return response()->json(['user' => MobileResources::user(Auth::user())]);
    }

    public function devices(CloudInstanceSyncer $syncer): JsonResponse
    {
        $user = Auth::user();
        $instances = $user->cloudInstances()->with('sku')->latest()->get();

        // Same as the website's My devices page: don't make a customer wait
        // for the next cron pass to see a device that's already finished.
        $pending = $instances->filter(fn ($i) => ! $i->pad_code && $i->equipment_id);

        if ($pending->isNotEmpty() && Cache::add('instance-sync:'.$user->id, true, 10)) {
            $syncer->sync($pending);
            $instances = $user->cloudInstances()->with('sku')->latest()->get();
        }

        return response()->json(['devices' => $instances->map(fn ($i) => MobileResources::device($i))->values()]);
    }

    public function device(CloudInstance $instance, VmosRegionCatalog $regions): JsonResponse
    {
        abort_unless($instance->user_id === Auth::id(), 403);

        $details = null;
        $apps = [];

        if ($instance->pad_code) {
            try {
                $d = $this->vmos->padInfo($instance->pad_code)['data'] ?? [];

                // Same fields, same labels as the website's device panel.
                $details = [
                    'rows' => collect([
                        'Model' => $d['padType'] ?? $instance->sku?->name,
                        'Android' => $d['androidVersion'] ?? $instance->sku?->android_version,
                        'Phone number' => $d['phoneNumber'] ?? null,
                        'IMEI' => $d['padImei'] ?? null,
                        'Carrier' => $d['simIso'] ?? null,
                        'SIM country' => $d['simCountry'] ?? null,
                        'Public IP' => $d['publicIp'] ?? null,
                        'IP location' => $d['ipAddress'] ?? null,
                        'Timezone' => $d['timeZone'] ?? null,
                        'Language' => $d['explain'] ?? null,
                        'GPS' => isset($d['latitude'], $d['longitude']) ? $d['latitude'].', '.$d['longitude'] : null,
                        'WLAN MAC' => $d['wlanMac'] ?? null,
                        'Bluetooth' => $d['bluetoothAddress'] ?? null,
                    ])->filter(fn ($v) => filled($v))->map(fn ($v, $k) => ['label' => $k, 'value' => (string) $v])->values(),
                    'sim_country' => $d['simCountry'] ?? null,
                    'timezone' => $d['timeZone'] ?? null,
                    'latitude' => isset($d['latitude']) ? (float) $d['latitude'] : null,
                    'longitude' => isset($d['longitude']) ? (float) $d['longitude'] : null,
                ];
            } catch (Throwable $e) {
                Log::warning('mobile.device_details_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
            }

            $apps = Cache::remember("device.{$instance->id}.apps", now()->addMinutes(2), function () use ($instance) {
                try {
                    return $this->vmos->listInstalledApps([$instance->pad_code])['data'] ?? [];
                } catch (Throwable) {
                    return [];
                }
            });
        }

        return response()->json([
            'device' => MobileResources::device($instance->load('sku')),
            'details' => $details,
            // VMOS nests apps per device; same unwrapping as the website.
            'apps' => collect($apps)
                ->flatMap(fn ($e) => $e['apps'] ?? $e['appList'] ?? (is_array($e) && isset($e['packageName']) ? [$e] : []))
                ->map(fn ($a) => [
                    'name' => $a['appName'] ?? $a['name'] ?? $a['packageName'] ?? $a['pkgName'] ?? 'App',
                    'package_name' => $a['packageName'] ?? $a['pkgName'] ?? null,
                    'version' => $a['versionName'] ?? null,
                ])->filter(fn ($a) => $a['package_name'])->values(),
            'tasks' => $instance->tasks()->latest()->take(10)->get()->map(fn ($t) => MobileResources::task($t))->values(),
            'countries' => collect($regions->options())->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
        ]);
    }

    public function catalog(VmosRegionCatalog $regions, VmosProxyCatalog $proxies): JsonResponse
    {
        $skus = Sku::available()->cloudPhones()->where('price', '>', 0)
            ->orderBy('sort_order')->orderBy('name')->orderBy('duration_minutes')->orderBy('price')
            ->get();

        $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);

        return response()->json([
            'plans' => $skus->map(fn ($s) => MobileResources::sku($s))->values(),
            'regions' => collect($regions->purchaseOptions())->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            'proxy_products' => collect($proxies->products())->map(fn ($p) => [
                'id' => (int) ($p['proxyGoodId'] ?? 0),
                'name' => $p['proxyGoodName'] ?? 'Proxy package',
                'price' => round(((float) ($p['proxyGoodPrice'] ?? 0)) / 100 * $markup, 2),
            ])->filter(fn ($p) => $p['id'])->values(),
            'proxy_regions' => collect($proxies->regions())->map(fn ($r) => [
                'code' => strtoupper((string) ($r['country'] ?? '')),
                'name' => VmosRegionCatalog::nameFor($r['country'] ?? ''),
            ])->filter(fn ($r) => $r['code'])->values(),
            'balance' => (float) Auth::user()->balance,
        ]);
    }

    /** Storefront + owned items for one of the non-device products. */
    public function products(string $type): JsonResponse
    {
        $user = Auth::user();

        $skus = match ($type) {
            'email-accounts' => Sku::available()->emailAccounts(),
            'phone-numbers' => Sku::available()->phoneNumbers(),
            'cloud-numbers' => Sku::available()->cloudNumbers(),
            'proxies' => Sku::available()->proxies(),
            default => abort(404),
        };

        $skus = $skus->where('price', '>', 0)->orderBy('default_country_code')->orderBy('sort_order')
            ->orderBy('name')->orderBy('duration_minutes')->get();

        $owned = match ($type) {
            'email-accounts' => EmailAccount::where('user_id', $user->id)->with('sku')->latest()->get()
                ->map(fn ($a) => MobileResources::emailAccount($a)),
            'phone-numbers' => PhoneNumber::where('user_id', $user->id)->with('sku')->latest()->get()
                ->map(fn ($n) => MobileResources::phoneNumber($n)),
            'cloud-numbers' => CloudNumber::where('user_id', $user->id)->with('sku')->latest()->get()
                ->map(fn ($n) => MobileResources::cloudNumber($n)),
            'proxies' => CustomerProxy::where('user_id', $user->id)->with('sku')->latest()->get()
                ->map(fn ($p) => MobileResources::proxy($p)),
        };

        return response()->json([
            'products' => $skus->map(fn ($s) => MobileResources::sku($s))->values(),
            'owned' => $owned->values(),
            'devices' => $this->readyDevices(),
        ]);
    }

    public function orders(): JsonResponse
    {
        $orders = Auth::user()->orders()->with('sku')->latest()->take(100)->get();

        return response()->json(['orders' => $orders->map(fn ($o) => MobileResources::order($o))->values()]);
    }

    public function order(Order $order): JsonResponse
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $order->load(['sku', 'payments' => fn ($q) => $q->latest('id'), 'cloudInstances.sku', 'emailAccounts.sku', 'phoneNumbers.sku', 'cloudNumbers.sku', 'customerProxies.sku']);

        return response()->json(['order' => MobileResources::order($order, withDetails: true)]);
    }

    public function wallet(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'balance' => (float) $user->balance,
            'min_deposit' => (float) config('crypto.min_deposit_usd'),
            'networks' => collect([
                'TRC20' => ['label' => 'USDT (TRC20)', 'enabled' => filled(config('crypto.usdt_trc20_address'))],
                'BEP20' => ['label' => 'USDT (BEP20 / BNB Smart Chain)', 'enabled' => filled(config('crypto.usdt_bep20_address'))],
                'LTC' => ['label' => 'Litecoin (LTC)', 'enabled' => filled(config('crypto.ltc_address'))],
            ])->filter(fn ($n) => $n['enabled'])->map(fn ($n, $code) => ['code' => $code, 'label' => $n['label']])->values(),
            'deposits' => $user->walletDeposits()->take(30)->get()->map(fn ($d) => MobileResources::deposit($d))->values(),
            'transactions' => $user->walletTransactions()->take(50)->get()->map(fn ($t) => MobileResources::transaction($t))->values(),
        ]);
    }

    public function cloudDrive(): JsonResponse
    {
        $devices = CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->get();

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

        return response()->json([
            'storage' => $storage ? [
                'used_bytes' => (int) ($storage['storageUsedAvail'] ?? 0),
                'total_bytes' => (int) ($storage['storageCapacityLimit'] ?? 0),
            ] : null,
            // Same fields the website's Cloud Drive page reads.
            'files' => collect(is_array($files) ? $files : [])->map(fn ($f) => [
                'id' => (int) ($f['fileId'] ?? 0),
                'name' => (string) ($f['appName'] ?? $f['fileName'] ?? $f['fileId'] ?? 'File'),
                'size_bytes' => (int) ($f['fileSize'] ?? $f['size'] ?? 0),
                'created_at' => isset($f['createTime']) && ! is_array($f['createTime']) ? (string) $f['createTime'] : null,
            ])->filter(fn ($f) => $f['id'])->values(),
            'devices' => $this->readyDevices(),
            'backups' => InstanceTask::whereIn('cloud_instance_id', $devices->pluck('id'))->where('type', 'backup')
                ->with('cloudInstance.sku')->latest()->take(10)->get()
                ->map(fn ($t) => MobileResources::task($t) + ['device' => $t->cloudInstance ? MobileResources::device($t->cloudInstance)['name'] : null])
                ->values(),
        ]);
    }

    /** @return array<int, array{id: int, name: string, pad_code: string}> */
    protected function readyDevices(): array
    {
        return CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->with('sku')->get()
            ->map(fn ($i) => ['id' => $i->id, 'name' => MobileResources::device($i)['name'], 'pad_code' => $i->pad_code])
            ->values()->all();
    }
}
