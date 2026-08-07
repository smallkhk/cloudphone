<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CryptoPayment;
use App\Models\Order;
use App\Services\Provisioning\CloudPhoneProvisioner;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->value();
        $search = $request->string('q')->trim()->value();

        $orders = Order::query()
            ->with(['user', 'sku', 'payments'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'status', 'search'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'sku', 'payments', 'cloudInstances']);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Marks a payment as received without an on-chain match — for when a customer
     * paid by another route (bank transfer, different wallet, manual settlement).
     */
    public function markPaid(Request $request, Order $order)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            return back()->with('error', 'This order is not awaiting payment.');
        }

        $payment = $order->latestPayment();
        $payment?->update([
            'status' => CryptoPayment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        return back()->with('status', "Order {$order->reference} marked as paid. Use \"Provision now\" to create the cloud phone.");
    }

    /** Runs (or retries) provisioning for a paid order. */
    public function provision(Order $order, CloudPhoneProvisioner $provisioner)
    {
        if (! in_array($order->status, [Order::STATUS_PAID, Order::STATUS_FAILED], true)) {
            return back()->with('error', 'Only paid or failed orders can be provisioned.');
        }

        try {
            $provisioner->provision($order);
        } catch (Throwable $e) {
            return back()->with('error', 'Provisioning failed: '.$e->getMessage());
        }

        return back()->with('status', "Provisioned {$order->reference}.");
    }

    public function cancel(Order $order)
    {
        if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELED], true)) {
            return back()->with('error', 'This order cannot be canceled.');
        }

        $order->update(['status' => Order::STATUS_CANCELED]);

        return back()->with('status', "Order {$order->reference} canceled.");
    }
}
