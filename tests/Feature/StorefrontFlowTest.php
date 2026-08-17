<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\CryptoPayment;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crypto.usdt_trc20_address' => 'TReceivingAddressXXXXXXXXXXXXXXXXX']);
    }

    #[Test]
    public function guests_can_browse_plans_but_must_log_in_to_buy(): void
    {
        Sku::factory()->create(['name' => 'Samsung Galaxy A53', 'price' => 9.99, 'active' => true]);

        $this->get(route('plans.index'))->assertOk()->assertSee('Samsung Galaxy A53')->assertSee('Log in to buy');
    }

    #[Test]
    public function the_landing_page_renders_for_guests(): void
    {
        Sku::factory()->create(['name' => 'Samsung Galaxy A53', 'price' => 9.99, 'active' => true]);

        $this->get('/')->assertOk()->assertSee('Samsung Galaxy A53');
    }

    #[Test]
    public function inactive_or_sold_out_skus_are_hidden_from_the_storefront(): void
    {
        Sku::factory()->create(['name' => 'Hidden Plan', 'active' => false]);
        Sku::factory()->create(['name' => 'Sold Out Plan', 'sell_out' => true]);

        $this->get(route('plans.index'))->assertOk()->assertDontSee('Hidden Plan')->assertDontSee('Sold Out Plan');
    }

    #[Test]
    public function a_logged_in_user_can_buy_a_plan_and_gets_a_crypto_quote(): void
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00]);

        $response = $this->actingAs($user)->post('/orders', ['sku_id' => $sku->id, 'quantity' => 1]);

        $order = Order::first();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame($user->id, $order->user_id);
        $this->assertEquals(15.00, $order->total_price);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);

        $payment = $order->latestPayment();
        $this->assertNotNull($payment);
        $this->assertEquals(15.00, $payment->amount_crypto);
        $this->assertSame('TReceivingAddressXXXXXXXXXXXXXXXXX', $payment->pay_to_address);
    }

    #[Test]
    public function a_customer_can_pick_a_region_at_checkout(): void
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00, 'default_country_code' => null]);

        $this->actingAs($user)->post('/orders', [
            'sku_id' => $sku->id,
            'quantity' => 1,
            'country_code' => 'jp',
        ])->assertRedirect();

        // Normalised to uppercase, same convention as SIM regeneration.
        $this->assertSame('JP', Order::first()->country_code);
    }

    #[Test]
    public function a_customer_can_add_their_own_proxy_at_checkout_for_free(): void
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00]);

        $this->actingAs($user)->post('/orders', [
            'sku_id' => $sku->id,
            'quantity' => 1,
            'proxy_mode' => 'custom',
            'proxy_ip' => '10.0.0.5',
            'proxy_port' => 1080,
            'proxy_account' => 'alice',
            'proxy_password' => 'secret',
            'proxy_name' => 'socks5',
            'proxy_type' => 'proxy',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame('custom', $order->proxy_mode);
        // proxy_status is set once provisioning actually runs (ProxyProvisioner),
        // not at checkout — the order isn't even paid for yet at this point.
        $this->assertNull($order->proxy_status);
        $this->assertSame('10.0.0.5', $order->proxy_config['ip']);
        $this->assertSame(1080, $order->proxy_config['port']);
        $this->assertEquals(0, $order->proxy_price);
        // A free custom proxy must not change the device's own price.
        $this->assertEquals(15.00, $order->total_price);
    }

    #[Test]
    public function buying_a_vmos_proxy_add_on_is_priced_with_markup_and_added_to_the_total(): void
    {
        Setting::set('default_markup_percent', '30');

        Http::fake([
            '*/proxyGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['proxyGoodId' => 9, 'proxyGoodName' => 'Residential', 'proxyGoodPrice' => 1000], // $10.00 cost, cents
            ]]),
            '*/getProxyRegion*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['country' => 'us', 'countryZh' => 'United States'],
            ]]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00]);

        $this->actingAs($user)->post('/orders', [
            'sku_id' => $sku->id,
            'quantity' => 1,
            'proxy_mode' => 'vmos',
            'proxy_good_id' => 9,
            'proxy_country' => 'us',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame('vmos', $order->proxy_mode);
        $this->assertEquals(13.00, $order->proxy_price); // $10 cost + 30% markup
        $this->assertEquals(10.00, $order->proxy_cost_price);
        $this->assertEquals(28.00, $order->total_price); // $15 device + $13 proxy
        $this->assertSame('US', $order->proxy_config['country']);
        $this->assertSame('United States', $order->proxy_config['proxy_address']);
    }

    #[Test]
    public function buying_an_unavailable_vmos_proxy_package_is_rejected(): void
    {
        Http::fake([
            '*/proxyGoodList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
            '*/getProxyRegion*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00]);

        $this->actingAs($user)->post('/orders', [
            'sku_id' => $sku->id,
            'quantity' => 1,
            'proxy_mode' => 'vmos',
            'proxy_good_id' => 999,
            'proxy_country' => 'us',
        ])->assertSessionHasErrors('proxy_good_id');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function checkout_falls_back_to_the_skus_default_region_when_none_is_picked(): void
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 15.00, 'default_country_code' => 'US']);

        $this->actingAs($user)->post('/orders', ['sku_id' => $sku->id, 'quantity' => 1])->assertRedirect();

        $this->assertSame('US', Order::first()->country_code);
    }

    #[Test]
    public function checkout_fails_gracefully_when_no_wallet_is_configured(): void
    {
        config(['crypto.usdt_trc20_address' => null]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create();

        $this->actingAs($user)
            ->post('/orders', ['sku_id' => $sku->id, 'quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('error');

        // No orphaned order is left behind when the payment quote can't be made.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, CryptoPayment::count());
    }

    #[Test]
    public function admins_see_the_underlying_reason_checkout_is_unavailable(): void
    {
        config(['crypto.usdt_trc20_address' => null]);

        $admin = User::factory()->create(['is_admin' => true]);
        $sku = Sku::factory()->create();

        $this->actingAs($admin)
            ->post('/orders', ['sku_id' => $sku->id, 'quantity' => 1])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'receiving wallet'));
    }

    #[Test]
    public function a_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sku = Sku::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'sku_id' => $sku->id]);

        $this->actingAs($intruder)->get(route('orders.show', $order))->assertForbidden();
    }

    #[Test]
    public function submitting_a_tx_hash_marks_the_payment_submitted(): void
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create(['price' => 5.00]);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'total_price' => 5.00]);
        $payment = CryptoPayment::factory()->create(['order_id' => $order->id, 'amount_crypto' => 5.00]);

        $this->actingAs($user)
            ->post(route('orders.payment', $order), ['tx_hash' => 'somehash1234567890'])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(CryptoPayment::STATUS_SUBMITTED, $payment->fresh()->status);
    }

    #[Test]
    public function a_user_can_restart_their_own_provisioned_instance(): void
    {
        Http::fake([
            '*/vcpcloud/api/padApi/restart' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => [['taskId' => 999, 'padCode' => 'AC123', 'vmStatus' => 1, 'taskStatus' => 1]],
            ]),
        ]);

        $user = User::factory()->create();
        $instance = CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC123']);

        $this->actingAs($user)
            ->post(route('instances.restart', $instance))
            ->assertRedirect();

        $this->assertDatabaseHas('instance_tasks', [
            'cloud_instance_id' => $instance->id,
            'vmos_task_id' => 999,
            'type' => 'restart',
        ]);
    }

    #[Test]
    public function a_user_cannot_restart_someone_elses_instance(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $instance = CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC123']);

        $this->actingAs($intruder)->post(route('instances.restart', $instance))->assertForbidden();
    }

    #[Test]
    public function non_admins_cannot_reach_the_admin_pricing_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.skus.index'))->assertForbidden();
    }

    #[Test]
    public function admins_can_update_a_skus_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sku = Sku::factory()->create(['price' => 10.00]);

        $this->actingAs($admin)
            ->patch(route('admin.skus.update', $sku), ['price' => 12.50, 'active' => '1'])
            ->assertRedirect();

        $this->assertEquals(12.50, $sku->fresh()->price);
    }
}
