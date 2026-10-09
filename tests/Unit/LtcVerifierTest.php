<?php

namespace Tests\Unit;

use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Payments\LtcVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LtcVerifierTest extends TestCase
{
    use RefreshDatabase;

    protected const PAY_TO = 'LReceivingAddressXXXXXXXXXXXXXXXX';

    protected function makePayment(float $amount = 2.0): CryptoPayment
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => $amount]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => $amount]);

        return CryptoPayment::factory()->create([
            'order_id' => $order->id,
            'network' => 'LTC',
            'currency' => 'LTC',
            'pay_to_address' => self::PAY_TO,
            'amount_crypto' => $amount,
            'tx_hash' => 'ltctxhash123',
            'status' => CryptoPayment::STATUS_SUBMITTED,
        ]);
    }

    protected function fakeTx(array $outputs, int $confirmations = 2, bool $doubleSpend = false): void
    {
        Http::fake(['*blockcypher.com*' => Http::response([
            'hash' => 'ltctxhash123',
            'confirmations' => $confirmations,
            'double_spend' => $doubleSpend,
            'outputs' => $outputs,
        ])]);
    }

    #[Test]
    public function it_confirms_a_matching_transfer(): void
    {
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 200_000_000, 'addresses' => [self::PAY_TO]]]); // 2 LTC

        $this->assertTrue((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_sums_multiple_outputs_to_the_same_address(): void
    {
        $payment = $this->makePayment(2.0);

        $this->fakeTx([
            ['value' => 150_000_000, 'addresses' => [self::PAY_TO]],
            ['value' => 50_000_000, 'addresses' => [self::PAY_TO]],
        ]);

        $this->assertTrue((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_an_unconfirmed_transaction(): void
    {
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 200_000_000, 'addresses' => [self::PAY_TO]]], confirmations: 0);

        $this->assertFalse((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_a_double_spend(): void
    {
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 200_000_000, 'addresses' => [self::PAY_TO]]], doubleSpend: true);

        $this->assertFalse((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_a_payment_to_the_wrong_address(): void
    {
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 200_000_000, 'addresses' => ['LSomeoneElsesAddressXXXXXXXXXXXXX']]]);

        $this->assertFalse((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_rejects_when_underpaid_beyond_tolerance(): void
    {
        config(['crypto.ltc_amount_tolerance_percent' => 3]);
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 100_000_000, 'addresses' => [self::PAY_TO]]]); // only 1 LTC

        $this->assertFalse((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_accepts_a_slight_underpayment_within_tolerance(): void
    {
        config(['crypto.ltc_amount_tolerance_percent' => 3]);
        $payment = $this->makePayment(2.0);

        $this->fakeTx([['value' => 196_000_000, 'addresses' => [self::PAY_TO]]]); // 1.96 LTC, 2% short

        $this->assertTrue((new LtcVerifier)->verify($payment));
    }

    #[Test]
    public function it_returns_false_without_a_tx_hash(): void
    {
        $payment = $this->makePayment(2.0);
        $payment->update(['tx_hash' => null]);

        $this->assertFalse((new LtcVerifier)->verify($payment->fresh()));
    }
}
