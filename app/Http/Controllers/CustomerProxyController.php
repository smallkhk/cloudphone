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

    /** Storefront: browse purchasable proxy plans via the "Buy Proxy" modal (region, then plan). */
    public function index()
    {
        $skus = Sku::available()->proxies()->where('price', '>', 0)
            ->orderBy('default_country_code')->orderBy('name')->get();

        $owned = Auth::check()
            ? CustomerProxy::where('user_id', Auth::id())->with('sku')->latest()->get()
            : collect();

        $devices = Auth::check()
            ? CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->get(['id', 'pad_code', 'nickname'])
            : collect();

        return view('proxies.index', compact('skus', 'owned', 'devices'));
    }

    /** Adds the customer's own proxy directly — no order, no VMOS charge, ready immediately. */
    public function store(Request $request)
    {
        $data = $request->validate($this->customProxyRules());

        CustomerProxy::create([
            'user_id' => Auth::id(),
            'source' => CustomerProxy::SOURCE_CUSTOM,
            'label' => $data['label'] ?? null,
            'host' => $data['host'],
            'port' => $data['port'],
            'account' => $data['account'] ?? null,
            'password' => $data['password'] ?? null,
            'proxy_name' => $data['proxy_name'],
            'proxy_type' => $data['proxy_type'],
            'remarks' => $data['remarks'] ?? null,
            'purchase_status' => CustomerProxy::PURCHASE_COMPLETED,
            'delivered_at' => now(),
        ]);

        return back()->with('status', 'Proxy added — test it, then attach it to a device whenever you\'re ready.');
    }

    /** Edits a manually-added proxy's details. A bought one has nothing here to edit — VMOS owns its details. */
    public function update(Request $request, CustomerProxy $proxy)
    {
        abort_unless($proxy->user_id === Auth::id(), 403);
        abort_unless($proxy->isCustom(), 403);

        $data = $request->validate($this->customProxyRules());

        $proxy->update([
            'label' => $data['label'] ?? null,
            'host' => $data['host'],
            'port' => $data['port'],
            'account' => $data['account'] ?? null,
            // Blank password on the edit form means "leave it as-is", not "clear it" —
            // VMOS's own list never hands a password back, so there's no way to show
            // one to a customer editing an entry that had one but left the field empty.
            'password' => filled($data['password'] ?? null) ? $data['password'] : $proxy->password,
            'proxy_name' => $data['proxy_name'],
            'proxy_type' => $data['proxy_type'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('status', 'Proxy updated'.($proxy->isAttached() ? ' — reattach it to the device to apply the change.' : '.'));
    }

    /** @return array<string, array<int, mixed>> */
    protected function customProxyRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'account' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'proxy_name' => ['required', 'in:socks5,http-relay'],
            'proxy_type' => ['required', 'in:proxy,vpn'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Removes a manually-added proxy. A bought one can't be deleted this way — it's real, paid-for inventory. */
    public function destroy(CustomerProxy $proxy)
    {
        abort_unless($proxy->user_id === Auth::id(), 403);
        abort_unless($proxy->isCustom(), 403);

        if ($proxy->isAttached()) {
            try {
                $this->vmos->disableProxy([$proxy->attached_pad_code]);
            } catch (Throwable $e) {
                Log::warning('customer_proxies.remove_detach_failed', ['customer_proxy_id' => $proxy->id, 'error' => $e->getMessage()]);
            }
        }

        $proxy->delete();

        return back()->with('status', 'Proxy removed.');
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
            if ($proxy->isCustom()) {
                $this->vmos->setCustomProxy(
                    [$data['pad_code']], (string) $proxy->host, (int) $proxy->port,
                    $proxy->account, $proxy->password, $proxy->proxy_name, $proxy->proxy_type,
                );
            } else {
                $this->vmos->attachProxies([$data['pad_code']], [$proxy->vmos_proxy_id]);
            }

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
            $response = $this->vmos->checkProxyIp((string) $proxy->host, (int) $proxy->port, $proxy->account, $proxy->password, $proxy->proxy_name ?: 'socks5');
            $info = $response['data'] ?? [];
            $where = collect([$info['city'] ?? null, $info['country'] ?? null])->filter()->implode(', ');

            return back()->with('status', 'Proxy is reachable'.($where ? " — appears to be in {$where}." : '.'));
        } catch (Throwable $e) {
            Log::warning('customer_proxies.test_failed', ['customer_proxy_id' => $proxy->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Proxy check failed. Double-check it\'s still active and try again.');
        }
    }
}
