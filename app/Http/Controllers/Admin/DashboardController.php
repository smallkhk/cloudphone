<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $completed = Order::where('status', Order::STATUS_COMPLETED);

        return view('admin.dashboard', [
            'stats' => [
                'revenue_total' => (clone $completed)->sum('total_price'),
                'revenue_month' => (clone $completed)->where('created_at', '>=', now()->startOfMonth())->sum('total_price'),
                'orders_total' => Order::count(),
                'orders_pending' => Order::where('status', Order::STATUS_PENDING_PAYMENT)->count(),
                'orders_failed' => Order::where('status', Order::STATUS_FAILED)->count(),
                'users_total' => User::count(),
                'users_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'devices_total' => CloudInstance::count(),
                'devices_online' => CloudInstance::where('online', true)->count(),
                'devices_stock' => CloudInstance::whereNull('user_id')->count(),
                'devices_expiring' => CloudInstance::whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
                'payments_awaiting' => CryptoPayment::where('status', CryptoPayment::STATUS_SUBMITTED)->count(),
                'skus_active' => Sku::where('active', true)->count(),
            ],
            'recentOrders' => Order::with(['user', 'sku'])->latest()->limit(8)->get(),
            'recentUsers' => User::latest()->limit(5)->get(),
            'setupComplete' => [
                'vmos' => filled(config('vmos.access_key')) && filled(config('vmos.secret_key')),
                'crypto' => filled(config('crypto.usdt_trc20_address')),
                'skus' => Sku::where('active', true)->exists(),
                'mail' => config('mail.default') !== 'log',
            ],
        ]);
    }
}
