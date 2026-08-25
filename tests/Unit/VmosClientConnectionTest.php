<?php

namespace Tests\Unit;

use App\Exceptions\VmosApiException;
use App\Services\Vmos\VmosClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VmosClientConnectionTest extends TestCase
{
    protected function client(): VmosClient
    {
        return new VmosClient('https://api.vmoscloud.test', 'access-key', 'secret-key');
    }

    #[Test]
    public function it_retries_when_the_connection_never_opens(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Connection timed out after 10002 milliseconds');
        });

        try {
            $this->client()->get('/vcpcloud/api/padApi/userPadList');
            $this->fail('Expected a VmosApiException.');
        } catch (VmosApiException $e) {
            $this->assertStringContainsString('Could not open a connection to the cloud phone provider', $e->getMessage());
            // The underlying cURL message is kept for the log/admin screen.
            $this->assertStringContainsString('Connection timed out', $e->getMessage());
        }

        $this->assertSame(3, $attempts, 'A connection failure should be retried, not reported on the first try.');
    }

    #[Test]
    public function it_succeeds_when_a_later_attempt_connects(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('cURL error 28: Connection timed out');
            }

            return Http::response(['code' => 200, 'data' => ['ok' => true]]);
        });

        $response = $this->client()->get('/vcpcloud/api/padApi/userPadList');

        $this->assertSame(2, $attempts);
        $this->assertTrue($response['data']['ok']);
    }

    #[Test]
    public function it_does_not_retry_an_error_vmos_actually_returned(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response(['code' => 500, 'msg' => 'System is busy']);
        });

        try {
            $this->client()->get('/vcpcloud/api/padApi/userPadList');
            $this->fail('Expected a VmosApiException.');
        } catch (VmosApiException $e) {
            $this->assertSame('System is busy', $e->getMessage());
        }

        $this->assertSame(1, $attempts, 'A real API error must not be retried.');
    }

    #[Test]
    public function a_post_is_re_signed_on_every_attempt_rather_than_replayed(): void
    {
        $signatures = [];

        Http::fake(function ($request) use (&$signatures) {
            $signatures[] = $request->header('X-Sign')[0] ?? null;

            if (count($signatures) < 3) {
                throw new ConnectionException('cURL error 28: Connection timed out');
            }

            return Http::response(['code' => 200, 'data' => []]);
        });

        $this->client()->post('/vcpcloud/api/padApi/padOperate', ['padCodes' => ['AC1']]);

        $this->assertCount(3, $signatures);
        $this->assertNotContains(null, $signatures, 'Every attempt must carry a signature.');
    }
}
