<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function crediting_increases_the_balance_and_records_a_ledger_entry(): void
    {
        $user = User::factory()->create(['balance' => 10]);

        $tx = app(WalletService::class)->credit($user, 25, WalletTransaction::TYPE_DEPOSIT, 'Top-up');

        $this->assertEquals(35, $user->fresh()->balance);
        $this->assertEquals(25, $tx->amount);
        $this->assertEquals(35, $tx->balance_after);
        $this->assertSame(WalletTransaction::TYPE_DEPOSIT, $tx->type);
    }

    #[Test]
    public function debiting_decreases_the_balance_and_records_a_negative_ledger_entry(): void
    {
        $user = User::factory()->create(['balance' => 50]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $tx = app(WalletService::class)->debit($user, 15, WalletTransaction::TYPE_PURCHASE, 'Order paid', $order);

        $this->assertEquals(35, $user->fresh()->balance);
        $this->assertEquals(-15, $tx->amount);
        $this->assertEquals(35, $tx->balance_after);
        $this->assertSame($order->id, $tx->order_id);
    }

    #[Test]
    public function debiting_more_than_the_balance_throws_and_changes_nothing(): void
    {
        $user = User::factory()->create(['balance' => 10]);

        $this->expectException(RuntimeException::class);

        try {
            app(WalletService::class)->debit($user, 20, WalletTransaction::TYPE_PURCHASE, 'Order paid');
        } finally {
            $this->assertEquals(10, $user->fresh()->balance);
            $this->assertSame(0, WalletTransaction::count());
        }
    }

    #[Test]
    public function crediting_a_non_positive_amount_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 10]);

        $this->expectException(RuntimeException::class);

        app(WalletService::class)->credit($user, 0, WalletTransaction::TYPE_ADJUSTMENT, 'nope');
    }
}
