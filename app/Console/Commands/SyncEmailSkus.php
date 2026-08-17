<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;
use Throwable;

class SyncEmailSkus extends Command
{
    protected $signature = 'vmos:sync-email-skus';

    protected $description = 'Pull the VMOS email-account catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            $this->error('VMOS credentials are not set. Add them under Admin → Settings → VMOS.');

            return self::FAILURE;
        }

        try {
            $response = $vmos->emailTypeList();
        } catch (Throwable $e) {
            $this->error('VMOS request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $types = $response['data'] ?? [];

        if (empty($types)) {
            $this->warn('VMOS returned no email account types. Check Admin → Diagnostics for the raw response.');

            return self::SUCCESS;
        }

        $seen = 0;
        $created = 0;

        foreach ($types as $type) {
            $typeId = $type['id'] ?? $type['emailTypeId'] ?? null;

            if ($typeId === null) {
                continue;
            }

            $cost = $this->normalisePrice($type['price'] ?? $type['currentPrice'] ?? 0);

            $sku = Sku::firstOrNew([
                'type' => Sku::TYPE_EMAIL_ACCOUNT,
                'vmos_good_id' => $typeId,
                'android_version' => '',
            ]);

            $isNew = ! $sku->exists;

            $sku->fill([
                'vmos_config_id' => 0,
                'name' => $type['name'] ?? $type['serviceName'] ?? 'Email account',
                'config_model' => $type['serviceName'] ?? null,
                'duration_label' => 'One-time',
                'duration_minutes' => 0,
                'vmos_cost_price' => $cost,
                'sell_out' => (bool) ($type['sellOutFlag'] ?? ($type['stock'] ?? 1) <= 0),
                'raw_payload' => $type,
                'synced_at' => now(),
            ]);

            if ($isNew) {
                $markup = 1 + ((float) Setting::get('default_markup_percent', 30) / 100);
                $sku->price = round($cost * $markup, 2);
                $sku->active = true;
                $created++;
            }

            $sku->save();

            $seen++;
        }

        $this->info("Synced {$seen} email account type(s) — {$created} new.");

        return self::SUCCESS;
    }

    /** Same minor-unit convention as SyncCloudSkus — VMOS quotes in cents by default. */
    protected function normalisePrice(mixed $raw): float
    {
        $value = (float) $raw;

        return Setting::get('vmos_price_unit', 'cents') === 'cents'
            ? round($value / 100, 2)
            : round($value, 2);
    }
}
