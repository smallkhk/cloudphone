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
    'allocated_at', 'allocated_by', 'allocation_note',
])]
class CloudInstance extends Model
{
    use HasFactory;

    /** VMOS cvmStatus codes worth naming in the UI; anything else shows as "Status <n>". */
    public const STATUS_LABELS = [
        99 => 'Loading',
        100 => 'Normal',
        101 => 'Screenshotting',
        102 => 'Rebooting',
        103 => 'Resetting',
        104 => 'Reboot failed',
        105 => 'Reset failed',
        106 => 'Maintenance',
        107 => 'Upgrading image',
        119 => 'Initializing',
        121 => 'Task running',
        201 => 'Backing up',
        202 => 'Restoring',
    ];

    protected function casts(): array
    {
        return [
            'online' => 'boolean',
            'auto_renew' => 'boolean',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'allocated_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        if ($this->pad_status === null) {
            return $this->pad_code ? 'Unknown' : 'Provisioning';
        }

        return self::STATUS_LABELS[$this->pad_status] ?? "Status {$this->pad_status}";
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeUnallocated($query)
    {
        return $query->whereNull('user_id');
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

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
