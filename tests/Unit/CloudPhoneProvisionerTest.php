<?php

namespace Tests\Unit;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\CloudPhoneProvisioner;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudPhoneProvisionerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_buys_the_sku_and_records_cloud_instances(): void
    {
        Http::fake([
            '*/vcpcloud/api/padApi/createMoneyOrder' => Http::response([
                'code' => 200,
                'msg' => 'success',
                'data' => [
                    ['id' => 1, 'orderId' => 'VMOS-CLOUD123', 'equipmentId' => 555001, 'createTime' => now()->toDateTimeString(), 'creater' => '1'],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['vmos_good_id' => 74, 'android_version' => '13']);
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'quantity' => 1]);

        app(CloudPhoneProvisioner::class)->provision($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame('VMOS-CLOUD123', $order->vmos_order_id);
        $this->assertDatabaseHas('cloud_instances', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'equipment_id' => 555001,
        ]);
    }

    #[Test]
    public function it_marks_the_order_failed_when_vmos_rejects_it(): void
    {
        Http::fake([
            '*/vcpcloud/api/padApi/createMoneyOrder' => Http::response([
                'code' => 110031, 'msg' => 'Sold out',
            ]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id]);

        try {
            app(CloudPhoneProvisioner::class)->provision($order);
            $this->fail('Expected exception was not thrown.');
        } catch (\App\Exceptions\VmosApiException $e) {
            // expected
        }

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame('Sold out', $order->error_message);
        $this->assertSame(0, CloudInstance::count());
    }
}
