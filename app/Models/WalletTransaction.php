<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger of every balance change — deposits, purchases paid from
 * balance, refunds, and manual admin adjustments. users.balance is a cached
 * running total for fast reads; this table is the source of truth, kept via
 * WalletService rather than direct model writes.
 */
#[Fillable(['user_id', 'type', 'amount', 'balance_after', 'description', 'order_id', 'wallet_deposit_id'])]
class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function walletDeposit(): BelongsTo
    {
        return $this->belongsTo(WalletDeposit::class);
    }
}
