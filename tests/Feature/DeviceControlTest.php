<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected CloudInstance $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->device = CloudInstance::factory()->create([
            'user_id' => $this->owner->id,
            'pad_code' => 'AC55501',
        ]);
    }

    /**
     * Stubs every VMOS call as a bare success.
     *
     * Called explicitly per test rather than in setUp(), because Http::fake()
     * merges stubs and the first registered match wins — a catch-all in setUp()
     * would shadow every more specific stub a test registers afterwards.
     */
    protected function fakeVmosOk(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);
    }

    protected function withVmosCredentials(): void
    {
        Setting::set('vmos_access_key', 'ak', true);
        Setting::set('vmos_secret_key', 'sk', true);
    }

    #[Test]
    public function the_owner_can_open_the_control_panel(): void
    {
        Http::fake([
            '*/padInfo' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                'padCode' => 'AC55501', 'phoneNumber' => '+6510633153', 'padImei' => '525036719631842',
                'simCountry' => 'SG', 'timeZone' => 'Asia/Singapore', 'publicIp' => '192.169.96.14',
            ]]),
            '*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);

        $this->actingAs($this->owner)->get(route('instances.show', $this->device))
            ->assertOk()
            ->assertSee('AC55501')
            ->assertSee('+6510633153')
            ->assertSee('525036719631842');
    }

    #[Test]
    public function a_stranger_cannot_open_or_control_someone_elses_device(): void
    {
        $this->fakeVmosOk();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('instances.show', $this->device))->assertForbidden();
        $this->actingAs($stranger)->post(route('instances.sim', $this->device), ['country_code' => 'US'])->assertForbidden();
        $this->actingAs($stranger)->post(route('instances.gps', $this->device), ['latitude' => 1, 'longitude' => 2])->assertForbidden();
        $this->actingAs($stranger)->post(route('instances.new-device', $this->device))->assertForbidden();
    }

    #[Test]
    public function an_admin_can_control_any_device(): void
    {
        $this->fakeVmosOk();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('instances.sim', $this->device), ['country_code' => 'US'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    #[Test]
    public function requesting_a_new_phone_number_calls_updatesim_with_the_country(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)
            ->post(route('instances.sim', $this->device), ['country_code' => 'ng'])
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'updateSIM')
            && $r['padCode'] === 'AC55501'
            && $r['countryCode'] === 'NG'); // normalised to uppercase
    }

    #[Test]
    public function gps_coordinates_are_validated(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)
            ->post(route('instances.gps', $this->device), ['latitude' => 200, 'longitude' => 0])
            ->assertSessionHasErrors('latitude');
    }

    #[Test]
    public function setting_gps_sends_the_coordinates_to_vmos(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)
            ->post(route('instances.gps', $this->device), ['latitude' => 1.3398, 'longitude' => 103.6967])
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'gpsInjectInfo')
            && $r['latitude'] === 1.3398 && $r['longitude'] === 103.6967);
    }

    #[Test]
    public function a_vmos_error_becomes_a_flash_message_not_a_500(): void
    {
        Http::fake(['*' => Http::response(['code' => 110028, 'msg' => 'Instance does not exist'])]);

        $this->actingAs($this->owner)
            ->post(route('instances.sim', $this->device), ['country_code' => 'US'])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Instance does not exist'));
    }

    #[Test]
    public function controls_are_refused_while_a_device_is_still_provisioning(): void
    {
        $this->fakeVmosOk();
        $pending = CloudInstance::factory()->create(['user_id' => $this->owner->id, 'pad_code' => null]);

        $this->actingAs($this->owner)
            ->post(route('instances.sim', $pending), ['country_code' => 'US'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'provisioned'));
    }

    #[Test]
    public function a_custom_proxy_can_be_applied_and_cleared(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)->post(route('instances.proxy', $this->device), [
            'ip' => '154.81.40.200', 'port' => 63007, 'account' => 'u', 'password' => 'p',
            'proxy_name' => 'socks5', 'proxy_type' => 'proxy',
        ])->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'setProxy')
            && $r['ip'] === '154.81.40.200' && $r['port'] === 63007 && $r['enable'] === true);

        $this->actingAs($this->owner)->delete(route('instances.proxy.clear', $this->device))
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'setProxy') && $r['enable'] === false);
    }

    #[Test]
    public function installing_an_app_requires_a_valid_url(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)
            ->post(route('instances.apps.install', $this->device), ['apk_url' => 'not-a-url'])
            ->assertSessionHasErrors('apk_url');
    }

    #[Test]
    public function installing_an_app_pushes_the_url_to_vmos(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)->post(route('instances.apps.install', $this->device), [
            'apk_url' => 'https://example.com/app.apk',
            'package_name' => 'com.example.app',
        ])->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'uploadFileV3')
            && $r['url'] === 'https://example.com/app.apk');
    }

    #[Test]
    public function app_actions_are_dispatched_with_the_package_name(): void
    {
        $this->fakeVmosOk();
        foreach (['start' => 'startApp', 'stop' => 'stopApp', 'uninstall' => 'uninstallApp'] as $action => $endpoint) {
            $this->actingAs($this->owner)->post(route('instances.apps.action', $this->device), [
                'package_name' => 'com.example.app', 'action' => $action,
            ])->assertSessionHas('status');

            Http::assertSent(fn ($r) => str_contains($r->url(), $endpoint) && $r['pkgName'] === 'com.example.app');
        }
    }

    #[Test]
    public function enabling_adb_returns_the_connect_command(): void
    {
        Http::fake([
            '*/openOnlineAdb' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
            '*/padApi/adb' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['padCode' => 'AC55501', 'ip' => '1.2.3.4', 'port' => 5555],
            ]]),
        ]);

        $this->actingAs($this->owner)->post(route('instances.adb', $this->device), ['enable' => '1'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'adb connect 1.2.3.4:5555'));
    }

    #[Test]
    public function one_key_new_device_asks_vmos_to_replace_the_identity(): void
    {
        $this->fakeVmosOk();
        $this->actingAs($this->owner)->post(route('instances.new-device', $this->device), ['wipe_data' => '1'])
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'padReplaceNew')
            && $r['padCodes'] === ['AC55501'] && $r['wipeData'] === true);
    }

    // --- Sync / pricing --------------------------------------------------

    #[Test]
    public function sync_converts_cent_prices_into_dollars(): void
    {
        $this->withVmosCredentials();
        Setting::set('vmos_price_unit', 'cents');

        Http::fake(['*/getCloudGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'configs' => [[
                'configId' => 7, 'configName' => 'Samsung Galaxy A53', 'androidVersion' => '13',
                'goodTimes' => [['id' => 501, 'showContent' => '1 month', 'goodTime' => 43200, 'currentPrice' => 499]],
            ]],
        ]])]);

        $this->artisan('vmos:sync-skus')->assertSuccessful();

        $sku = Sku::where('vmos_good_id', 501)->firstOrFail();
        $this->assertEquals(4.99, $sku->vmos_cost_price);
        $this->assertEquals(6.49, $sku->price); // 4.99 + 30% default markup
    }

    #[Test]
    public function resyncing_never_overwrites_a_price_the_admin_set(): void
    {
        $this->withVmosCredentials();

        $payload = ['code' => 200, 'msg' => 'success', 'data' => ['configs' => [[
            'configId' => 7, 'configName' => 'Samsung Galaxy A53', 'androidVersion' => '13',
            'goodTimes' => [['id' => 501, 'showContent' => '1 month', 'goodTime' => 43200, 'currentPrice' => 499]],
        ]]]];

        Http::fake(['*/getCloudGoodList*' => Http::response($payload)]);

        $this->artisan('vmos:sync-skus')->assertSuccessful();

        // Admin sets their own price…
        Sku::where('vmos_good_id', 501)->update(['price' => 19.99]);

        // …and a later scheduled sync must leave it alone.
        $this->artisan('vmos:sync-skus')->assertSuccessful();

        $this->assertEquals(19.99, Sku::where('vmos_good_id', 501)->first()->price);
    }

    #[Test]
    public function sync_can_treat_prices_as_whole_units_instead(): void
    {
        $this->withVmosCredentials();
        Setting::set('vmos_price_unit', 'units');

        Http::fake(['*/getCloudGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'configs' => [[
                'configId' => 7, 'configName' => 'Pixel', 'androidVersion' => '14',
                'goodTimes' => [['id' => 502, 'showContent' => '1 month', 'goodTime' => 43200, 'currentPrice' => 4.99]],
            ]],
        ]])]);

        $this->artisan('vmos:sync-skus')->assertSuccessful();

        $this->assertEquals(4.99, Sku::where('vmos_good_id', 502)->first()->vmos_cost_price);
    }

    #[Test]
    public function sync_pulls_every_known_android_version_by_default(): void
    {
        // A real account was confirmed to return only 2 of 4 versions when no
        // filter was sent — the fix is to request each version explicitly.
        $this->withVmosCredentials();

        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
            $version = $query['androidVersion'] ?? null;

            if (! in_array($version, ['13', '14', '15', '16'], true)) {
                return Http::response(['code' => 200, 'msg' => 'success', 'data' => ['configs' => []]]);
            }

            return Http::response(['code' => 200, 'msg' => 'success', 'data' => ['configs' => [[
                'configId' => (int) $version, 'configName' => "Device Android {$version}", 'androidVersion' => $version,
                'goodTimes' => [['id' => (int) "9{$version}", 'showContent' => '1 month', 'goodTime' => 43200, 'currentPrice' => 499]],
            ]]]]);
        });

        $this->artisan('vmos:sync-skus')->assertSuccessful();

        $this->assertSame(4, Sku::where('type', Sku::TYPE_CLOUD_PHONE)->distinct('android_version')->count('android_version'));
        foreach (['13', '14', '15', '16'] as $version) {
            $this->assertDatabaseHas('skus', ['android_version' => $version, 'name' => "Device Android {$version}"]);
        }
    }

    #[Test]
    public function sync_fails_loudly_when_vmos_returns_an_empty_catalogue(): void
    {
        $this->withVmosCredentials();

        Http::fake(['*/getCloudGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['configs' => []]])]);

        $this->artisan('vmos:sync-skus')->assertFailed();
        $this->assertSame(0, Sku::count());
    }

    #[Test]
    public function sync_refuses_to_run_without_credentials(): void
    {
        config(['vmos.access_key' => null, 'vmos.secret_key' => null]);

        $this->artisan('vmos:sync-skus')->assertFailed();
    }

    // --- Admin proxy + diagnostics screens -------------------------------

    #[Test]
    public function the_diagnostics_page_shows_the_raw_response(): void
    {
        $this->withVmosCredentials();

        Http::fake(['*/getCloudGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'configs' => [['configId' => 1, 'configName' => 'Probe Device', 'goodTimes' => [['id' => 9, 'currentPrice' => 499, 'showContent' => '1 month']]]],
        ]])]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.diagnostics.index', ['probe' => 'catalogue_all']))
            ->assertOk()
            ->assertSee('Probe Device')
            ->assertSee('$4.99'); // cent-conversion hint
    }

    #[Test]
    public function the_proxy_page_survives_a_failing_vmos_endpoint(): void
    {
        $this->withVmosCredentials();

        Http::fake([
            '*/proxyGoodList*' => Http::response(['code' => 500, 'msg' => 'Proxy service unavailable']),
            '*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.proxies.index'))
            ->assertOk()
            ->assertSee('Proxy service unavailable');
    }

    #[Test]
    public function customers_cannot_reach_proxy_or_diagnostics_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.proxies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.diagnostics.index'))->assertForbidden();
    }
}
