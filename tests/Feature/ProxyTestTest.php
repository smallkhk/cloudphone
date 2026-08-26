<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProxyTestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_in_customer_can_test_a_proxy_before_buying(): void
    {
        Http::fake(['*/checkIP' => Http::response([
            'code' => 200, 'msg' => 'success',
            'data' => ['city' => 'Los Angeles', 'country' => 'US'],
        ])]);

        $this->actingAs(User::factory()->create())
            ->postJson('/proxy/test', [
                'ip' => '10.0.0.5', 'port' => 1080, 'proxy_name' => 'socks5',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'message' => 'Proxy is reachable — appears to be in Los Angeles, US.']);
    }

    #[Test]
    public function a_guest_cannot_test_a_proxy(): void
    {
        $this->postJson('/proxy/test', ['ip' => '10.0.0.5', 'port' => 1080, 'proxy_name' => 'socks5'])
            ->assertUnauthorized();
    }

    #[Test]
    public function an_unreachable_proxy_reports_failure_without_leaking_upstream_text(): void
    {
        Http::fake(['*/checkIP' => Http::response(['code' => 500, 'msg' => 'timeout after 5s'], 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/proxy/test', ['ip' => '10.0.0.5', 'port' => 1080, 'proxy_name' => 'socks5'])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'message' => 'Proxy check failed. Double-check the details and try again.']);
    }

    #[Test]
    public function invalid_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/proxy/test', ['ip' => '10.0.0.5', 'port' => 99999, 'proxy_name' => 'socks5'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('port');
    }
}
