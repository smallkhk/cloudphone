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
        Http::fake(['*/checkIP' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['city' => 'Ashburn', 'country' => 'US']])]);

        $user = User::factory()->create();
        $proxy = CustomerProxy::factory()->create(['user_id' => $user->id, 'host' => '1.1.1.1', 'port' => 1080]);

        $this->actingAs($user)->post(route('proxies.test', $proxy))->assertSessionHas('status');
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
