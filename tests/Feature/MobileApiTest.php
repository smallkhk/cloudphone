<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\CloudInstance;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tokenFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue($user)];
    }

    // --- Auth ----------------------------------------------------------------

    #[Test]
    public function registering_asks_for_the_email_code_and_gives_no_token_yet(): void
    {
        $this->postJson('/api/mobile/v1/auth/register', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertOk()->assertJson(['otp_required' => true])->assertJsonMissing(['token']);

        $this->assertNull(User::firstWhere('email', 'ada@example.com')->email_verified_at);
    }

    #[Test]
    public function the_right_code_returns_a_token_that_works(): void
    {
        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'), 'email_otp_expires_at' => now()->addMinutes(10),
        ]);

        $token = $this->postJson('/api/mobile/v1/auth/verify-otp', ['email' => $user->email, 'code' => '123456'])
            ->assertOk()->json('token');

        $this->getJson('/api/mobile/v1/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('user.email', $user->email);
    }

    #[Test]
    public function five_wrong_codes_burn_the_code(): void
    {
        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'), 'email_otp_expires_at' => now()->addMinutes(10),
        ]);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/mobile/v1/auth/verify-otp', ['email' => $user->email, 'code' => '000000'])->assertStatus(422);
        }

        // Even the right code no longer works — a new one must be requested.
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['email' => $user->email, 'code' => '123456'])->assertStatus(422);
        $this->assertNull($user->fresh()->email_otp);
    }

    #[Test]
    public function logging_in_unverified_sends_a_code_instead_of_a_token(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('password')]);

        $this->postJson('/api/mobile/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJson(['otp_required' => true])->assertJsonMissing(['token']);
    }

    #[Test]
    public function logging_in_verified_returns_a_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $token = $this->postJson('/api/mobile/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('token');

        $this->postJson('/api/mobile/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->getJson('/api/mobile/v1/me', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }

    #[Test]
    public function wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->postJson('/api/mobile/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(422);
    }

    #[Test]
    public function requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/mobile/v1/devices')->assertStatus(401);
        $this->postJson('/api/mobile/v1/wallet/deposits', ['amount_usd' => 10, 'network' => 'TRC20'])->assertStatus(401);
    }

    // --- Reads ---------------------------------------------------------------

    #[Test]
    public function devices_lists_only_my_own(): void
    {
        $me = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $me->id, 'pad_code' => 'AC1']);
        CloudInstance::factory()->create(['pad_code' => 'AC2']);

        $this->getJson('/api/mobile/v1/devices', $this->tokenFor($me))
            ->assertOk()->assertJsonCount(1, 'devices')->assertJsonPath('devices.0.pad_code', 'AC1');
    }

    #[Test]
    public function someone_elses_device_is_forbidden(): void
    {
        $other = CloudInstance::factory()->create(['pad_code' => 'AC9']);

        $this->getJson("/api/mobile/v1/devices/{$other->id}", $this->tokenFor(User::factory()->create()))->assertForbidden();
    }

    #[Test]
    public function catalog_lists_cloud_phone_plans(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'data' => []])]);
        Sku::factory()->create(['name' => 'Phone X', 'price' => 9.5, 'type' => Sku::TYPE_CLOUD_PHONE]);

        $this->getJson('/api/mobile/v1/catalog', $this->tokenFor(User::factory()->create()))
            ->assertOk()->assertJsonPath('plans.0.name', 'Phone X')->assertJsonPath('plans.0.price', 9.5);
    }

    // --- Actions go through the website's own controllers ---------------------

    #[Test]
    public function restarting_my_device_returns_the_sites_message_as_json(): void
    {
        $me = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $me->id, 'pad_code' => 'AC1']);

        Http::fake(['*/vcpcloud/api/padApi/restart' => Http::response(['code' => 200, 'data' => [['taskId' => 7]]])]);

        $this->postJson("/api/mobile/v1/devices/{$device->id}/restart", [], $this->tokenFor($me))
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Restart requested.']);
    }

    #[Test]
    public function restarting_someone_elses_device_is_forbidden(): void
    {
        $device = CloudInstance::factory()->create(['pad_code' => 'AC1']);

        $this->postJson("/api/mobile/v1/devices/{$device->id}/restart", [], $this->tokenFor(User::factory()->create()))
            ->assertForbidden();
    }

    #[Test]
    public function validation_errors_come_back_as_json(): void
    {
        $me = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $me->id, 'pad_code' => 'AC1']);

        $this->postJson("/api/mobile/v1/devices/{$device->id}/gps", ['latitude' => 500], $this->tokenFor($me))
            ->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    #[Test]
    public function a_flashed_error_becomes_a_422_with_the_message(): void
    {
        $me = User::factory()->create(['balance' => 0]);
        $sku = Sku::factory()->create(['price' => 10, 'type' => Sku::TYPE_CLOUD_PHONE]);

        $this->postJson('/api/mobile/v1/orders', ['sku_id' => $sku->id, 'quantity' => 1, 'pay_with_balance' => 1], $this->tokenFor($me))
            ->assertStatus(422)->assertJson(['ok' => false])
            ->assertJsonPath('message', 'Not enough wallet balance to cover this order. Please top up your wallet and try again.');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function creating_a_wallet_deposit_returns_its_id(): void
    {
        config(['crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX']);
        $me = User::factory()->create();

        $response = $this->postJson('/api/mobile/v1/wallet/deposits', ['amount_usd' => 25, 'network' => 'TRC20'], $this->tokenFor($me))
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(WalletDeposit::first()->id, $response->json('data.newDepositId'));

        $this->getJson('/api/mobile/v1/wallet', $this->tokenFor($me))
            ->assertOk()->assertJsonPath('deposits.0.amount_usd', 25)->assertJsonPath('networks.0.code', 'TRC20');
    }

    // --- Live screen ---------------------------------------------------------

    #[Test]
    public function live_screen_link_opens_a_signed_page_and_issues_a_session(): void
    {
        $me = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $me->id, 'pad_code' => 'AC1']);

        $url = $this->postJson("/api/mobile/v1/devices/{$device->id}/live", [], $this->tokenFor($me))->assertOk()->json('url');

        $page = $this->get($url)->assertOk()->assertSee('pad-stage', false);

        preg_match("/tokenUrl: '(.*?)'/", $page->getContent(), $m);
        $tokenUrl = json_decode('"'.$m[1].'"');

        Http::fake(['*/vcpcloud/api/padApi/stsTokenByPadCode' => Http::response(['code' => 200, 'data' => ['token' => 'sts-1']])]);

        $this->postJson($tokenUrl)->assertOk()->assertJson(['token' => 'sts-1', 'padCode' => 'AC1', 'userId' => "user-{$me->id}"]);
    }

    #[Test]
    public function the_live_page_rejects_a_tampered_link(): void
    {
        $device = CloudInstance::factory()->create(['pad_code' => 'AC1']);
        $other = CloudInstance::factory()->create(['pad_code' => 'AC2']);

        $url = URL::temporarySignedRoute('mobile.live', now()->addMinutes(5), ['instance' => $device->id, 'native' => 1]);

        $this->get(str_replace("/live/{$device->id}", "/live/{$other->id}", $url))->assertForbidden();
    }
}
