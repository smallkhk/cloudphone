<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Vmos\VmosClient;
use Illuminate\Http\Request;
use Throwable;

/**
 * Shows the raw VMOS API response for a handful of read-only endpoints.
 *
 * When a sync returns nothing it's rarely obvious why — the account may have no
 * products for the requested Android version, the credentials may be scoped
 * differently, or the payload shape may differ from the docs. Seeing the actual
 * JSON answers all of those at once.
 */
class DiagnosticsController extends Controller
{
    /** Read-only endpoints that are safe to call from a diagnostics screen. */
    public const PROBES = [
        'catalogue_13' => ['GET', '/vcpcloud/api/padApi/getCloudGoodList', ['androidVersion' => 13], 'Plan catalogue (Android 13)'],
        'catalogue_14' => ['GET', '/vcpcloud/api/padApi/getCloudGoodList', ['androidVersion' => 14], 'Plan catalogue (Android 14)'],
        'catalogue_all' => ['GET', '/vcpcloud/api/padApi/getCloudGoodList', [], 'Plan catalogue (no version filter)'],
        'pads' => ['POST', '/vcpcloud/api/padApi/userPadList', [], 'Your cloud phones'],
        'countries' => ['GET', '/vcpcloud/api/padApi/country', [], 'Supported countries'],
        'static_proxies' => ['GET', '/vcpcloud/api/padApi/proxyGoodList', [], 'Static residential proxy products'],
        'proxy_regions' => ['GET', '/vcpcloud/api/padApi/getProxyRegion', [], 'Proxy regions'],
        'my_proxies' => ['POST', '/vcpcloud/api/padApi/queryProxyList', ['current' => 1, 'size' => 20], 'Proxies you own'],
    ];

    public function index(Request $request, VmosClient $client)
    {
        $probe = $request->string('probe')->value();
        $result = null;
        $error = null;

        if ($probe && isset(self::PROBES[$probe])) {
            [$method, $path, $params] = self::PROBES[$probe];

            try {
                $result = $method === 'GET'
                    ? $client->get($path, $params)
                    : $client->post($path, $params);
            } catch (Throwable $e) {
                $error = $e->getMessage().(method_exists($e, 'getCode') && $e->getCode() ? " (code {$e->getCode()})" : '');
            }
        }

        return view('admin.diagnostics.index', [
            'probes' => self::PROBES,
            'probe' => $probe,
            'result' => $result,
            'error' => $error,
            'configured' => filled(config('vmos.access_key')) && filled(config('vmos.secret_key')),
        ]);
    }
}
