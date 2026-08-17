<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A VMOS temporary phone number ("Captcha Service", SMS half) with the latest
 * SMS verification code VMOS has reported for it.
 */
#[Fillable([
    'order_id', 'user_id', 'sku_id', 'vmos_order_id', 'phone_number', 'country_code',
    'latest_code', 'code_fetched_at', 'raw_payload', 'status', 'delivered_at',
])]
class PhoneNumber extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'code_fetched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
