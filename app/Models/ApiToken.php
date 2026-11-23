<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A sign-in token for the iPhone app — see AuthenticateApiToken. */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Creates a token and returns the plaintext once — it's never stored or shown again. */
    public static function issue(User $user, ?string $name = null): string
    {
        $plain = Str::random(64);

        static::create([
            'user_id' => $user->id,
            'name' => $name ? Str::limit($name, 250, '') : null,
            'token_hash' => hash('sha256', $plain),
        ]);

        return $plain;
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::where('token_hash', hash('sha256', $plain))->first();
    }
}
