<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A VMOS static residential proxy bought standalone (Sku::TYPE_PROXY), not
 * tied to a device order — unlike the checkout proxy add-on (Order.proxy_*),
 * a customer can attach/detach this to whichever of their own devices they
 * like, whenever they like. See StandaloneProxyProvisioner for the purchase
 * flow.
 */
#[Fillable([
    'order_id', 'user_id', 'sku_id', 'source', 'label', 'vmos_proxy_id', 'host', 'port', 'account', 'password',
    'proxy_name', 'proxy_type', 'remarks', 'country_code',
    'purchase_client_token', 'purchase_status', 'purchase_error',
    'attached_pad_code', 'raw_payload', 'delivered_at',
])]
class CustomerProxy extends Model
{
    use HasFactory;

    public const PURCHASE_PROCESSING = 'PROCESSING';

    public const PURCHASE_COMPLETED = 'COMPLETED';

    public const PURCHASE_FAILED = 'FAILED';

    // Bought through us — async VMOS purchase/poll/match, see StandaloneProxyProvisioner.
    public const SOURCE_VMOS = 'vmos';

    // The customer's own proxy, added directly — no order, no VMOS charge,
    // delivered immediately. Same idea as the checkout add-on's "Use my own
    // proxy" mode.
    public const SOURCE_CUSTOM = 'custom';

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
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

    public function isAttached(): bool
    {
        return filled($this->attached_pad_code);
    }

    public function isDelivered(): bool
    {
        if ($this->source === self::SOURCE_CUSTOM) {
            return filled($this->host) && filled($this->port);
        }

        return $this->purchase_status === self::PURCHASE_COMPLETED && filled($this->vmos_proxy_id);
    }

    public function isCustom(): bool
    {
        return $this->source === self::SOURCE_CUSTOM;
    }

    /** VMOS proxyIds already claimed by a completed standalone purchase — never re-match these to a different order. */
    public static function claimedVmosProxyIds(): array
    {
        return static::whereNotNull('vmos_proxy_id')->pluck('vmos_proxy_id')->all();
    }
}
