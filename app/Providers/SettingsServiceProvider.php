<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Overlays admin-managed settings on top of the .env-derived config, so values
 * edited in the admin panel take effect without a redeploy. Anything left blank
 * in the admin panel falls through to the .env value.
 */
class SettingsServiceProvider extends ServiceProvider
{
    /** setting key => config key */
    protected const CONFIG_MAP = [
        'site_name' => 'app.name',
        'site_url' => 'app.url',

        'vmos_base_url' => 'vmos.base_url',
        'vmos_access_key' => 'vmos.access_key',
        'vmos_secret_key' => 'vmos.secret_key',
        'vmos_webhook_token' => 'vmos.webhook_token',

        'crypto_usdt_trc20_address' => 'crypto.usdt_trc20_address',
        'crypto_payment_window_minutes' => 'crypto.payment_window_minutes',
        'crypto_amount_tolerance_percent' => 'crypto.amount_tolerance_percent',
        'trongrid_api_key' => 'crypto.trongrid_api_key',

        'assistant_enabled' => 'assistant.enabled',
        'assistant_provider' => 'assistant.provider',
        'assistant_openai_preset' => 'assistant.openai_preset',
        'assistant_openai_base_url' => 'assistant.openai_base_url',
        'assistant_openai_api_key' => 'assistant.openai_api_key',
        'assistant_openai_model' => 'assistant.openai_model',
        'anthropic_api_key' => 'assistant.api_key',
        'assistant_model' => 'assistant.model',
        'assistant_max_tokens' => 'assistant.max_tokens',
        'assistant_greeting' => 'assistant.greeting',
        'assistant_rate_limit_per_hour' => 'assistant.rate_limit_per_hour',
        'assistant_knowledge' => 'assistant.knowledge',

        'mail_mailer' => 'mail.default',
        'mail_host' => 'mail.mailers.smtp.host',
        'mail_port' => 'mail.mailers.smtp.port',
        'mail_username' => 'mail.mailers.smtp.username',
        'mail_password' => 'mail.mailers.smtp.password',
        'mail_encryption' => 'mail.mailers.smtp.scheme',
        'mail_from_address' => 'mail.from.address',
        'mail_from_name' => 'mail.from.name',
    ];

    public function boot(): void
    {
        self::apply();
    }

    /**
     * Copies stored settings over the .env-derived config.
     *
     * Also called after settings are saved, so changes take effect within the
     * same request — otherwise "save credentials" followed by "test connection"
     * would still be testing the old values.
     */
    public static function apply(): void
    {
        // Guard against running before migrations (fresh install, CI, artisan
        // migrate itself) — settings simply don't exist yet at that point.
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::all_settings();
        } catch (Throwable) {
            return;
        }

        foreach (self::CONFIG_MAP as $settingKey => $configKey) {
            $value = $settings[$settingKey] ?? null;

            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }
    }
}
