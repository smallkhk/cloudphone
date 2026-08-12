<?php

namespace App\Models;

use App\Providers\SettingsServiceProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Key/value application settings editable from the admin panel, so the site
 * owner can change branding, mail, payment and VMOS credentials without
 * touching .env on the server.
 *
 * Database credentials deliberately live in .env only — they're needed to reach
 * this table in the first place.
 */
#[Fillable(['key', 'value', 'is_secret'])]
class Setting extends Model
{
    protected const CACHE_KEY = 'settings.all';

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    /**
     * All settings as a flat key => value map (decrypting secrets).
     *
     * @return array<string, string|null>
     */
    public static function all_settings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get()->mapWithKeys(function (Setting $setting) {
                return [$setting->key => $setting->decryptedValue()];
            })->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all_settings()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, mixed $value, bool $isSecret = false): void
    {
        // Blank input on a secret field means "leave the stored value alone",
        // since the UI never renders existing secrets back to the browser.
        if ($isSecret && ($value === null || $value === '')) {
            return;
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => ($isSecret && $value !== null && $value !== '') ? Crypt::encryptString((string) $value) : $value,
                'is_secret' => $isSecret,
            ]
        );

        self::flush();
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        // The assistant's system prompt is built from these values, so it has
        // to be rebuilt when any of them change.
        Cache::forget('assistant.site_prompt');

        // Re-apply over config so changes are visible immediately, not just on
        // the next request.
        SettingsServiceProvider::apply();
    }

    /** True when a secret has a stored value, without revealing it. */
    public static function hasValue(string $key): bool
    {
        return filled(self::get($key));
    }

    protected function decryptedValue(): ?string
    {
        if (! $this->is_secret || $this->value === null || $this->value === '') {
            return $this->value;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (Throwable) {
            // APP_KEY changed (or the row was written by hand) — treat as unset
            // rather than taking the whole app down on a decrypt failure.
            return null;
        }
    }
}
