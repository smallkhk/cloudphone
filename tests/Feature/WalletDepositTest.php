<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WalletDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
            'crypto.usdt_bep20_address' => '0xReceivingAddress0000000000000000000001',
            'crypto.bscscan_api_key' => 'test-key',
        ]);
    }

    #[Test]
    public function a_guest_cannot_reach_the_wallet_page(): void
    {
        $this->get(route('wallet.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_customer_can_start_a_trc20_deposit(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('wallet.deposit'), ['amount_usd' => 50, 'network' => 'TRC20'])
            ->assertRedirect(route('wallet.index'));

        $deposit = WalletDeposit::first();
        $this->assertSame('TRC20', $deposit->network);
        $this->assertEquals(50, $deposit->amount_usd);
        $this->assertSame('TReceivingAddressXXXXXXXXXXXXXXXXX', $deposit->pay_to_address);
    }

    #[Test]
    public function a_customer_can_start_a_bep20_deposit(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('wallet.deposit'), ['amount_usd' => 50, 'network' => 'BEP20'])
            ->assertRedirect(route('wallet.index'));

        $deposit = WalletDeposit::first();
        $this->assertSame('BEP20', $deposit->network);
        $this->assertSame('0xReceivingAddress0000000000000000000001', $deposit->pay_to_address);
    }

    #[Test]
    public function a_deposit_below_the_minimum_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('wallet.deposit'), ['amount_usd' => 1, 'network' => 'TRC20'])
            ->assertSessionHasErrors('amount_usd');

        $this->assertSame(0, WalletDeposit::count());
    }

    #[Test]
    public function submitting_a_tx_hash_marks_the_deposit_submitted(): void
    {
        $user = User::factory()->create();
        $deposit = WalletDeposit::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('wallet.deposit.tx', $deposit), ['tx_hash' => 'somehash1234567890'])
            ->assertRedirect(route('wallet.index'));

        $this->assertSame(WalletDeposit::STATUS_SUBMITTED, $deposit->fresh()->status);
    }

    #[Test]
    public function a_customer_cannot_submit_a_tx_hash_for_someone_elses_deposit(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $deposit = WalletDeposit::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->post(route('wallet.deposit.tx', $deposit), ['tx_hash' => 'somehash1234567890'])
            ->assertForbidden();
    }

    #[Test]
    public function verifying_a_confirmed_trc20_deposit_credits_the_balance(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $deposit = WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'network' => 'TRC20',
            'pay_to_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
            'amount_crypto' => 50,
            'amount_usd' => 50,
            'tx_hash' => 'deposit-hash',
            'status' => WalletDeposit::STATUS_SUBMITTED,
        ]);

        Http::fake(['api.trongrid.io/*' => Http::response([
            'success' => true,
            'data' => [[
                'transaction_id' => 'deposit-hash',
                'to' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
                'value' => '50000000',
                'token_info' => ['symbol' => 'USDT', 'decimals' => 6],
            ]],
        ])]);

        $this->artisan('wallet:verify-deposits')->assertExitCode(0);

        $this->assertSame(WalletDeposit::STATUS_CONFIRMED, $deposit->fresh()->status);
        $this->assertEquals(50, $user->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'wallet_deposit_id' => $deposit->id,
            'amount' => 50,
        ]);
    }

    #[Test]
    public function verifying_a_confirmed_bep20_deposit_credits_the_balance(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $deposit = WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'network' => 'BEP20',
            'pay_to_address' => '0xReceivingAddress0000000000000000000001',
            'amount_crypto' => 50,
            'amount_usd' => 50,
            'tx_hash' => '0xdeposit-hash',
            'status' => WalletDeposit::STATUS_SUBMITTED,
        ]);

        Http::fake(['api.bscscan.com/*' => Http::response([
            'status' => '1', 'message' => 'OK',
            'result' => [[
                'hash' => '0xdeposit-hash',
                'to' => '0xReceivingAddress0000000000000000000001',
                'value' => '50000000000000000000',
                'tokenDecimal' => '18',
            ]],
        ])]);

        $this->artisan('wallet:verify-deposits')->assertExitCode(0);

        $this->assertSame(WalletDeposit::STATUS_CONFIRMED, $deposit->fresh()->status);
        $this->assertEquals(50, $user->fresh()->balance);
    }

    #[Test]
    public function an_unconfirmed_deposit_is_left_pending(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $deposit = WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'network' => 'TRC20',
            'tx_hash' => 'deposit-hash',
            'status' => WalletDeposit::STATUS_SUBMITTED,
        ]);

        Http::fake(['api.trongrid.io/*' => Http::response(['success' => true, 'data' => []])]);

        $this->artisan('wallet:verify-deposits');

        $this->assertSame(WalletDeposit::STATUS_SUBMITTED, $deposit->fresh()->status);
        $this->assertEquals(0, $user->fresh()->balance);
    }

    #[Test]
    public function an_expired_unpaid_quote_is_marked_expired(): void
    {
        $deposit = WalletDeposit::factory()->create([
            'status' => WalletDeposit::STATUS_AWAITING_PAYMENT,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('wallet:verify-deposits');

        $this->assertSame(WalletDeposit::STATUS_EXPIRED, $deposit->fresh()->status);
    }
}
