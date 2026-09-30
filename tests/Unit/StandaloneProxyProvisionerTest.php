<?php

namespace Tests\Unit;

use App\Models\CustomerProxy;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\StandaloneProxyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StandaloneProxyProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(int $quantity = 1): Order
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create([
            'type' => Sku::TYPE_PROXY,
            'vmos_good_id' => 11,
            'default_country_code' => 'US',
            'vmos_cost_price' => 9.99,
            'price' => 12.99,
        ]);

        return Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'quantity' => $quantity]);
    }

    #[Test]
    public function a_finished_purchase_records_the_proxy_and_completes_the_order(): void
    {
        Http::fake([
            '*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 'PX-TASK-1']]),
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'FINISHED']]),
            '*/queryProxyList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['proxyId' => 555, 'proxyHost' => '1.2.3.4', 'proxyPort' => 8080, 'account' => 'user1', 'proxyCountry' => 'us', 'proxyUseNumber' => 0],
            ]]]),
        ]);

        $order = $this->makeOrder();

        app(StandaloneProxyProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);

        $proxy = CustomerProxy::where('order_id', $order->id)->first();
        $this->assertNotNull($proxy);
        $this->assertSame(555, $proxy->vmos_proxy_id);
        $this->assertSame('1.2.3.4', $proxy->host);
        $this->assertSame(8080, $proxy->port);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'createProxyOrder') && ! str_contains($r->url(), 'status')
            && $r['clientRequestId'] === 'order-'.$order->id.'-standalone-proxy');
    }

    #[Test]
    public function a_still_processing_purchase_leaves_the_order_provisioning(): void
    {
        Http::fake([
            '*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 'PX-TASK-2']]),
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'PROCESSING']]),
        ]);

        $order = $this->makeOrder();

        app(StandaloneProxyProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_PROVISIONING, $order->status);
        $this->assertSame('PX-TASK-2', $order->vmos_order_id);
        $this->assertSame(0, CustomerProxy::count());
    }

    #[Test]
    public function polling_a_now_finished_purchase_completes_the_order(): void
    {
        $order = $this->makeOrder();
        $order->update(['status' => Order::STATUS_PROVISIONING, 'vmos_order_id' => 'PX-TASK-3']);

        Http::fake([
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'FINISHED']]),
            '*/queryProxyList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['proxyId' => 777, 'proxyHost' => '5.6.7.8', 'proxyPort' => 9090, 'account' => 'user2', 'proxyCountry' => 'us', 'proxyUseNumber' => 0],
            ]]]),
        ]);

        app(StandaloneProxyProvisioner::class)->pollAndFinalize($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(1, CustomerProxy::where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_needs_review_purchase_fails_the_order_without_resubmitting(): void
    {
        $order = $this->makeOrder();
        $order->update(['status' => Order::STATUS_PROVISIONING, 'vmos_order_id' => 'PX-TASK-4']);

        Http::fake(['*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'NEEDS_REVIEW']])]);

        app(StandaloneProxyProvisioner::class)->pollAndFinalize($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertStringContainsString('manual review', $order->error_message);
        $this->assertSame(0, CustomerProxy::count());
    }

    #[Test]
    public function an_ambiguous_match_fails_the_order_instead_of_guessing(): void
    {
        Http::fake([
            '*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 'PX-TASK-5']]),
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'FINISHED']]),
            '*/queryProxyList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => []]]),
        ]);

        $order = $this->makeOrder();

        app(StandaloneProxyProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertStringContainsString("couldn't be positively matched", $order->error_message);
        $this->assertSame(0, CustomerProxy::count());
    }

    #[Test]
    public function already_claimed_proxies_are_never_matched_again(): void
    {
        Http::fake([
            '*/createProxyOrder' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 'PX-TASK-6']]),
            '*/createProxyOrder/status*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'FINISHED']]),
            '*/queryProxyList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['proxyId' => 42, 'proxyHost' => '9.9.9.9', 'proxyPort' => 80, 'account' => 'x', 'proxyCountry' => 'us', 'proxyUseNumber' => 0],
            ]]]),
        ]);

        CustomerProxy::factory()->create(['vmos_proxy_id' => 42]);

        $order = $this->makeOrder();

        app(StandaloneProxyProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame(1, CustomerProxy::where('vmos_proxy_id', 42)->count());
    }

    #[Test]
    public function a_connection_failure_fails_the_order_without_throwing(): void
    {
        Http::fake(['*/createProxyOrder' => Http::response(['code' => 500, 'msg' => 'System is busy'])]);

        $order = $this->makeOrder();

        app(StandaloneProxyProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertStringContainsString('System is busy', $order->error_message);
    }
}
