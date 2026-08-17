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

    // A temporary phone number for SMS verification codes — VMOS's "Captcha
    // Service" (SMS half). Same unused-column convention as TYPE_EMAIL_ACCOUNT.
    // See CLAUDE.md: the underlying VMOS endpoints are unconfirmed.
    public const TYPE_PHONE_NUMBER = 'phone_number';

    public const TIER_STANDARD = 'standard';

    public const TIER_HIGH_END = 'high_end';

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

    public function scopePhoneNumbers($query)
    {
        return $query->where('type', self::TYPE_PHONE_NUMBER);
    }

    public function isEmailAccount(): bool
    {
        return $this->type === self::TYPE_EMAIL_ACCOUNT;
    }

    public function isPhoneNumber(): bool
    {
        return $this->type === self::TYPE_PHONE_NUMBER;
    }

    /**
     * VMOS's getCloudGoodList doesn't expose a device-tier field, so this
     * mirrors the owner's own rule from the VMOS console: device names
     * starting "V0…" (V03/V04/V06/V08…) are the virtual "Standard" line;
     * every other device name (Samsung Galaxy A53, Pixel 8, …) is a real
     * physical device — VMOS's "High-end Real Machine" line. The V0x code is
     * the device's `name` (configName — what's shown as the card title), NOT
     * `config_model`, which holds something else entirely. If VMOS adds a
     * model outside that naming (e.g. a V1x), this rule needs revisiting.
     */
    public function deviceTier(): string
    {
        return self::isStandardModel($this->name) ? self::TIER_STANDARD : self::TIER_HIGH_END;
    }

    public static function isStandardModel(?string $name): bool
    {
        return $name !== null && str_starts_with(strtoupper(trim($name)), 'V0');
    }

    public function scopeStandardTier($query)
    {
        return $query->whereRaw('UPPER(name) LIKE ?', ['V0%']);
    }

    public function scopeHighEndTier($query)
    {
        return $query->whereRaw('UPPER(name) NOT LIKE ?', ['V0%']);
    }
}
