<?php

namespace App\Http\Controllers;

use App\Services\ProxyChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lets a customer check their own proxy is reachable before buying a device
 * with it — this runs independently of VMOS (see ProxyChecker), so it works
 * standalone at checkout rather than only after a device already exists (see
 * DeviceControlController::testProxy for the equivalent on an owned device).
 */
class ProxyTestController extends Controller
{
    public function test(Request $request, ProxyChecker $checker)
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'account' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'proxy_name' => ['required', 'in:socks5,http-relay'],
        ]);

        try {
            $result = $checker->check(
                $data['ip'], (int) $data['port'], $data['account'] ?? null, $data['password'] ?? null, $data['proxy_name']
            );

            $where = collect([$result['city'], $result['country']])->filter()->implode(', ');

            return response()->json([
                'ok' => true,
                'message' => 'Proxy is reachable'.($where ? " — appears to be in {$where}." : '.'),
            ]);
        } catch (Throwable $e) {
            Log::warning('proxy_test.failed', [
                'user_id' => Auth::id(),
                'ip' => $data['ip'],
                'port' => $data['port'],
                'proxy_name' => $data['proxy_name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Proxy check failed. Double-check the details and try again.'], 422);
        }
    }
}
