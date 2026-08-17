<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'reference', 'user_id', 'sku_id', 'quantity', 'unit_price', 'total_price',
    'auto_renew', 'country_code', 'status', 'vmos_order_id', 'vmos_equipment_id',
    'error_message', 'paid_at', 'provisioned_at',
])]
class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= 'ORD-'.strtoupper(Str::random(10));
        });
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'auto_renew' => 'boolean',
            'paid_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CryptoPayment::class);
    }

    public function latestPayment(): ?CryptoPayment
    {
        return $this->payments()->latest('id')->first();
    }

    public function cloudInstances(): HasMany
    {
        return $this->hasMany(CloudInstance::class);
    }

    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }
}
