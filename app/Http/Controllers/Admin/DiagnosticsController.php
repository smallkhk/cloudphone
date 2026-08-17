<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Vmos\VmosClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        'email_services' => ['POST', '/vcpcloud/api/padApi/getEmailServiceList', [], 'Email registration services (GitHub, TikTok…)'],
        'email_types' => ['POST', '/vcpcloud/api/padApi/getEmailTypeList', [], 'Email account types & stock'],
        'my_emails' => ['POST', '/vcpcloud/api/padApi/getEmailOrder', ['current' => 1, 'size' => 20], 'Email accounts you own'],
        'sms_services' => ['POST', '/vcpcloud/api/padApi/getSmsServiceList', [], 'UNCONFIRMED — phone-number registration services'],
        'sms_types' => ['POST', '/vcpcloud/api/padApi/getSmsTypeList', [], 'UNCONFIRMED — phone-number types & stock'],
        'my_sms' => ['POST', '/vcpcloud/api/padApi/getSmsOrder', ['current' => 1, 'size' => 20], 'UNCONFIRMED — phone numbers you own'],
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
            'connectivity' => $request->boolean('connectivity') ? $this->connectivity() : null,
        ]);
    }

    /**
     * Can this server reach VMOS at all?
     *
     * "cURL error 28: Connection timed out" means the TCP handshake never
     * completed — nothing to do with credentials or signing. Separating DNS
     * from the connection tells you whether it's a name that won't resolve or
     * a route that won't open, which are different fixes.
     *
     * @return array<string, mixed>
     */
    protected function connectivity(): array
    {
        $host = parse_url((string) config('vmos.base_url'), PHP_URL_HOST) ?: 'api.vmoscloud.com';

        $started = microtime(true);
        $addresses = @gethostbynamel($host);
        $dnsMs = (int) round((microtime(true) - $started) * 1000);

        $result = [
            'host' => $host,
            'dns_ok' => is_array($addresses) && $addresses !== [],
            'dns_ms' => $dnsMs,
            'addresses' => is_array($addresses) ? $addresses : [],
            'force_ipv4' => (bool) config('vmos.force_ipv4', true),
            'connect_timeout' => (int) config('vmos.connect_timeout', 15),
        ];

        if (! $result['dns_ok']) {
            $result['connect_ok'] = false;
            $result['message'] = "DNS lookup for {$host} failed on this server. Check the host's resolver before anything else.";

            return $result;
        }

        // An unauthenticated HEAD: we only care whether the socket opens, so a
        // 401/403/404 from VMOS is still a success for this test.
        $started = microtime(true);

        try {
            $status = Http::connectTimeout((int) config('vmos.connect_timeout', 15))
                ->timeout((int) config('vmos.timeout', 30))
                ->withOptions(config('vmos.force_ipv4', true) && defined('CURL_IPRESOLVE_V4')
                    ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
                    : [])
                ->get(rtrim((string) config('vmos.base_url'), '/').'/')
                ->status();

            $result['connect_ok'] = true;
            $result['connect_ms'] = (int) round((microtime(true) - $started) * 1000);
            $result['message'] = "Reached {$host} (HTTP {$status}). Outbound HTTPS from this server is working, "
                .'so any failure above is VMOS answering, not a network problem.';
        } catch (Throwable $e) {
            $result['connect_ok'] = false;
            $result['connect_ms'] = (int) round((microtime(true) - $started) * 1000);
            $result['message'] = 'Could not open a connection: '.$e->getMessage();
        }

        return $result;
    }
}
