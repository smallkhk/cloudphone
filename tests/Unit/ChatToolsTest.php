<?php

namespace Tests\Unit;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Models\User;
use App\Services\Chat\ChatTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The chat assistant can act on real customer hardware, so ownership
 * enforcement here matters as much as the actions actually working.
 */
class ChatToolsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refuses_to_act_on_a_device_it_does_not_own(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC55501']);

        $result = (new ChatTools)->execute($stranger, 'restart_cloud_phone', ['pad_code' => $device->pad_code]);

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('belongs to this customer', $result);
    }

    #[Test]
    public function it_refuses_to_act_when_no_user_is_signed_in(): void
    {
        $result = (new ChatTools)->execute(null, 'restart_cloud_phone', ['pad_code' => 'AC55501']);

        $this->assertStringContainsString('no signed-in customer', $result);
    }

    #[Test]
    public function it_refuses_an_unknown_pad_code(): void
    {
        $user = User::factory()->create();

        $result = (new ChatTools)->execute($user, 'restart_cloud_phone', ['pad_code' => 'NOT-REAL']);

        $this->assertStringContainsString('no cloud phone with pad code', $result);
    }

    #[Test]
    public function it_restarts_a_device_the_customer_owns_and_records_the_task(): void
    {
        $owner = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC55501']);

        Http::fake([
            '*/restart' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [['taskId' => 999]]]),
        ]);

        $result = (new ChatTools)->execute($owner, 'restart_cloud_phone', ['pad_code' => 'AC55501']);

        $this->assertStringContainsString('Restart requested', $result);
        $this->assertDatabaseHas('instance_tasks', [
            'cloud_instance_id' => $device->id,
            'type' => InstanceTask::TYPE_RESTART,
            'vmos_task_id' => 999,
        ]);
    }

    #[Test]
    public function a_vmos_failure_is_reported_as_an_error_string_not_an_exception(): void
    {
        $owner = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC55501']);

        Http::fake([
            '*/restart' => Http::response(['code' => 500, 'msg' => 'System is busy'], 200),
        ]);

        $result = (new ChatTools)->execute($owner, 'restart_cloud_phone', ['pad_code' => 'AC55501']);

        $this->assertStringContainsString('Error', $result);
    }

    #[Test]
    public function it_takes_a_screenshot_and_stores_the_url(): void
    {
        $owner = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC55501']);

        Http::fake([
            '*/getLongGenerateUrl' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [['url' => 'https://example.com/shot.png']]]),
        ]);

        $result = (new ChatTools)->execute($owner, 'take_screenshot', ['pad_code' => 'AC55501']);

        $this->assertStringContainsString('https://example.com/shot.png', $result);
        $this->assertSame('https://example.com/shot.png', $device->fresh()->screenshot_url);
    }

    #[Test]
    public function an_unknown_tool_name_is_rejected(): void
    {
        $owner = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC55501']);

        $result = (new ChatTools)->execute($owner, 'delete_everything', ['pad_code' => 'AC55501']);

        $this->assertStringContainsString('unknown tool', $result);
    }
}
