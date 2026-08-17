<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveScreenTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected CloudInstance $device;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vmos.access_key' => 'ak', 'vmos.secret_key' => 'sk']);

        $this->owner = User::factory()->create();
        $this->device = CloudInstance::factory()->create([
            'user_id' => $this->owner->id,
            'pad_code' => 'AC90001',
        ]);
    }

    protected function fakeToken(string $token = 'sts-token-123'): void
    {
        Http::fake([
            '*stsTokenByPadCode' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['token' => $token]]),
            '*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);
    }

    #[Test]
    public function the_owner_gets_a_session_scoped_to_their_own_device(): void
    {
        $this->fakeToken();

        $response = $this->actingAs($this->owner)
            ->postJson(route('instances.screen-token', $this->device));

        $response->assertOk()
            ->assertJsonPath('token', 'sts-token-123')
            ->assertJsonPath('padCode', 'AC90001')
            ->assertJsonPath('userId', 'user-'.$this->owner->id)
            ->assertJsonPath('baseUrl', config('vmos.sdk_base_url'));

        // The token request must name this device, not the whole account.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'stsTokenByPadCode')
            && $request->body() === '{"padCode":"AC90001"}');
    }

    #[Test]
    public function the_api_keys_are_never_sent_to_the_browser(): void
    {
        $this->fakeToken();

        $body = $this->actingAs($this->owner)
            ->postJson(route('instances.screen-token', $this->device))
            ->getContent();

        $this->assertStringNotContainsString('ak', json_decode($body, true)['token']);
        $this->assertArrayNotHasKey('accessKey', json_decode($body, true));
        $this->assertArrayNotHasKey('secretKey', json_decode($body, true));
    }

    #[Test]
    public function another_customer_cannot_open_a_session_on_someone_elses_device(): void
    {
        $this->fakeToken();

        $this->actingAs(User::factory()->create())
            ->postJson(route('instances.screen-token', $this->device))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_page(): void
    {
        // A web route, so an expired session redirects rather than 401s — the
        // player treats a non-JSON reply as "sign in again".
        $this->post(route('instances.screen-token', $this->device))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function a_device_still_provisioning_has_no_screen_to_show(): void
    {
        $this->fakeToken();

        $pending = CloudInstance::factory()->create(['user_id' => $this->owner->id, 'pad_code' => null]);

        $this->actingAs($this->owner)
            ->postJson(route('instances.screen-token', $pending))
            ->assertStatus(409)
            ->assertJsonStructure(['error']);
    }

    #[Test]
    public function a_vmos_failure_is_reported_rather_than_throwing(): void
    {
        Http::fake(['*' => Http::response(['code' => 500, 'msg' => 'System is busy'])]);

        $this->actingAs($this->owner)
            ->postJson(route('instances.screen-token', $this->device))
            ->assertStatus(502)
            ->assertJsonPath('error', 'VMOS refused the session: System is busy');
    }

    #[Test]
    public function the_device_page_offers_the_live_screen(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $this->actingAs($this->owner)
            ->get(route('instances.show', $this->device))
            ->assertOk()
            ->assertSee('Live screen')
            ->assertSee('Take control of this phone')
            ->assertSee(route('instances.screen-token', $this->device));
    }
}
