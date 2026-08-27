<?php

namespace Tests\Unit;

use App\Services\ProxyChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ProxyCheckerTest extends TestCase
{
    #[Test]
    public function it_reports_the_exit_location_for_a_reachable_proxy(): void
    {
        Http::fake(['ip-api.com/*' => Http::response([
            'status' => 'success', 'city' => 'Tokyo', 'country' => 'Japan', 'query' => '203.0.113.9',
        ])]);

        $result = (new ProxyChecker)->check('10.0.0.5', 1080, 'user', 'pass', 'socks5');

        $this->assertSame('Tokyo', $result['city']);
        $this->assertSame('Japan', $result['country']);
        $this->assertSame('203.0.113.9', $result['ip']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ip-api.com'));
    }

    #[Test]
    public function it_builds_an_http_proxy_url_without_credentials_when_none_given(): void
    {
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'city' => null, 'country' => null, 'query' => '1.2.3.4'])]);

        $result = (new ProxyChecker)->check('10.0.0.5', 8080, null, null, 'http-relay');

        $this->assertSame('1.2.3.4', $result['ip']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ip-api.com'));
    }

    #[Test]
    public function it_throws_when_the_geolocation_lookup_reports_failure(): void
    {
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'fail', 'message' => 'reserved range'])]);

        $this->expectException(RuntimeException::class);

        (new ProxyChecker)->check('10.0.0.5', 1080, null, null, 'socks5');
    }

    #[Test]
    public function it_throws_when_the_connection_itself_fails(): void
    {
        Http::fake(['ip-api.com/*' => fn () => throw new ConnectionException('could not resolve host')]);

        $this->expectException(RuntimeException::class);

        (new ProxyChecker)->check('10.0.0.5', 1080, null, null, 'socks5');
    }
}
