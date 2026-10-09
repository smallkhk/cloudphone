<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'crypto.usdt_bep20_address' => '0x1234567890123456789012345678901234567890',
            'crypto.ltc_address' => 'LReceivingAddressXXXXXXXXXXXXXXXX',
        ]);
        Cache::forget('ltc_usd_price');
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
        $this->assertSame('0x1234567890123456789012345678901234567890', $deposit->pay_to_address);
    }

    #[Test]
    public function a_customer_can_start_an_ltc_deposit_converted_at_the_live_price(): void
    {
        Http::fake(['*coingecko.com*' => Http::response(['litecoin' => ['usd' => 50.0]])]);

        $this->actingAs(User::factory()->create())
            ->post(route('wallet.deposit'), ['amount_usd' => 100, 'network' => 'LTC'])
            ->assertRedirect(route('wallet.index'));

        $deposit = WalletDeposit::first();
        $this->assertSame('LTC', $deposit->network);
        $this->assertSame('LTC', $deposit->currency);
        $this->assertSame('LReceivingAddressXXXXXXXXXXXXXXXX', $deposit->pay_to_address);
        $this->assertEquals(2.0, $deposit->amount_crypto);
        $this->assertEquals(100, $deposit->amount_usd);
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
        $payTo = '0x1234567890123456789012345678901234567890';
        $deposit = WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'network' => 'BEP20',
            'pay_to_address' => $payTo,
            'amount_crypto' => 50,
            'amount_usd' => 50,
            'tx_hash' => '0xdeposit-hash',
            'status' => WalletDeposit::STATUS_SUBMITTED,
        ]);

        Http::fake(['*bsc-dataseed.binance.org*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'result' => [
                'status' => '0x1',
                'logs' => [[
                    'address' => '0x55d398326f99059fF775485246999027B3197955',
                    'topics' => [
                        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                        '0x0000000000000000000000001111111111111111111111111111111111111111',
                        '0x'.str_pad(ltrim($payTo, '0x'), 64, '0', STR_PAD_LEFT),
                    ],
                    'data' => '0x2b5e3af16b1880000', // 50 USDT at 18 decimals
                ]],
            ],
        ])]);

        $this->artisan('wallet:verify-deposits')->assertExitCode(0);

        $this->assertSame(WalletDeposit::STATUS_CONFIRMED, $deposit->fresh()->status);
        $this->assertEquals(50, $user->fresh()->balance);
    }

    #[Test]
    public function verifying_a_confirmed_ltc_deposit_credits_the_balance(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $payTo = 'LReceivingAddressXXXXXXXXXXXXXXXX';
        $deposit = WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'network' => 'LTC',
            'currency' => 'LTC',
            'pay_to_address' => $payTo,
            'amount_crypto' => 2.0,
            'amount_usd' => 100,
            'tx_hash' => 'ltc-deposit-hash',
            'status' => WalletDeposit::STATUS_SUBMITTED,
        ]);

        Http::fake(['*blockcypher.com*' => Http::response([
            'confirmations' => 2,
            'double_spend' => false,
            'outputs' => [['value' => 200_000_000, 'addresses' => [$payTo]]],
        ])]);

        $this->artisan('wallet:verify-deposits')->assertExitCode(0);

        $this->assertSame(WalletDeposit::STATUS_CONFIRMED, $deposit->fresh()->status);
        $this->assertEquals(100, $user->fresh()->balance);
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
    public function a_pending_deposit_shows_a_qr_code_for_the_receiving_address(): void
    {
        $user = User::factory()->create();
        WalletDeposit::factory()->create([
            'user_id' => $user->id,
            'status' => WalletDeposit::STATUS_AWAITING_PAYMENT,
            'pay_to_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
        ]);

        $this->actingAs($user)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('paymentQr', false)
            ->assertSee('TReceivingAddressXXXXXXXXXXXXXXXXX');
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
