<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\CustomerProxy;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\OrderProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX']);
    }

    #[Test]
    public function guests_can_browse_but_not_buy(): void
    {
        Sku::factory()->create(['type' => Sku::TYPE_PROXY, 'default_country_code' => 'US', 'price' => 12.99]);

        $this->get(route('proxies.index'))->assertOk()->assertSee('Log in to buy');
    }

    #[Test]
    public function the_buy_modal_lists_every_regions_plans(): void
    {
        Sku::factory()->create(['type' => Sku::TYPE_PROXY, 'default_country_code' => 'US', 'price' => 12.99]);
        Sku::factory()->create(['type' => Sku::TYPE_PROXY, 'default_country_code' => 'JP', 'price' => 14.99]);

        $response = $this->actingAs(User::factory()->create())->get(route('proxies.index'));

        $response->assertOk()->assertSee('$12.99')->assertSee('$14.99')->assertSee('Buy Proxy');
    }

    #[Test]
    public function the_buy_modal_shows_full_country_names_not_codes(): void
    {
        Sku::factory()->create(['type' => Sku::TYPE_PROXY, 'default_country_code' => 'JP', 'price' => 14.99]);

        $this->actingAs(User::factory()->create())->get(route('proxies.index'))->assertOk()->assertSee('Japan');
    }

    #[Test]
    public function a_customer_can_add_their_own_proxy_for_free(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('proxies.store'), [
            'label' => 'Home proxy', 'host' => '9.8.7.6', 'port' => 1080, 'account' => 'me', 'password' => 'secret',
            'proxy_name' => 'socks5', 'proxy_type' => 'proxy', 'remarks' => 'from my ISP',
        ])->assertSessionHas('status');

        $proxy = CustomerProxy::where('user_id', $user->id)->first();
        $this->assertNotNull($proxy);
        $this->assertTrue($proxy->isCustom());
        $this->assertSame('Home proxy', $proxy->label);
        $this->assertSame('from my ISP', $proxy->remarks);
        $this->assertNull($proxy->order_id);
        $this->assertNull($proxy->sku_id);
        $this->assertTrue($proxy->isDelivered());
    }

    #[Test]
    public function the_owned_proxies_table_shows_ip_address_and_expiration(): void
    {
        $user = User::factory()->create();
        CustomerProxy::factory()->create([
            'user_id' => $user->id, 'host' => '5.5.5.5', 'port' => 1080,
            'raw_payload' => ['list_record' => ['expireTime' => strtotime('2027-01-15')]],
        ]);

        $this->actingAs($user)->get(route('proxies.index'))
            ->assertOk()
            ->assertSee('IP Address')
            ->assertSee('5.5.5.5:1080')
            ->assertSee('Expiration time')
            ->assertSee('15 Jan 2027');
    }

    #[Test]
    public function a_customer_can_edit_their_own_manually_added_proxy(): void
    {
        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $user->id, 'host' => '1.1.1.1', 'port' => 1080]);

        $this->actingAs($user)->put(route('proxies.update', $proxy), [
            'label' => 'Updated label', 'host' => '2.2.2.2', 'port' => 9090, 'account' => 'newuser',
            'proxy_name' => 'http-relay', 'proxy_type' => 'vpn', 'remarks' => 'updated',
        ])->assertSessionHas('status');

        $proxy->refresh();
        $this->assertSame('Updated label', $proxy->label);
        $this->assertSame('2.2.2.2', $proxy->host);
        $this->assertSame(9090, $proxy->port);
        $this->assertSame('http-relay', $proxy->proxy_name);
    }

    #[Test]
    public function leaving_the_password_blank_when_editing_keeps_the_existing_one(): void
    {
        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $user->id, 'password' => 'original-secret']);

        $this->actingAs($user)->put(route('proxies.update', $proxy), [
            'host' => $proxy->host, 'port' => $proxy->port,
            'proxy_name' => 'socks5', 'proxy_type' => 'proxy',
        ]);

        $this->assertSame('original-secret', $proxy->fresh()->password);
    }

    #[Test]
    public function a_bought_proxy_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(route('proxies.update', $proxy), [
            'host' => 'x', 'port' => 1, 'proxy_name' => 'socks5', 'proxy_type' => 'proxy',
        ])->assertForbidden();
    }

    #[Test]
    public function a_stranger_cannot_edit_someone_elses_proxy(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->put(route('proxies.update', $proxy), [
            'host' => 'x', 'port' => 1, 'proxy_name' => 'socks5', 'proxy_type' => 'proxy',
        ])->assertForbidden();
    }

    #[Test]
    public function attaching_a_manually_added_proxy_uses_setcustomproxy_not_attachproxies(): void
    {
        Http::fake(['*/setProxy' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $user = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $user->id, 'host' => '9.8.7.6', 'port' => 1080]);

        $this->actingAs($user)
            ->post(route('proxies.attach', $proxy), ['pad_code' => 'AC001'])
            ->assertSessionHas('status');

        $this->assertSame('AC001', $proxy->fresh()->attached_pad_code);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setProxy')
            && $r['padCodes'] === ['AC001'] && $r['ip'] === '9.8.7.6' && $r['port'] === 1080);
    }

    #[Test]
    public function a_customer_can_remove_a_manually_added_proxy(): void
    {
        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('proxies.destroy', $proxy))->assertSessionHas('status');

        $this->assertNull(CustomerProxy::find($proxy->id));
    }

    #[Test]
    public function a_bought_proxy_cannot_be_removed_this_way(): void
    {
        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('proxies.destroy', $proxy))->assertForbidden();

        $this->assertNotNull(CustomerProxy::find($proxy->id));
    }

    #[Test]
    public function a_stranger_cannot_remove_someone_elses_manual_proxy(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $proxy = CustomerProxy::factory()->custom()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->delete(route('proxies.destroy', $proxy))->assertForbidden();
    }

    #[Test]
    public function a_customer_can_buy_a_proxy_and_it_completes(): void
    {
        Http::fake([
            '*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 'PX-1']]),
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'FINISHED']]),
            '*/queryProxyList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['proxyId' => 900, 'proxyHost' => '2.2.2.2', 'proxyPort' => 1080, 'account' => 'u', 'proxyCountry' => 'us', 'proxyUseNumber' => 0],
            ]]]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['type' => Sku::TYPE_PROXY, 'default_country_code' => 'US', 'price' => 12.99]);

        $this->actingAs($user)->post(route('orders.store'), [
            'sku_id' => $sku->id, 'quantity' => 1,
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame(Sku::TYPE_PROXY, $order->sku->type);

        $order->update(['status' => Order::STATUS_PAID]);
        app(OrderProvisioner::class)->provision($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(1, CustomerProxy::where('order_id', $order->id)->count());
    }

    #[Test]
    public function attaching_to_a_device_you_own_calls_vmos_and_records_it(): void
    {
        Http::fake(['*/batchPadConfigProxy' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $user = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id, 'vmos_proxy_id' => 900]);

        $this->actingAs($user)
            ->post(route('proxies.attach', $proxy), ['pad_code' => 'AC001'])
            ->assertSessionHas('status');

        $this->assertSame('AC001', $proxy->fresh()->attached_pad_code);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'batchPadConfigProxy')
            && $r['padCodes'] === ['AC001'] && $r['proxyIds'] === [900]);
    }

    #[Test]
    public function a_customer_cannot_attach_to_a_device_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC001']);
        $proxy = CustomerProxy::factory()->create(['user_id' => $intruder->id, 'vmos_proxy_id' => 900]);

        $this->actingAs($intruder)
            ->post(route('proxies.attach', $proxy), ['pad_code' => 'AC001'])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_cannot_attach_someone_elses_proxy(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $intruder->id, 'pad_code' => 'AC001']);
        $proxy = CustomerProxy::factory()->create(['user_id' => $owner->id, 'vmos_proxy_id' => 900]);

        $this->actingAs($intruder)
            ->post(route('proxies.attach', $proxy), ['pad_code' => 'AC001'])
            ->assertForbidden();
    }

    #[Test]
    public function detaching_calls_vmos_and_clears_the_attachment(): void
    {
        Http::fake(['*/setProxy' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id, 'attached_pad_code' => 'AC001']);

        $this->actingAs($user)->post(route('proxies.detach', $proxy))->assertSessionHas('status');

        $this->assertNull($proxy->fresh()->attached_pad_code);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setProxy') && $r['padCodes'] === ['AC001'] && $r['enable'] === false);
    }

    #[Test]
    public function testing_a_proxy_checks_it_via_vmos(): void
    {
        Http::fake(['*/checkIP' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['proxyWorking' => true, 'proxyLocation' => 'Ashburn, US']])]);

        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id, 'host' => '1.1.1.1', 'port' => 1080]);

        $this->actingAs($user)->post(route('proxies.test', $proxy))->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'checkIP') && $r['host'] === '1.1.1.1' && $r['type'] === 'Socks5');
    }

    #[Test]
    public function a_proxy_vmos_reports_as_not_working_counts_as_a_failed_test(): void
    {
        Http::fake(['*/checkIP' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['proxyWorking' => false]])]);

        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id, 'host' => '1.1.1.1', 'port' => 1080]);

        $this->actingAs($user)->post(route('proxies.test', $proxy))->assertSessionHas('error');
    }

    #[Test]
    public function a_stranger_cannot_manage_someone_elses_proxy(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->post(route('proxies.detach', $proxy))->assertForbidden();
        $this->actingAs($stranger)->post(route('proxies.test', $proxy))->assertForbidden();
    }
}
