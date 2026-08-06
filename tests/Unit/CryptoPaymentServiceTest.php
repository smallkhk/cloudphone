<?php

namespace Tests\Unit;

use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Payments\CryptoPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CryptoPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_trc20_quote_matching_the_order_total(): void
    {
        config(['crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX']);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 12.34]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => 12.34]);

        $payment = (new CryptoPaymentService)->createForOrder($order);

        $this->assertSame('TReceivingAddressXXXXXXXXXXXXXXXXX', $payment->pay_to_address);
        $this->assertEquals(12.34, $payment->amount_crypto);
        $this->assertSame(CryptoPayment::STATUS_AWAITING_PAYMENT, $payment->status);
        $this->assertTrue($payment->expires_at->isFuture());
    }

    #[Test]
    public function it_throws_when_no_receiving_address_is_configured(): void
    {
        config(['crypto.usdt_trc20_address' => null]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id]);

        $this->expectException(\RuntimeException::class);

        (new CryptoPaymentService)->createForOrder($order);
    }

    #[Test]
    public function submitting_a_tx_hash_moves_status_to_submitted(): void
    {
        config(['crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX']);

        $user = User::factory()->create();
        $sku = Sku::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id]);
        $payment = (new CryptoPaymentService)->createForOrder($order);

        $updated = (new CryptoPaymentService)->submitTransactionHash($payment, 'deadbeef');

        $this->assertSame(CryptoPayment::STATUS_SUBMITTED, $updated->status);
        $this->assertSame('deadbeef', $updated->tx_hash);
        $this->assertNotNull($updated->submitted_at);
    }
}
