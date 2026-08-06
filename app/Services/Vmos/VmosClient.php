<?php

namespace App\Services\Vmos;

use App\Exceptions\VmosApiException;
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
        $timestamp = (string) time();
        $sign = $this->sign($timestamp, $path, $queryString);

        $url = $this->baseUrl.$path.($queryString !== '' ? '?'.$queryString : '');

        $response = Http::withHeaders($this->headers($timestamp, $sign))
            ->timeout(30)
            ->get($url);

        return $this->handle($path, $response->status(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function post(string $path, array $params = []): array
    {
        $signBody = ! in_array($path, self::UNSIGNED_BODY_PATHS, true);

        $body = empty($params) ? '' : json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $sign = $this->sign($timestamp, $path, $signBody ? $body : '');

        $response = Http::withHeaders($this->headers($timestamp, $sign, 'application/json'))
            ->timeout(30)
            ->withBody($body, 'application/json')
            ->post($this->baseUrl.$path);

        return $this->handle($path, $response->status(), $response->body());
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
