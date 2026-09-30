<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncCloudNumberSkusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('vmos_access_key', 'ak', true);
        Setting::set('vmos_secret_key', 'sk', true);
    }

    #[Test]
    public function it_creates_a_sku_per_country_and_plan(): void
    {
        Http::fake(['*/cloudNumber/skus' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            ['countryCode' => 'US', 'status' => 'NO_STOCK', 'skus' => []],
            ['countryCode' => 'GB', 'status' => 'AVAILABLE', 'skus' => [
                ['planId' => 2, 'durationDays' => 30, 'chargeCents' => 998, 'chargeUsd' => 9.98, 'dailyCents' => 33],
                ['planId' => 4, 'durationDays' => 365, 'chargeCents' => 6398, 'chargeUsd' => 63.98, 'dailyCents' => 18],
            ]],
        ]])]);

        $this->artisan('vmos:sync-cloud-number-skus')->assertExitCode(0);

        $this->assertSame(2, Sku::cloudNumbers()->count());

        $sku = Sku::cloudNumbers()->where('vmos_good_id', 2)->first();
        $this->assertSame('GB', $sku->default_country_code);
        $this->assertSame('30 days', $sku->duration_label);
        $this->assertSame(30 * 1440, $sku->duration_minutes);
        $this->assertEquals(9.98, $sku->vmos_cost_price);
        $this->assertFalse($sku->sell_out);
        $this->assertTrue($sku->active);
    }

    #[Test]
    public function the_same_plan_id_in_two_countries_does_not_collide(): void
    {
        // Same planId (2) reused across two different countries — must not
        // hit the skus table's unique(vmos_good_id, android_version) index.
        Http::fake(['*/cloudNumber/skus' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            ['countryCode' => 'GB', 'status' => 'AVAILABLE', 'skus' => [
                ['planId' => 2, 'durationDays' => 30, 'chargeCents' => 998],
            ]],
            ['countryCode' => 'US', 'status' => 'AVAILABLE', 'skus' => [
                ['planId' => 2, 'durationDays' => 30, 'chargeCents' => 1200],
            ]],
        ]])]);

        $this->artisan('vmos:sync-cloud-number-skus')->assertExitCode(0);

        $this->assertSame(2, Sku::cloudNumbers()->where('vmos_good_id', 2)->count());
    }

    #[Test]
    public function out_of_stock_countries_sync_as_sold_out(): void
    {
        Http::fake(['*/cloudNumber/skus' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            ['countryCode' => 'US', 'status' => 'NO_STOCK', 'skus' => [
                ['planId' => 9, 'durationDays' => 30, 'chargeCents' => 500],
            ]],
        ]])]);

        $this->artisan('vmos:sync-cloud-number-skus');

        $this->assertTrue(Sku::cloudNumbers()->first()->sell_out);
    }
}
