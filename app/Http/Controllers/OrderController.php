<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\Order;
use App\Models\Sku;
use App\Services\Payments\CryptoPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Auth::user()->orders()->with(['sku', 'payments' => fn ($q) => $q->latest('id')])->latest()->paginate(15);

        return view('orders.index', compact('orders'));
    }

    public function store(Request $request, CryptoPaymentService $payments)
    {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer', 'exists:skus,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'auto_renew' => ['sometimes', 'boolean'],
            // A 2-letter VMOS country code the customer picked at checkout, or
            // blank to let VMOS assign one. Not validated against the live
            // region list here — VMOS itself is the authority on what's a
            // valid code, and rejects a bad one clearly during provisioning.
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $sku = Sku::available()->findOrFail($validated['sku_id']);
        $quantity = $validated['quantity'];

        try {
            // The order and its payment quote must succeed together — otherwise a
            // misconfigured store leaves orphaned pending orders behind.
            $order = DB::transaction(function () use ($sku, $quantity, $validated, $request, $payments) {
                $order = Auth::user()->orders()->create([
                    'sku_id' => $sku->id,
                    'quantity' => $quantity,
                    'unit_price' => $sku->price,
                    'total_price' => round($sku->price * $quantity, 2),
                    'auto_renew' => $request->boolean('auto_renew', true),
                    'country_code' => strtoupper($validated['country_code'] ?? '') ?: $sku->default_country_code,
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
