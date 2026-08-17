<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'vmos_config_id', 'vmos_good_id', 'android_version',
    'name', 'config_model', 'duration_label', 'duration_minutes',
    'vmos_cost_price', 'price', 'default_country_code',
    'sell_out', 'active', 'sort_order', 'raw_payload', 'synced_at',
])]
class Sku extends Model
{
    use HasFactory;

    public const TYPE_CLOUD_PHONE = 'cloud_phone';

    // A pre-made email account with verification-code retrieval (VMOS's
    // …/padApi/*Email* endpoints). Reuses this table's vmos_good_id column to
    // store the VMOS email "type" id, and vmos_config_id/android_version/
    // duration_minutes are unused (0 / '') for this type — see SyncEmailSkus.
    public const TYPE_EMAIL_ACCOUNT = 'email_account';

    protected function casts(): array
    {
        return [
            'vmos_cost_price' => 'decimal:2',
            'price' => 'decimal:2',
            'sell_out' => 'boolean',
            'active' => 'boolean',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('active', true)->where('sell_out', false);
    }

    public function scopeCloudPhones($query)
    {
        return $query->where('type', self::TYPE_CLOUD_PHONE);
    }

    public function scopeEmailAccounts($query)
    {
        return $query->where('type', self::TYPE_EMAIL_ACCOUNT);
    }

    public function isEmailAccount(): bool
    {
        return $this->type === self::TYPE_EMAIL_ACCOUNT;
    }
}
