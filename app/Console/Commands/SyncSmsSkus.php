<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls VMOS's SMS/phone-number catalogue. Unlike vmos:sync-email-skus, the
 * underlying endpoint (getSmsTypeList) is unconfirmed — see CLAUDE.md and
 * VmosCloudPhoneService — so a failure here is expected until that's checked
 * against a live account via Admin → Diagnostics.
 */
class SyncSmsSkus extends Command
{
    protected $signature = 'vmos:sync-sms-skus';

    protected $description = 'Pull the VMOS phone-number catalogue and upsert it into the local skus table';

    public function handle(VmosCloudPhoneService $vmos): int
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            $this->error('VMOS credentials are not set. Add them under Admin → Settings → VMOS.');

            return self::FAILURE;
        }

        try {
            $response = $vmos->smsTypeList();
        } catch (Throwable $e) {
            $this->error('VMOS request failed: '.$e->getMessage()
                .' — this endpoint is unconfirmed (see CLAUDE.md); check Admin → Diagnostics.');

            return self::FAILURE;
        }

        $types = $response['data'] ?? [];

        if (empty($types)) {
            $this->warn('VMOS returned no phone number types. Check Admin → Diagnostics for the raw response.');

            return self::SUCCESS;
        }

        $seen = 0;
        $created = 0;

        foreach ($types as $type) {
            $typeId = $type['id'] ?? $type['smsTypeId'] ?? null;

            if ($typeId === null) {
                continue;
            }

            $cost = $this->normalisePrice($type['price'] ?? $type['currentPrice'] ?? 0);

            $sku = Sku::firstOrNew([
                'type' => Sku::TYPE_PHONE_NUMBER,
                'vmos_good_id' => $typeId,
                'android_version' => '',
            ]);

            $isNew = ! $sku->exists;

            $sku->fill([
                'vmos_config_id' => 0,
                'name' => $type['name'] ?? $type['serviceName'] ?? 'Phone number',
                'config_model' => $type['serviceName'] ?? null,
                'default_country_code' => $type['countryCode'] ?? $type['country'] ?? null,
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
                // New phone-number SKUs start hidden — this endpoint is unconfirmed,
                // so nothing goes live for customers until an admin checks a real
                // response in Diagnostics and flips it on deliberately.
                $sku->active = false;
                $created++;
            }

            $sku->save();

            $seen++;
        }

        $this->info("Synced {$seen} phone number type(s) — {$created} new (hidden by default; enable under Plans & pricing → Phone numbers once verified).");

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
