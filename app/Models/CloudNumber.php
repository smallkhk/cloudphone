<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A VMOS "Cloud Number" — a rented virtual phone number (30/90/365-day
 * plans, auto-renewing) that can be bound to one of the customer's cloud
 * phones to receive SMS on it. See CloudNumberProvisioner for the purchase
 * flow and CLAUDE.md for why it's a different shape from Sku::TYPE_PHONE_NUMBER.
 */
#[Fillable([
    'order_id', 'user_id', 'sku_id', 'vmos_number_id', 'number', 'country_code',
    'purchase_client_token', 'purchase_status', 'purchase_error',
    'auto_renew', 'row_version', 'vmos_status', 'expire_time',
    'bound_pad_code', 'bind_state', 'bind_request_id',
    'raw_payload', 'delivered_at',
])]
class CloudNumber extends Model
{
    use HasFactory;

    public const PURCHASE_PROCESSING = 'PROCESSING';

    public const PURCHASE_COMPLETED = 'COMPLETED';

    public const PURCHASE_FAILED = 'FAILED';

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'auto_renew' => 'boolean',
            'expire_time' => 'datetime',
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

    public function isBound(): bool
    {
        return filled($this->bound_pad_code) && $this->bind_state === 'SUCCESS';
    }
}
