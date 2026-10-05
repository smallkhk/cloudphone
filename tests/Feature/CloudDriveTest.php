<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeVmosOk(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []])]);
    }

    #[Test]
    public function a_guest_cannot_view_the_cloud_drive_page(): void
    {
        $this->get(route('cloud-drive.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function the_owner_sees_their_storage_files_and_devices(): void
    {
        $user = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001', 'nickname' => 'Main phone']);

        Http::fake([
            '*/getRenewStorageInfo*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                'storageUsedAvail' => 1073741824, 'storageCapacityLimit' => 5368709120,
            ]]),
            '*/selectFiles*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                ['fileId' => 1, 'appName' => 'report.pdf'],
            ]]),
            '*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => []]),
        ]);

        $this->actingAs($user)->get(route('cloud-drive.index'))
            ->assertOk()
            ->assertSee('report.pdf')
            ->assertSee('Main phone')
            ->assertSee('1.00 GB / 5.00 GB used');
    }

    #[Test]
    public function uploading_a_file_downloads_the_url_and_reuploads_it(): void
    {
        $this->fakeVmosOk();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cloud-drive.upload'), [
            'url' => 'https://example.com/report.pdf',
            'file_name' => 'report.pdf',
        ])->assertSessionHas('status');

        Http::assertSent(fn ($r) => $r->url() === 'https://example.com/report.pdf');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'uploadFile') && $r->isMultipart());
    }

    #[Test]
    public function deleting_a_file_calls_vmos(): void
    {
        $this->fakeVmosOk();
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('cloud-drive.delete'), ['file_ids' => [1]])
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'deleteOssFiles') && $r['files'] === [1]);
    }

    #[Test]
    public function a_customer_can_back_up_a_device_they_own(): void
    {
        $user = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);

        Http::fake(['*/addBackup' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['batchId' => 'B1']])]);

        $this->actingAs($user)->post(route('cloud-drive.backup'), ['pad_code' => 'AC001'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('instance_tasks', ['cloud_instance_id' => $device->id, 'type' => 'backup']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'addBackup')
            && $r['vcPadBackupList'] === [['padCode' => 'AC001']]);
    }

    #[Test]
    public function a_customer_cannot_back_up_a_device_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC001']);

        $this->actingAs($stranger)->post(route('cloud-drive.backup'), ['pad_code' => 'AC001'])
            ->assertForbidden();
    }

    #[Test]
    public function checking_backup_progress_updates_the_task(): void
    {
        $user = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);
        $task = $device->tasks()->create(['type' => 'backup', 'status' => InstanceTask::STATUS_PENDING, 'result' => ['batchId' => 'B1']]);

        Http::fake(['*/queryBackupBatch' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'done']])]);

        $this->actingAs($user)->post(route('cloud-drive.backup-progress', $task))->assertSessionHas('status');

        $this->assertSame(['status' => 'done'], $task->fresh()->result['progress']);
    }

    #[Test]
    public function a_stranger_cannot_check_someone_elses_backup_progress(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC001']);
        $task = $device->tasks()->create(['type' => 'backup', 'status' => InstanceTask::STATUS_PENDING, 'result' => ['batchId' => 'B1']]);

        $this->actingAs($stranger)->post(route('cloud-drive.backup-progress', $task))->assertForbidden();
    }

    #[Test]
    public function only_an_admin_can_buy_storage(): void
    {
        $this->fakeVmosOk();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cloud-drive.buy-storage'), ['storage_id' => 1])->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('cloud-drive.buy-storage'), ['storage_id' => 1, 'auto_renew' => '1'])
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'buyStorageGoods')
            && $r['storageId'] === 1 && $r['autoRenewOrder'] === 1);
    }
}
