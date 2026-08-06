<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'vmos_config_id', 'vmos_good_id', 'android_version',
    'name', 'config_model', 'duration_label', 'duration_minutes',
    'vmos_cost_price', 'price', 'default_country_code',
    'sell_out', 'active', 'sort_order', 'raw_payload', 'synced_at',
])]
class Sku extends Model
{
    use HasFactory;

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
}
