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

    protected function setUp(): void
    {
        parent::setUp();
        config(['crypto.bscscan_api_key' => 'test-key']);
    }

    protected function makePayment(float $amount = 10.00): CryptoPayment
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => $amount]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => $amount]);

        return CryptoPayment::factory()->create([
            'order_id' => $order->id,
            'network' => 'BEP20',
            'pay_to_address' => '0xReceivingAddress0000000000000000000001',
            'amount_crypto' => $amount,
            'tx_hash' => '0xabc123txhash',
            'status' => CryptoPayment::STATUS_SUBMITTED,
        ]);
    }

    #[Test]
    public function it_confirms_a_matching_transfer(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.bscscan.com/*' => Http::response([
                'status' => '1', 'message' => 'OK',
                'result' => [[
                    'hash' => '0xabc123txhash',
                    'to' => '0xReceivingAddress0000000000000000000001',
                    'value' => '10000000000000000000', // 10 USDT at 18 decimals
                    'tokenDecimal' => '18',
                ]],
            ]),
        ]);

        $this->assertTrue((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_matches_the_hash_and_address_case_insensitively(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.bscscan.com/*' => Http::response([
                'status' => '1', 'message' => 'OK',
                'result' => [[
                    'hash' => '0xABC123TXHASH',
                    'to' => '0XRECEIVINGADDRESS0000000000000000000001',
                    'value' => '10000000000000000000',
                    'tokenDecimal' => '18',
                ]],
            ]),
        ]);

        $this->assertTrue((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_tx_hash_is_not_found(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake(['api.bscscan.com/*' => Http::response(['status' => '0', 'message' => 'No transactions found', 'result' => []])]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_the_amount_is_underpaid_beyond_tolerance(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.bscscan.com/*' => Http::response([
                'status' => '1', 'message' => 'OK',
                'result' => [[
                    'hash' => '0xabc123txhash',
                    'to' => '0xReceivingAddress0000000000000000000001',
                    'value' => '5000000000000000000', // only 5 USDT
                    'tokenDecimal' => '18',
                ]],
            ]),
        ]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_returns_false_when_no_api_key_is_configured(): void
    {
        config(['crypto.bscscan_api_key' => null]);
        $payment = $this->makePayment(10.00);

        Http::fake();

        $this->assertFalse((new BscUsdtVerifier)->verify($payment));
        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_false_without_a_tx_hash(): void
    {
        $payment = $this->makePayment(10.00);
        $payment->update(['tx_hash' => null]);

        $this->assertFalse((new BscUsdtVerifier)->verify($payment->fresh()));
    }
}
