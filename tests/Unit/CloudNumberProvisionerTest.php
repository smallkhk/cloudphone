<?php

namespace Tests\Unit;

use App\Models\CloudNumber;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\CloudNumberProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudNumberProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(): Order
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create([
            'type' => Sku::TYPE_CLOUD_NUMBER,
            'vmos_good_id' => 2,
            'default_country_code' => 'GB',
            'vmos_cost_price' => 9.98,
            'price' => 12.99,
        ]);

        return Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'quantity' => 1]);
    }

    #[Test]
    public function a_completed_purchase_records_the_number_and_completes_the_order(): void
    {
        Http::fake([
            '*/cloudNumber/purchase' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                'status' => 'COMPLETED', 'terminal' => true, 'orderNo' => 'VMOS-CLOUD1', 'numbers' => ['447449641145'],
            ]]),
            '*/cloudNumber/list' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['id' => 40, 'number' => '447449641145', 'autoRenew' => 1, 'rowVersion' => '0', 'status' => 'normal', 'expireTime' => '2026-10-20'],
            ]]]),
        ]);

        $order = $this->makeOrder();

        app(CloudNumberProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame('VMOS-CLOUD1', $order->vmos_order_id);

        $number = CloudNumber::where('order_id', $order->id)->first();
        $this->assertNotNull($number);
        $this->assertSame('447449641145', $number->number);
        $this->assertSame(40, $number->vmos_number_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'cloudNumber/purchase') && ! str_contains($r->url(), 'status')
            && $r['clientToken'] === 'order-'.$order->id.'-cloudnumber');
    }

    #[Test]
    public function a_still_processing_purchase_leaves_the_order_provisioning(): void
    {
        Http::fake(['*/cloudNumber/purchase' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'status' => 'PROCESSING', 'terminal' => false,
        ]])]);

        $order = $this->makeOrder();

        app(CloudNumberProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_PROVISIONING, $order->status);
        $this->assertSame('order-'.$order->id.'-cloudnumber', $order->vmos_order_id);
        $this->assertSame(0, CloudNumber::count());
    }

    #[Test]
    public function polling_a_now_finished_purchase_completes_the_order(): void
    {
        $order = $this->makeOrder();
        $order->update(['status' => Order::STATUS_PROVISIONING, 'vmos_order_id' => 'order-'.$order->id.'-cloudnumber']);

        Http::fake([
            '*/cloudNumber/purchase/status' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                'status' => 'COMPLETED', 'terminal' => true, 'orderNo' => 'VMOS-CLOUD2', 'numbers' => ['447449641146'],
            ]]),
            '*/cloudNumber/list' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['id' => 41, 'number' => '447449641146', 'autoRenew' => 1, 'rowVersion' => '0', 'status' => 'normal'],
            ]]]),
        ]);

        app(CloudNumberProvisioner::class)->pollAndFinalize($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(1, CloudNumber::where('order_id', $order->id)->count());
    }

    #[Test]
    public function insufficient_balance_fails_the_order_without_creating_a_number(): void
    {
        Http::fake(['*/cloudNumber/purchase' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'status' => 'INSUFFICIENT_BALANCE', 'terminal' => true, 'message' => 'Insufficient balance', 'chargedCents' => 0,
        ]])]);

        $order = $this->makeOrder();

        app(CloudNumberProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertStringContainsString('insufficient_balance', strtolower($order->error_message));
        $this->assertSame(0, CloudNumber::count());
    }

    #[Test]
    public function a_connection_failure_fails_the_order_without_throwing(): void
    {
        Http::fake(['*/cloudNumber/purchase' => Http::response(['code' => 500, 'msg' => 'System is busy'])]);

        $order = $this->makeOrder();

        app(CloudNumberProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertStringContainsString('System is busy', $order->error_message);
    }
}
