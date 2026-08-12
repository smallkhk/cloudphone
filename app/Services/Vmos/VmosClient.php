<?php

namespace App\Services\Vmos;

use App\Exceptions\VmosApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level HTTP client for the VMOS Cloud OpenAPI (V2 SHA-256 request signing).
 *
 * Docs: https://cloud.vmoscloud.com/vmoscloud/doc/en/server/example.html#signing-algorithm
 *
 *   X-Sign = lowerHex( SHA256( SecretKey + X-Timestamp + path + bodyOrQuery ) )
 *
 * The string that gets signed must be byte-for-byte identical to what is put on
 * the wire, so this client builds the JSON body / query string itself instead of
 * letting the HTTP layer re-encode it.
 */
class VmosClient
{
    /** Endpoints whose body is intentionally NOT part of the signature. */
    protected const UNSIGNED_BODY_PATHS = [
        '/vcpcloud/api/padApi/uploadFile',
        '/vcpcloud/api/padApi/uploadFileV3',
        '/vcpcloud/api/padApi/asyncCmd',
        '/vcpcloud/api/padApi/syncCmd',
    ];

    /** Connection failures are retried; anything VMOS actually answers is not. */
    protected const MAX_ATTEMPTS = 3;

    protected string $baseUrl;

    protected string $accessKey;

    protected string $secretKey;

    public function __construct(?string $baseUrl = null, ?string $accessKey = null, ?string $secretKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('vmos.base_url'), '/');
        $this->accessKey = $accessKey ?? (string) config('vmos.access_key');
        $this->secretKey = $secretKey ?? (string) config('vmos.secret_key');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed> Decoded JSON response ("data" payload's parent, i.e. the full envelope).
     */
    public function get(string $path, array $query = []): array
    {
        $queryString = $this->buildQueryString($query);
        $url = $this->baseUrl.$path.($queryString !== '' ? '?'.$queryString : '');

        $response = $this->send($path, function () use ($path, $queryString, $url) {
            $timestamp = (string) time();

            return $this->pending()
                ->withHeaders($this->headers($timestamp, $this->sign($timestamp, $path, $queryString)))
                ->get($url);
        });

        return $this->handle($path, $response->status(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function post(string $path, array $params = []): array
    {
        $signBody = ! in_array($path, self::UNSIGNED_BODY_PATHS, true);

        $body = empty($params) ? '' : json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->send($path, function () use ($path, $body, $signBody) {
            $timestamp = (string) time();
            $sign = $this->sign($timestamp, $path, $signBody ? $body : '');

            return $this->pending()
                ->withHeaders($this->headers($timestamp, $sign, 'application/json'))
                ->withBody($body, 'application/json')
                ->post($this->baseUrl.$path);
        });

        return $this->handle($path, $response->status(), $response->body());
    }

    /**
     * Shared transport settings.
     *
     * `timeout` covers a slow reply; `connectTimeout` covers a TCP handshake
     * that never completes — a different failure with a different fix, and the
     * one shared hosts actually hit ("cURL error 28: Connection timed out
     * after 10002 milliseconds" is the connect timeout, not this client's 30s).
     */
    protected function pending(): PendingRequest
    {
        $request = Http::connectTimeout((int) config('vmos.connect_timeout', 15))
            ->timeout((int) config('vmos.timeout', 30));

        // Some shared hosts publish an IPv6 route they can't actually use, so
        // every request stalls on the AAAA address before falling back. Forcing
        // IPv4 is a no-op when the host has no IPv6 at all.
        if (config('vmos.force_ipv4', true) && defined('CURL_IPRESOLVE_V4')) {
            $request->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);
        }

        return $request;
    }

    /**
     * Runs a request, retrying only when the connection itself failed.
     *
     * The callback re-signs on every attempt rather than replaying the first
     * one: the signature covers a timestamp, so a replay seconds later could be
     * rejected as stale. Anything VMOS actually answers — including an error —
     * is returned as-is, since retrying a real API error just wastes time.
     *
     * @param  callable(): Response  $attempt
     *
     * @throws VmosApiException
     */
    protected function send(string $path, callable $attempt): Response
    {
        $lastError = null;

        for ($try = 1; $try <= self::MAX_ATTEMPTS; $try++) {
            try {
                return $attempt();
            } catch (ConnectionException $e) {
                $lastError = $e;

                Log::warning('vmos.connection_failed', [
                    'path' => $path,
                    'attempt' => $try,
                    'error' => $e->getMessage(),
                ]);

                if ($try < self::MAX_ATTEMPTS) {
                    usleep(500_000 * $try);
                }
            }
        }

        throw new VmosApiException(
            "Could not open a connection to VMOS ({$this->baseUrl}) after ".self::MAX_ATTEMPTS.' attempts. '
            .'This is your server failing to reach VMOS, not a rejected request — check outbound HTTPS from the host, '
            .'then try again. Original error: '.$lastError?->getMessage(),
            0,
            [],
            $lastError
        );
    }

    protected function headers(string $timestamp, string $sign, ?string $contentType = null): array
    {
        $headers = [
            'X-Access-Key' => $this->accessKey,
            'X-Timestamp' => $timestamp,
            'X-Sign' => $sign,
        ];

        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }

        return $headers;
    }

    protected function sign(string $timestamp, string $path, string $bodyOrQuery): string
    {
        return hash('sha256', $this->secretKey.$timestamp.$path.$bodyOrQuery);
    }

    /**
     * Builds a raw "key=value&key2=value2" string, matching the exact bytes sent
     * on the wire (VMOS's own sample clients do not URL-encode this either).
     *
     * @param  array<string, mixed>  $query
     */
    protected function buildQueryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $parts[] = $key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return implode('&', $parts);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws VmosApiException
     */
    protected function handle(string $path, int $status, string $body): array
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            Log::error('vmos.invalid_response', ['path' => $path, 'status' => $status, 'body' => $body]);

            throw new VmosApiException("VMOS API returned a non-JSON response for {$path} (HTTP {$status})");
        }

        $code = $data['code'] ?? null;

        if ($code !== 200) {
            Log::warning('vmos.api_error', ['path' => $path, 'code' => $code, 'msg' => $data['msg'] ?? null]);

            throw new VmosApiException($data['msg'] ?? "VMOS API error on {$path}", (int) ($code ?? 0), $data);
        }

        return $data;
    }
}
