<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Throwable;

/**
 * Static residential + dynamic proxy stock held on the VMOS account: what's
 * available to buy, what you already own, and attaching a proxy to a device.
 *
 * Proxies live on the VMOS side, so this reads through to their API rather than
 * mirroring them locally — there's no useful local state to keep in sync.
 */
class ProxyController extends Controller
{
    public function __construct(protected VmosCloudPhoneService $vmos)
    {
    }

    public function index()
    {
        if (! filled(config('vmos.access_key'))) {
            return view('admin.proxies.index', [
                'configured' => false,
                'products' => [], 'regions' => [], 'owned' => [], 'traffic' => null, 'errors' => [],
                'devices' => collect(),
            ]);
        }

        $errors = [];

        $products = $this->safely($errors, 'products', fn () => $this->vmos->staticProxyGoods()['data'] ?? []);
        $regions = $this->safely($errors, 'regions', fn () => $this->vmos->staticProxyRegions()['data'] ?? []);
        $owned = $this->safely($errors, 'owned', fn () => $this->vmos->listStaticProxies()['data']['records'] ?? []);
        $traffic = $this->safely($errors, 'traffic', fn () => $this->vmos->dynamicTrafficBalance()['data'] ?? null);

        return view('admin.proxies.index', [
            'configured' => true,
            'products' => $products ?? [],
            'regions' => $regions ?? [],
            'owned' => $owned ?? [],
            'traffic' => $traffic,
            'errors' => $errors,
            'devices' => CloudInstance::whereNotNull('pad_code')->with('user')->orderBy('pad_code')->get(),
        ]);
    }

    public function buy(Request $request)
    {
        $data = $request->validate([
            'proxy_good_id' => ['required', 'integer'],
            'country' => ['required', 'string', 'max:8'],
            'proxy_address' => ['required', 'string', 'max:100'],
            'num' => ['required', 'integer', 'min:1', 'max:20'],
            'auto_renew' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->vmos->buyStaticProxy(
                proxyGoodId: (int) $data['proxy_good_id'],
                country: $data['country'],
                proxyAddress: $data['proxy_address'],
                num: (int) $data['num'],
                autoRenew: $request->boolean('auto_renew'),
            );
        } catch (Throwable $e) {
            return back()->with('error', 'Purchase failed: '.$e->getMessage());
        }

        return back()->with('status', "Bought {$data['num']} proxy(ies) in {$data['proxy_address']}. They'll appear below once VMOS provisions them.");
    }

    /** Attaches a VMOS-owned proxy to one or more devices. */
    public function attach(Request $request)
    {
        $data = $request->validate([
            'proxy_id' => ['required', 'integer'],
            'pad_codes' => ['required', 'array', 'min:1'],
            'pad_codes.*' => ['string'],
        ]);

        try {
            $this->vmos->attachProxies($data['pad_codes'], [(int) $data['proxy_id']]);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not attach the proxy: '.$e->getMessage());
        }

        $count = count($data['pad_codes']);

        return back()->with('status', "Proxy attached to {$count} device(s). Mounting is asynchronous — allow a few seconds.");
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string'],
            'port' => ['required', 'integer'],
            'account' => ['required', 'string'],
        ]);

        try {
            $deleted = $this->vmos->deleteStaticProxy($data['host'], (int) $data['port'], $data['account'])['data'] ?? 0;
        } catch (Throwable $e) {
            return back()->with('error', 'Delete failed: '.$e->getMessage());
        }

        return back()->with('status', $deleted
            ? "Deleted {$deleted} proxy(ies). Any devices using them were unbound."
            : 'No matching proxy found — nothing deleted.');
    }

    /**
     * Runs a read that may fail independently of the others, so one broken
     * endpoint doesn't blank the whole page.
     *
     * @param  array<string, string>  $errors
     */
    protected function safely(array &$errors, string $key, callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            $errors[$key] = $e->getMessage();

            return null;
        }
    }
}
