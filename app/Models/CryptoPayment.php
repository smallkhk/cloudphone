<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'currency', 'network', 'pay_to_address', 'amount_crypto', 'amount_usd',
    'tx_hash', 'status', 'confirmations', 'submitted_at', 'confirmed_at', 'expires_at',
])]
class CryptoPayment extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'amount_crypto' => 'decimal:8',
            'amount_usd' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYMENT && $this->expires_at->isPast();
    }
}
