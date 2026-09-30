<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncProxySkusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('vmos_access_key', 'ak', true);
        Setting::set('vmos_secret_key', 'sk', true);
    }

    #[Test]
    public function it_creates_a_sku_per_product_and_region(): void
    {
        Http::fake([
            '*/proxyGoodList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['proxyGoodId' => 11, 'proxyGoodName' => '30 day residential', 'proxyGoodPrice' => 999, 'proxyGoodType' => 1],
            ]]),
            '*/getProxyRegion' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['country' => 'US', 'countryZh' => 'United States'],
                ['country' => 'GB', 'countryZh' => 'United Kingdom'],
            ]]),
        ]);

        $this->artisan('vmos:sync-proxy-skus')->assertExitCode(0);

        $this->assertSame(2, Sku::proxies()->where('vmos_good_id', 11)->count());

        $sku = Sku::proxies()->where('vmos_good_id', 11)->where('default_country_code', 'US')->first();
        $this->assertNotNull($sku);
        $this->assertEquals(9.99, $sku->vmos_cost_price);
        $this->assertTrue($sku->active);
        $this->assertSame('px-us', $sku->android_version);
    }

    #[Test]
    public function the_same_product_in_two_countries_does_not_collide(): void
    {
        Http::fake([
            '*/proxyGoodList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['proxyGoodId' => 5, 'proxyGoodName' => 'Package', 'proxyGoodPrice' => 500],
            ]]),
            '*/getProxyRegion' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['country' => 'US'],
                ['country' => 'JP'],
            ]]),
        ]);

        $this->artisan('vmos:sync-proxy-skus')->assertExitCode(0);

        $this->assertSame(2, Sku::proxies()->where('vmos_good_id', 5)->count());
    }

    #[Test]
    public function resyncing_never_overwrites_an_admin_set_price(): void
    {
        Http::fake([
            '*/proxyGoodList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['proxyGoodId' => 5, 'proxyGoodName' => 'Package', 'proxyGoodPrice' => 500],
            ]]),
            '*/getProxyRegion' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [['country' => 'US']]]),
        ]);

        $this->artisan('vmos:sync-proxy-skus');
        $sku = Sku::proxies()->first();
        $sku->update(['price' => 99.99]);

        $this->artisan('vmos:sync-proxy-skus');

        $this->assertEquals(99.99, $sku->fresh()->price);
    }
}
