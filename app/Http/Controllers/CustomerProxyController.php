<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\CustomerProxy;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Standalone proxies (Sku::TYPE_PROXY) a customer bought on their own, not as
 * a device-checkout add-on — see StandaloneProxyProvisioner. Unlike the
 * checkout add-on, these can be attached/detached from any of the customer's
 * own devices at will.
 */
class CustomerProxyController extends Controller
{
    public function __construct(protected VmosCloudPhoneService $vmos) {}

    /** Storefront: browse purchasable proxy plans. */
    public function index()
    {
        $skus = Sku::available()->proxies()->where('price', '>', 0)->orderBy('default_country_code')->orderBy('name')->get();

        $owned = Auth::check()
            ? CustomerProxy::where('user_id', Auth::id())->with('sku')->latest()->get()
            : collect();

        $devices = Auth::check()
            ? CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->get(['id', 'pad_code', 'nickname'])
            : collect();

        return view('proxies.index', compact('skus', 'owned', 'devices'));
    }

    /** Attaches an owned, delivered proxy to one of the customer's own devices. */
    public function attach(Request $request, CustomerProxy $proxy)
    {
        abort_unless($proxy->user_id === Auth::id(), 403);

        $data = $request->validate(['pad_code' => ['required', 'string']]);

        $owns = CloudInstance::where('user_id', Auth::id())->where('pad_code', $data['pad_code'])->exists();
        abort_unless($owns, 403);

        if (! $proxy->isDelivered()) {
            return back()->with('error', 'This proxy isn\'t ready to attach yet — try again in a moment.');
        }

        try {
            $this->vmos->attachProxies([$data['pad_code']], [$proxy->vmos_proxy_id]);

            $proxy->update(['attached_pad_code' => $data['pad_code']]);

            return back()->with('status', 'Proxy attached. Allow a few seconds for it to take effect on the device.');
        } catch (Throwable $e) {
            Log::warning('customer_proxies.attach_failed', ['customer_proxy_id' => $proxy->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not attach this proxy right now. Please try again.');
        }
    }

    /** Detaches a proxy from whichever device it's currently attached to. */
    public function detach(CustomerProxy $proxy)
    {
        abort_unless($proxy->user_id === Auth::id(), 403);

        if (! $proxy->isAttached()) {
            return back()->with('error', 'This proxy isn\'t attached to a device.');
        }

        try {
            $this->vmos->disableProxy([$proxy->attached_pad_code]);

            $proxy->update(['attached_pad_code' => null]);

            return back()->with('status', 'Proxy detached.');
        } catch (Throwable $e) {
            Log::warning('customer_proxies.detach_failed', ['customer_proxy_id' => $proxy->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not detach this proxy right now. Please try again.');
        }
    }

    /** Checks the proxy is reachable, same underlying check as checkout/device-panel "Test proxy". */
    public function test(CustomerProxy $proxy)
    {
        abort_unless($proxy->user_id === Auth::id(), 403);

        if (! $proxy->isDelivered()) {
            return back()->with('error', 'This proxy isn\'t ready to test yet.');
        }

        try {
            $response = $this->vmos->checkProxyIp((string) $proxy->host, (int) $proxy->port, $proxy->account);
            $info = $response['data'] ?? [];
            $where = collect([$info['city'] ?? null, $info['country'] ?? null])->filter()->implode(', ');

            return back()->with('status', 'Proxy is reachable'.($where ? " — appears to be in {$where}." : '.'));
        } catch (Throwable $e) {
            Log::warning('customer_proxies.test_failed', ['customer_proxy_id' => $proxy->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Proxy check failed. Double-check it\'s still active and try again.');
        }
    }
}
