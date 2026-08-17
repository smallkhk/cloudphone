<?php

namespace Tests\Unit;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\ProxyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProxyProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(array $overrides = []): Order
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create();

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'sku_id' => $sku->id,
        ], $overrides));
    }

    // --- purchase() --------------------------------------------------------

    #[Test]
    public function purchase_buys_a_vmos_proxy_and_marks_it_purchased(): void
    {
        Http::fake(['*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['orderId' => 'PX-1']])]);

        $order = $this->makeOrder([
            'proxy_mode' => 'vmos',
            'proxy_config' => ['good_id' => 9, 'country' => 'US', 'proxy_address' => 'United States'],
        ]);

        app(ProxyProvisioner::class)->purchase($order);

        $order->refresh();
        $this->assertSame(Order::PROXY_STATUS_PURCHASED, $order->proxy_status);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'createProxyOrder')
            && $r['proxyGoodId'] === 9 && $r['country'] === 'US' && $r['proxyAddress'] === 'United States');
    }

    #[Test]
    public function purchase_failure_marks_the_order_failed_without_throwing(): void
    {
        Http::fake(['*/createProxyOrder' => Http::response(['code' => 500, 'msg' => 'Out of stock'])]);

        $order = $this->makeOrder([
            'proxy_mode' => 'vmos',
            'proxy_config' => ['good_id' => 9, 'country' => 'US', 'proxy_address' => 'United States'],
        ]);

        app(ProxyProvisioner::class)->purchase($order);

        $order->refresh();
        $this->assertSame(Order::PROXY_STATUS_FAILED, $order->proxy_status);
        $this->assertStringContainsString('Out of stock', $order->proxy_error);
    }

    #[Test]
    public function purchase_marks_a_custom_proxy_pending_without_calling_vmos(): void
    {
        Http::fake(); // any call at all fails the test via assertNothingSent below

        $order = $this->makeOrder([
            'proxy_mode' => 'custom',
            'proxy_config' => ['ip' => '1.2.3.4', 'port' => 1080, 'proxy_name' => 'socks5', 'proxy_type' => 'proxy'],
        ]);

        app(ProxyProvisioner::class)->purchase($order);

        $this->assertSame(Order::PROXY_STATUS_PENDING, $order->fresh()->proxy_status);
        Http::assertNothingSent();
    }

    // --- apply() -------------------------------------------------------

    #[Test]
    public function apply_sets_a_custom_proxy_on_the_device_once_it_has_a_pad_code(): void
    {
        Http::fake(['*/setProxy' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $order = $this->makeOrder([
            'proxy_mode' => 'custom',
            'proxy_status' => Order::PROXY_STATUS_PENDING,
            'proxy_config' => ['ip' => '1.2.3.4', 'port' => 1080, 'account' => 'u', 'password' => 'p', 'proxy_name' => 'socks5', 'proxy_type' => 'proxy'],
        ]);
        $instance = CloudInstance::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'pad_code' => 'AC001']);

        app(ProxyProvisioner::class)->apply($instance);

        $this->assertSame(Order::PROXY_STATUS_ATTACHED, $order->fresh()->proxy_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setProxy') && $r['ip'] === '1.2.3.4' && $r['port'] === 1080);
    }

    #[Test]
    public function apply_attaches_a_purchased_vmos_proxy_matched_by_country_and_unused(): void
    {
        Http::fake([
            '*/queryProxyList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['proxyId' => 111, 'proxyCountry' => 'us', 'proxyUseNumber' => 1], // already in use — must be skipped
                ['proxyId' => 222, 'proxyCountry' => 'us', 'proxyUseNumber' => 0], // the real match
                ['proxyId' => 333, 'proxyCountry' => 'jp', 'proxyUseNumber' => 0], // wrong country
            ]]]),
            '*/batchPadConfigProxy' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);

        $order = $this->makeOrder([
            'proxy_mode' => 'vmos',
            'proxy_status' => Order::PROXY_STATUS_PURCHASED,
            'proxy_config' => ['good_id' => 9, 'country' => 'US', 'proxy_address' => 'United States'],
        ]);
        $instance = CloudInstance::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'pad_code' => 'AC002']);

        app(ProxyProvisioner::class)->apply($instance);

        $order->refresh();
        $this->assertSame(Order::PROXY_STATUS_ATTACHED, $order->proxy_status);
        $this->assertSame(222, $order->proxy_config['matched_proxy_id']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'batchPadConfigProxy')
            && $r['padCodes'] === ['AC002'] && $r['proxyIds'] === [222]);
    }

    #[Test]
    public function apply_flags_for_manual_attach_when_no_unused_proxy_matches(): void
    {
        Http::fake([
            '*/queryProxyList*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => []]]),
        ]);

        $order = $this->makeOrder([
            'proxy_mode' => 'vmos',
            'proxy_status' => Order::PROXY_STATUS_PURCHASED,
            'proxy_config' => ['good_id' => 9, 'country' => 'US', 'proxy_address' => 'United States'],
        ]);
        $instance = CloudInstance::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'pad_code' => 'AC003']);

        app(ProxyProvisioner::class)->apply($instance);

        $order->refresh();
        $this->assertSame(Order::PROXY_STATUS_FAILED, $order->proxy_status);
        $this->assertStringContainsString('Admin', $order->proxy_error);
    }

    #[Test]
    public function apply_does_nothing_when_the_order_has_no_proxy(): void
    {
        Http::fake();

        $order = $this->makeOrder();
        $instance = CloudInstance::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'pad_code' => 'AC004']);

        app(ProxyProvisioner::class)->apply($instance);

        Http::assertNothingSent();
    }
}
