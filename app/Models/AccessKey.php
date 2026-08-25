<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An access code the desktop app requires before it will load the site.
 * The website itself stays unlocked for normal browser visitors — this is
 * purely a gate the (not-yet-built) desktop app checks against on launch, so
 * only people the admin has actually handed a code to can open it there.
 */
class AccessKey extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'label', 'is_active', 'expires_at', 'created_by', 'used_count', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Unambiguous charset (no 0/O/1/I/L) grouped for easy reading and typing. */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $raw = collect(range(1, 16))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');

            $code = implode('-', str_split($raw, 4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isValid(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function recordUse(): void
    {
        $this->update([
            'used_count' => $this->used_count + 1,
            'last_used_at' => now(),
        ]);
    }
}
