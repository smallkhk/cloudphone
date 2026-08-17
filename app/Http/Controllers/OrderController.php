<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Sku;
use App\Services\Payments\CryptoPaymentService;
use App\Services\Vmos\VmosProxyCatalog;
use App\Services\Vmos\VmosRegionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(protected VmosProxyCatalog $proxyCatalog) {}

    public function index()
    {
        $orders = Auth::user()->orders()->with(['sku', 'payments' => fn ($q) => $q->latest('id')])->latest()->paginate(15);

        return view('orders.index', compact('orders'));
    }

    public function store(Request $request, CryptoPaymentService $payments)
    {
        // Normalise before validating, same convention as SIM regeneration —
        // the "in:" list below is upper-case.
        if ($request->filled('country_code')) {
            $request->merge(['country_code' => strtoupper($request->string('country_code')->value())]);
        }

        $validated = $request->validate([
            'sku_id' => ['required', 'integer', 'exists:skus,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'auto_renew' => ['sometimes', 'boolean'],
            // A VMOS country code the customer picked at checkout, or blank to
            // let VMOS assign one. Restricted to the confirmed purchase-region
            // list (matching VMOS's own console) rather than just any 2-letter
            // code — the live "supported countries" list is broader than what
            // VMOS's purchase flow actually accepts, so a code from that list
            // could pass validation here and still fail at provisioning.
            'country_code' => ['nullable', 'string', 'in:'.implode(',', array_keys(VmosRegionCatalog::PURCHASE_REGIONS))],

            // Proxy add-on — either a VMOS residential proxy bought alongside
            // the device, or the customer's own.
            'proxy_mode' => ['nullable', 'in:vmos,custom'],
            'proxy_good_id' => ['required_if:proxy_mode,vmos', 'integer'],
            'proxy_country' => ['required_if:proxy_mode,vmos', 'string', 'max:8'],
            'proxy_ip' => ['required_if:proxy_mode,custom', 'string', 'max:255'],
            'proxy_port' => ['required_if:proxy_mode,custom', 'integer', 'min:1', 'max:65535'],
            'proxy_account' => ['nullable', 'string', 'max:255'],
            'proxy_password' => ['nullable', 'string', 'max:255'],
            'proxy_name' => ['required_if:proxy_mode,custom', 'in:socks5,http-relay'],
            'proxy_type' => ['required_if:proxy_mode,custom', 'in:proxy,vpn'],
        ]);

        $sku = Sku::available()->findOrFail($validated['sku_id']);
        $quantity = $validated['quantity'];
        $proxy = $this->resolveProxy($validated);

        try {
            // The order and its payment quote must succeed together — otherwise a
            // misconfigured store leaves orphaned pending orders behind.
            $order = DB::transaction(function () use ($sku, $quantity, $validated, $proxy, $request, $payments) {
                $order = Auth::user()->orders()->create([
                    'sku_id' => $sku->id,
                    'quantity' => $quantity,
                    'unit_price' => $sku->price,
                    'total_price' => round($sku->price * $quantity + $proxy['price'], 2),
                    'auto_renew' => $request->boolean('auto_renew', true),
                    'country_code' => strtoupper($validated['country_code'] ?? '') ?: $sku->default_country_code,
                    'proxy_mode' => $proxy['mode'],
                    'proxy_config' => $proxy['config'],
                    'proxy_price' => $proxy['price'],
                    'proxy_cost_price' => $proxy['cost_price'],
                ]);

                $payments->createForOrder($order);

                return $order;
            });
        } catch (PaymentsNotConfiguredException $e) {
            Log::error('checkout.payments_not_configured', ['error' => $e->getMessage()]);

            return back()->with('error', Auth::user()->is_admin
                ? $e->getMessage()
                : 'Checkout is temporarily unavailable. Please contact support — we\'ve been notified.');
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order created — send the exact USDT (TRC20) amount shown below to complete your purchase.');
    }

    /**
     * Turns the checkout proxy fields into what an Order needs to store,
     * pricing a VMOS proxy add-on the same way SKUs are priced (VMOS cost +
     * the site's markup). A custom proxy costs the customer nothing extra —
     * it's theirs already.
     *
     * @param  array<string, mixed>  $validated
     * @return array{mode: ?string, config: ?array, price: float, cost_price: float}
     */
    protected function resolveProxy(array $validated): array
    {
        $mode = $validated['proxy_mode'] ?? null;

        if ($mode === 'custom') {
            return [
                'mode' => 'custom',
                'config' => [
                    'ip' => $validated['proxy_ip'],
                    'port' => (int) $validated['proxy_port'],
                    'account' => $validated['proxy_account'] ?? null,
                    'password' => $validated['proxy_password'] ?? null,
                    'proxy_name' => $validated['proxy_name'],
                    'proxy_type' => $validated['proxy_type'],
                ],
                'price' => 0,
                'cost_price' => 0,
            ];
        }

        if ($mode === 'vmos') {
            $product = collect($this->proxyCatalog->products())
                ->first(fn ($p) => (int) ($p['proxyGoodId'] ?? 0) === (int) $validated['proxy_good_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'proxy_good_id' => 'That proxy package is no longer available — please pick another.',
                ]);
            }

            $region = collect($this->proxyCatalog->regions())
                ->first(fn ($r) => strcasecmp((string) ($r['country'] ?? ''), $validated['proxy_country']) === 0);

            $cost = round(((float) ($product['proxyGoodPrice'] ?? 0)) / 100, 2);
            $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);

            return [
                'mode' => 'vmos',
                'config' => [
                    'good_id' => (int) $validated['proxy_good_id'],
                    'country' => strtoupper($validated['proxy_country']),
                    'proxy_address' => $region['countryZh'] ?? $validated['proxy_country'],
                ],
                'price' => round($cost * $markup, 2),
                'cost_price' => $cost,
            ];
        }

        return ['mode' => null, 'config' => null, 'price' => 0, 'cost_price' => 0];
    }

    public function show(Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $order->load(['sku', 'payments' => fn ($q) => $q->latest('id'), 'cloudInstances', 'emailAccounts', 'phoneNumbers']);

        return view('orders.show', [
            'order' => $order,
            'payment' => $order->payments->first(),
        ]);
    }

    public function submitPayment(Request $request, Order $order, CryptoPaymentService $payments)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'tx_hash' => ['required', 'string', 'min:10', 'max:128'],
        ]);

        $payment = $order->latestPayment();
        abort_if(! $payment, 404, 'No pending payment found for this order.');

        $payments->submitTransactionHash($payment, trim($validated['tx_hash']));

        return redirect()->route('orders.show', $order)
            ->with('status', 'Thanks — we\'ll confirm your payment on-chain shortly (usually within a couple of minutes).');
    }
}
