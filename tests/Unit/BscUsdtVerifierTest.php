<?php

namespace Tests\Unit;

use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Payments\BscUsdtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BscUsdtVerifierTest extends TestCase
{
    use RefreshDatabase;

    protected const PAY_TO = '0x1234567890123456789012345678901234567890';

    protected const CONTRACT = '0x55d398326f99059fF775485246999027B3197955';

    protected const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    protected function makePayment(float $amount = 10.00): CryptoPayment
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => $amount]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => $amount]);

        return CryptoPayment::factory()->create([
            'order_id' => $order->id,
            'network' => 'BEP20',
            'pay_to_address' => self::PAY_TO,
            'amount_crypto' => $amount,
            'tx_hash' => '0xabc123txhash',
            'status' => CryptoPayment::STATUS_SUBMITTED,
        ]);
    }

    /** Builds a Transfer(address,address,uint256) log entry the way a real eth_getTransactionReceipt response shapes it. */
    protected function transferLog(string $to, string $valueHex, ?string $contract = null): array
    {
        return [
            'address' => $contract ?? self::CONTRACT,
            'topics' => [
                self::TRANSFER_TOPIC,
                '0x000000000000000000000000'.str_repeat('1', 40), // "from" — irrelevant to verification
                '0x'.str_pad(ltrim($to, '0x'), 64, '0', STR_PAD_LEFT),
            ],
            'data' => $valueHex,
        ];
    }

    protected function fakeReceipt(array $logs, string $status = '0x1'): void
    {
        Http::fake(['*bsc-dataseed.binance.org*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'result' => ['status' => $status, 'logs' => $logs],
        ])]);
    }

    #[Test]
    public function it_confirms_a_matching_transfer(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([$this->transferLog(self::PAY_TO, '0x8ac7230489e80000')]); // 10 USDT at 18 decimals

        $this->assertTrue((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_matches_the_address_case_insensitively(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([$this->transferLog(strtoupper(self::PAY_TO), '0x8ac7230489e80000')]);

        $this->assertTrue((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_a_reverted_transaction(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([$this->transferLog(self::PAY_TO, '0x8ac7230489e80000')], status: '0x0');

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_a_transfer_from_the_wrong_contract(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([$this->transferLog(self::PAY_TO, '0x8ac7230489e80000', contract: '0x0000000000000000000000000000000000dead')]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_no_log_is_found(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_the_amount_is_underpaid_beyond_tolerance(): void
    {
        $payment = $this->makePayment(10.00);

        $this->fakeReceipt([$this->transferLog(self::PAY_TO, '0x4563918244f40000')]); // only 5 USDT

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_returns_false_without_a_tx_hash(): void
    {
        $payment = $this->makePayment(10.00);
        $payment->update(['tx_hash' => null]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment->fresh()));
    }
}
