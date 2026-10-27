<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletDeposit;
use App\Services\Payments\Bep20AutoMatcher;
use App\Services\Payments\TxHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Bep20AutoMatchTest extends TestCase
{
    use RefreshDatabase;

    protected const PAY_TO = '0x1234567890123456789012345678901234567890';

    protected const HASH = '0xa3778301ebea7f1579b20c2f65621e3cf114827230976ae0ebdf88333ef4c2a3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['crypto.usdt_bep20_address' => self::PAY_TO]);
    }

    protected function fakeIncoming(float $amount, int $timestamp): void
    {
        Http::fake(['*publicnode.com*' => function ($request) use ($amount, $timestamp) {
            return $request['method'] === 'eth_blockNumber'
                ? Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x7c20a24'])
                : Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => [[
                    'transactionHash' => self::HASH,
                    'data' => $this->usdtHex($amount),
                    'blockTimestamp' => '0x'.dechex($timestamp),
                    'removed' => false,
                ]]]);
        }]);
    }

    /** Whole-USDT amount as an 18-decimal uint256 hex — via a decimal string, since 50e18 overflows a PHP int. */
    protected function usdtHex(float $amount): string
    {
        $decimal = (string) (int) $amount.str_repeat('0', 18);
        $hex = '';

        while ($decimal !== '0' && $decimal !== '') {
            $remainder = 0;
            $quotient = '';
            foreach (str_split($decimal) as $digit) {
                $current = $remainder * 10 + (int) $digit;
                $quotient .= intdiv($current, 16);
                $remainder = $current % 16;
            }
            $hex = dechex($remainder).$hex;
            $decimal = ltrim($quotient, '0');
        }

        return '0x'.str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    protected function openDeposit(float $amount, ?User $user = null): WalletDeposit
    {
        $deposit = WalletDeposit::factory()->create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'network' => 'BEP20',
            'pay_to_address' => self::PAY_TO,
            'amount_crypto' => $amount,
            'amount_usd' => $amount,
            'status' => WalletDeposit::STATUS_AWAITING_PAYMENT,
            'tx_hash' => null,
            'expires_at' => now()->addMinutes(30),
        ]);
        $deposit->forceFill(['created_at' => now()->subMinutes(5)])->save();

        return $deposit;
    }

    #[Test]
    public function a_payment_sent_without_pasting_a_hash_is_attached_to_its_quote(): void
    {
        $deposit = $this->openDeposit(50);
        $this->fakeIncoming(50, now()->timestamp);

        $this->assertSame(1, app(Bep20AutoMatcher::class)->run());

        $deposit->refresh();
        $this->assertSame(WalletDeposit::STATUS_SUBMITTED, $deposit->status);
        $this->assertSame(self::HASH, $deposit->tx_hash);
    }

    #[Test]
    public function two_open_quotes_for_the_same_amount_are_left_alone(): void
    {
        $a = $this->openDeposit(50);
        $b = $this->openDeposit(50);
        $this->fakeIncoming(50, now()->timestamp);

        $this->assertSame(0, app(Bep20AutoMatcher::class)->run());
        $this->assertNull($a->fresh()->tx_hash);
        $this->assertNull($b->fresh()->tx_hash);
    }

    #[Test]
    public function a_transfer_older_than_the_quote_is_not_matched(): void
    {
        $deposit = $this->openDeposit(50);
        $this->fakeIncoming(50, now()->subHour()->timestamp);

        $this->assertSame(0, app(Bep20AutoMatcher::class)->run());
        $this->assertNull($deposit->fresh()->tx_hash);
    }

    #[Test]
    public function a_different_amount_is_not_matched(): void
    {
        $deposit = $this->openDeposit(50);
        $this->fakeIncoming(20, now()->timestamp);

        $this->assertSame(0, app(Bep20AutoMatcher::class)->run());
        $this->assertNull($deposit->fresh()->tx_hash);
    }

    #[Test]
    public function the_same_transaction_cannot_be_claimed_by_two_deposits(): void
    {
        $user = User::factory()->create();
        $first = $this->openDeposit(50, $user);
        $second = $this->openDeposit(50, $user);

        $this->actingAs($user)->post(route('wallet.deposit.tx', $first), ['tx_hash' => self::HASH]);

        // Same hash, pasted without 0x and in upper case — still the same payment.
        $this->actingAs($user)
            ->post(route('wallet.deposit.tx', $second), ['tx_hash' => strtoupper(substr(self::HASH, 2))])
            ->assertSessionHasErrors('tx_hash');

        $this->assertNull($second->fresh()->tx_hash);
    }

    #[Test]
    public function pasted_hashes_are_canonicalised_per_network(): void
    {
        $bare = substr(self::HASH, 2);

        $this->assertSame(self::HASH, TxHash::canonical($bare, 'BEP20'));
        $this->assertSame(self::HASH, TxHash::canonical('https://bscscan.com/tx/'.strtoupper(self::HASH), 'BEP20'));
        $this->assertSame($bare, TxHash::canonical(self::HASH, 'TRC20'));
        $this->assertSame($bare, TxHash::canonical(' '.$bare.' ', 'LTC'));
    }
}
