<?php

namespace Tests\Unit;

use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Payments\TronUsdtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TronUsdtVerifierTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(float $amount = 10.00): CryptoPayment
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => $amount]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => $amount]);

        return CryptoPayment::factory()->create([
            'order_id' => $order->id,
            'pay_to_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
            'amount_crypto' => $amount,
            'tx_hash' => 'abc123txhash',
            'status' => CryptoPayment::STATUS_SUBMITTED,
        ]);
    }

    #[Test]
    public function it_confirms_a_matching_transfer(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'transaction_id' => 'abc123txhash',
                        'to' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
                        'value' => '10000000', // 10 USDT at 6 decimals
                        'token_info' => ['symbol' => 'USDT', 'decimals' => 6],
                    ],
                ],
            ]),
        ]);

        $this->assertTrue((new TronUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_tx_hash_is_not_found(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake(['api.trongrid.io/*' => Http::response(['success' => true, 'data' => []])]);

        $this->assertFalse((new TronUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_the_amount_is_underpaid_beyond_tolerance(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'data' => [[
                    'transaction_id' => 'abc123txhash',
                    'to' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
                    'value' => '5000000', // only 5 USDT sent
                    'token_info' => ['symbol' => 'USDT', 'decimals' => 6],
                ]],
            ]),
        ]);

        $this->assertFalse((new TronUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_accepts_a_tiny_underpayment_within_tolerance(): void
    {
        config(['crypto.amount_tolerance_percent' => 1.0]);
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'data' => [[
                    'transaction_id' => 'abc123txhash',
                    'to' => 'TReceivingAddressXXXXXXXXXXXXXXXXX',
                    'value' => '9950000', // 9.95 USDT, within 1% tolerance of 10
                    'token_info' => ['symbol' => 'USDT', 'decimals' => 6],
                ]],
            ]),
        ]);

        $this->assertTrue((new TronUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_a_transfer_to_a_different_address(): void
    {
        $payment = $this->makePayment(10.00);

        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'data' => [[
                    'transaction_id' => 'abc123txhash',
                    'to' => 'TSomeoneElsesAddressXXXXXXXXXXXXXXX',
                    'value' => '10000000',
                    'token_info' => ['symbol' => 'USDT', 'decimals' => 6],
                ]],
            ]),
        ]);

        $this->assertFalse((new TronUsdtVerifier)->verify($payment));
    }

    #[Test]
    public function it_returns_false_without_a_tx_hash(): void
    {
        $payment = $this->makePayment(10.00);
        $payment->update(['tx_hash' => null]);

        $this->assertFalse((new TronUsdtVerifier)->verify($payment->fresh()));
    }
}
