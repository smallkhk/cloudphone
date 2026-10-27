<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstanceProvisioningSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function opening_my_devices_picks_up_a_device_vmos_has_finished_without_waiting_for_cron(): void
    {
        $user = User::factory()->create();
        $instance = CloudInstance::factory()->create([
            'user_id' => $user->id, 'pad_code' => null, 'equipment_id' => '5001',
        ]);

        Http::fake(['*/vcpcloud/api/padApi/userPadList' => Http::response([
            'code' => 200, 'msg' => 'success',
            'data' => [['padCode' => 'AC500100', 'equipmentId' => 5001, 'cvmStatus' => 100, 'status' => 1]],
        ])]);

        $this->actingAs($user)->get(route('instances.index'))
            ->assertOk()
            ->assertSee('AC500100')
            ->assertDontSee('location.reload', false);

        $this->assertSame('AC500100', $instance->fresh()->pad_code);
    }

    #[Test]
    public function a_device_still_initializing_keeps_the_page_auto_refreshing(): void
    {
        $user = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => null, 'equipment_id' => '5002']);

        Http::fake(['*/vcpcloud/api/padApi/userPadList' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);

        $this->actingAs($user)->get(route('instances.index'))
            ->assertOk()
            ->assertSee('location.reload', false);
    }

    #[Test]
    public function a_vmos_error_does_not_break_the_sync_command(): void
    {
        CloudInstance::factory()->create(['pad_code' => null, 'equipment_id' => '5003']);

        Http::fake(['*/vcpcloud/api/padApi/userPadList' => Http::response(['code' => 500, 'msg' => 'boom'], 500)]);

        $this->artisan('vmos:sync-instances')->assertExitCode(0);
    }
}
