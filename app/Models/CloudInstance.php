<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'order_id', 'sku_id', 'pad_code', 'equipment_id', 'nickname',
    'pad_status', 'online', 'screenshot_url', 'auto_renew', 'expires_at', 'last_synced_at',
])]
class CloudInstance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'online' => 'boolean',
            'auto_renew' => 'boolean',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
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

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InstanceTask::class);
    }
}
