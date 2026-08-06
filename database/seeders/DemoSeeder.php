<?php

namespace Database\Seeders;

use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fake catalogue + login so the storefront isn't empty when previewed
 * (e.g. in a Codespace) before real VMOS credentials are configured.
 * Not run in production migrations — invoked explicitly via
 * `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Sku::query()->updateOrCreate(
            ['vmos_good_id' => 101, 'android_version' => '13'],
            [
                'vmos_config_id' => 1,
                'name' => '[DEMO] Samsung Galaxy A53',
                'config_model' => 'Samsung',
                'duration_label' => '1 day',
                'duration_minutes' => 1440,
                'vmos_cost_price' => 1.00,
                'price' => 1.99,
                'active' => true,
                'sell_out' => false,
                'sort_order' => 1,
            ]
        );

        Sku::query()->updateOrCreate(
            ['vmos_good_id' => 102, 'android_version' => '13'],
            [
                'vmos_config_id' => 2,
                'name' => '[DEMO] Google Pixel 7 Pro',
                'config_model' => 'Google',
                'duration_label' => '1 month',
                'duration_minutes' => 43200,
                'vmos_cost_price' => 9.99,
                'price' => 14.99,
                'active' => true,
                'sell_out' => false,
                'sort_order' => 2,
            ]
        );

        Sku::query()->updateOrCreate(
            ['vmos_good_id' => 103, 'android_version' => '14'],
            [
                'vmos_config_id' => 3,
                'name' => '[DEMO] OnePlus Ace 2',
                'config_model' => 'OnePlus',
                'duration_label' => '3 months',
                'duration_minutes' => 129600,
                'vmos_cost_price' => 24.99,
                'price' => 34.99,
                'active' => true,
                'sell_out' => false,
                'sort_order' => 3,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => bcrypt('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
