<?php

namespace Tests\Unit;

use App\Services\Vmos\VmosClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VmosClientSigningTest extends TestCase
{
    /**
     * Cross-checked against the worked example in the VMOS docs
     * (server/example.html#signing-algorithm):
     *   SK=9cucpjoyn4xxmkhj3q9el3ce, ts=1747555200,
     *   path=/vcpcloud/api/padApi/padInfo, body={"padCode":"AC32010601132"}
     *   => X-Sign = 483a4999d303307ef1b8b078b51e03fa0556547729c8a3c1470d2caf63e5f350
     */
    #[Test]
    public function it_signs_post_requests_exactly_like_the_docs_example(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $client = new VmosClient('https://api.vmoscloud.com', 'ak_test', '9cucpjoyn4xxmkhj3q9el3ce');

        $reflection = new \ReflectionMethod($client, 'sign');
        $reflection->setAccessible(true);

        $sign = $reflection->invoke($client, '1747555200', '/vcpcloud/api/padApi/padInfo', '{"padCode":"AC32010601132"}');

        $this->assertSame('483a4999d303307ef1b8b078b51e03fa0556547729c8a3c1470d2caf63e5f350', $sign);
    }

    #[Test]
    public function post_sends_headers_and_raw_json_body_that_match_the_signature(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['ok' => true]])]);

        $client = new VmosClient('https://api.vmoscloud.com', 'ak_test', 'sk_test');
        $client->post('/vcpcloud/api/padApi/padInfo', ['padCode' => 'AC32010601132']);

        Http::assertSent(function ($request) {
            $ts = $request->header('X-Timestamp')[0];
            $expected = hash('sha256', 'sk_test'.$ts.'/vcpcloud/api/padApi/padInfo'.$request->body());

            return $request->url() === 'https://api.vmoscloud.com/vcpcloud/api/padApi/padInfo'
                && $request->header('X-Access-Key')[0] === 'ak_test'
                && $request->header('X-Sign')[0] === $expected
                && $request->body() === '{"padCode":"AC32010601132"}';
        });
    }

    #[Test]
    public function get_signs_the_raw_query_string(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $client = new VmosClient('https://api.vmoscloud.com', 'ak_test', 'sk_test');
        $client->get('/vcpcloud/api/padApi/getCloudGoodList', ['androidVersion' => 13, 'goodIds' => '74,75']);

        Http::assertSent(function ($request) {
            $ts = $request->header('X-Timestamp')[0];
            $expected = hash('sha256', 'sk_test'.$ts.'/vcpcloud/api/padApi/getCloudGoodList'.'androidVersion=13&goodIds=74,75');

            return str_contains($request->url(), 'androidVersion=13')
                && $request->header('X-Sign')[0] === $expected;
        });
    }

    #[Test]
    public function upload_endpoints_are_signed_with_an_empty_body(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $client = new VmosClient('https://api.vmoscloud.com', 'ak_test', 'sk_test');
        $client->post('/vcpcloud/api/padApi/uploadFileV3', ['padCodes' => ['AC1'], 'url' => 'https://x/y.apk']);

        Http::assertSent(function ($request) {
            $ts = $request->header('X-Timestamp')[0];
            $expected = hash('sha256', 'sk_test'.$ts.'/vcpcloud/api/padApi/uploadFileV3');

            return $request->header('X-Sign')[0] === $expected;
        });
    }

    #[Test]
    public function it_throws_when_the_api_returns_a_non_200_code(): void
    {
        Http::fake(['*' => Http::response(['code' => 2019, 'msg' => 'Signature verification failed'])]);

        $client = new VmosClient('https://api.vmoscloud.com', 'ak_test', 'sk_test');

        $this->expectException(\App\Exceptions\VmosApiException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $client->post('/vcpcloud/api/padApi/padInfo', ['padCode' => 'AC1']);
    }
}
