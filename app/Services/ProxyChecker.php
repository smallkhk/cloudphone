<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Checks a customer-supplied proxy is reachable and reports its exit
 * location — done as a real request through the proxy from our own server to
 * a third-party geolocation service, independent of VMOS's own checkIP tool.
 *
 * Built after the owner found VMOS's checkIP reporting the wrong location
 * for a proxy that tested correctly everywhere else (including VMOS's own
 * website's proxy tool, not just ours) — so we stopped trusting it entirely
 * rather than just working around it for this one form.
 */
class ProxyChecker
{
    /**
     * @return array{ip: ?string, city: ?string, country: ?string}
     *
     * @throws RuntimeException
     */
    public function check(string $ip, int $port, ?string $account, ?string $password, string $proxyName): array
    {
        // socks5h (not socks5) so DNS is resolved through the proxy too —
        // closer to how the proxy actually behaves once applied to a device.
        $scheme = $proxyName === 'socks5' ? 'socks5h' : 'http';
        $auth = $account !== null && $account !== '' ? rawurlencode($account).':'.rawurlencode($password ?? '').'@' : '';
        $proxyUrl = "{$scheme}://{$auth}{$ip}:{$port}";

        try {
            $response = Http::timeout(10)
                ->withOptions(['proxy' => $proxyUrl])
                ->get('http://ip-api.com/json/', ['fields' => 'status,message,country,city,query']);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not connect through the proxy: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Geolocation lookup failed with HTTP {$response->status()}.");
        }

        $data = $response->json() ?? [];

        if (($data['status'] ?? null) !== 'success') {
            throw new RuntimeException('Geolocation lookup failed: '.($data['message'] ?? 'unknown reason'));
        }

        return [
            'ip' => $data['query'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
        ];
    }
}
