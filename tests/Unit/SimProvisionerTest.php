<?php

namespace Tests\Unit;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\SimProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeInstance(?string $countryCode): CloudInstance
    {
        $user = User::factory()->create();
        $sku = Sku::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'sku_id' => $sku->id, 'country_code' => $countryCode]);

        return CloudInstance::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'sku_id' => $sku->id, 'pad_code' => 'AC001']);
    }

    #[Test]
    public function it_sets_the_sim_to_the_checkout_region_and_records_a_task(): void
    {
        Http::fake(['*/updateSIM' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['taskId' => 42]])]);

        $instance = $this->makeInstance('JP');

        app(SimProvisioner::class)->apply($instance);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'updateSIM')
            && $r['padCode'] === 'AC001' && $r['countryCode'] === 'JP');

        $this->assertDatabaseHas('instance_tasks', [
            'cloud_instance_id' => $instance->id,
            'type' => 'update_sim',
            'vmos_task_id' => 42,
        ]);
    }

    #[Test]
    public function it_does_nothing_when_no_region_was_picked_at_checkout(): void
    {
        Http::fake();

        $instance = $this->makeInstance(null);

        app(SimProvisioner::class)->apply($instance);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_reapply_once_already_run(): void
    {
        Http::fake(['*/updateSIM' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $instance = $this->makeInstance('US');

        app(SimProvisioner::class)->apply($instance);
        app(SimProvisioner::class)->apply($instance->fresh());

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_vmos_failure_is_logged_not_thrown(): void
    {
        Http::fake(['*/updateSIM' => Http::response(['code' => 500, 'msg' => 'System is busy'])]);

        $instance = $this->makeInstance('DE');

        app(SimProvisioner::class)->apply($instance);

        $this->assertDatabaseMissing('instance_tasks', ['cloud_instance_id' => $instance->id, 'type' => 'update_sim']);
    }
}
