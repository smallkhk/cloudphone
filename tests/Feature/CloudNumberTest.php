<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\CloudNumber;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Services\Provisioning\OrderProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudNumberTest extends TestCase
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
        Sku::factory()->create(['type' => Sku::TYPE_CLOUD_NUMBER, 'default_country_code' => 'GB', 'price' => 12.99]);

        $this->get(route('cloud-numbers.index'))->assertOk()->assertSee('Log in to buy');
    }

    #[Test]
    public function a_customer_can_buy_a_cloud_number_and_it_completes(): void
    {
        Http::fake([
            '*/cloudNumber/purchase' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
                'status' => 'COMPLETED', 'terminal' => true, 'orderNo' => 'VMOS-CLOUD1', 'numbers' => ['447449641145'],
            ]]),
            '*/cloudNumber/list' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['records' => [
                ['id' => 40, 'number' => '447449641145', 'autoRenew' => 1, 'rowVersion' => '0', 'status' => 'normal'],
            ]]]),
        ]);

        $user = User::factory()->create();
        $sku = Sku::factory()->create(['type' => Sku::TYPE_CLOUD_NUMBER, 'default_country_code' => 'GB', 'price' => 12.99]);

        $this->actingAs($user)->post(route('orders.store'), [
            'sku_id' => $sku->id, 'quantity' => 1,
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame(Sku::TYPE_CLOUD_NUMBER, $order->sku->type);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);

        // Simulate payment confirming (same as crypto:verify-payments would trigger).
        $order->update(['status' => Order::STATUS_PAID]);
        app(OrderProvisioner::class)->provision($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(1, CloudNumber::where('order_id', $order->id)->count());
    }

    #[Test]
    public function binding_requires_explicit_restart_acknowledgement(): void
    {
        $user = User::factory()->create();
        $instance = CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);
        $number = CloudNumber::factory()->create(['user_id' => $user->id, 'vmos_number_id' => 40]);

        $this->actingAs($user)
            ->post(route('cloud-numbers.bind', $number), ['pad_code' => 'AC001'])
            ->assertSessionHasErrors('restart_acknowledged');
    }

    #[Test]
    public function binding_to_a_device_you_own_starts_the_bind_task(): void
    {
        Http::fake(['*/cloudNumber/bind' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['state' => 'RUNNING', 'terminal' => false]])]);

        $user = User::factory()->create();
        $instance = CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC001']);
        $number = CloudNumber::factory()->create(['user_id' => $user->id, 'vmos_number_id' => 40]);

        $this->actingAs($user)
            ->post(route('cloud-numbers.bind', $number), ['pad_code' => 'AC001', 'restart_acknowledged' => '1'])
            ->assertSessionHas('status');

        $this->assertSame('AC001', $number->fresh()->bound_pad_code);
        $this->assertSame('RUNNING', $number->fresh()->bind_state);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'cloudNumber/bind')
            && $r['numberId'] === 40 && $r['padCode'] === 'AC001' && $r['restartAcknowledged'] === true);
    }

    #[Test]
    public function a_customer_cannot_bind_a_device_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $owner->id, 'pad_code' => 'AC001']);
        $number = CloudNumber::factory()->create(['user_id' => $intruder->id, 'vmos_number_id' => 40]);

        $this->actingAs($intruder)
            ->post(route('cloud-numbers.bind', $number), ['pad_code' => 'AC001', 'restart_acknowledged' => '1'])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_cannot_bind_someone_elses_number(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        CloudInstance::factory()->create(['user_id' => $intruder->id, 'pad_code' => 'AC001']);
        $number = CloudNumber::factory()->create(['user_id' => $owner->id, 'vmos_number_id' => 40]);

        $this->actingAs($intruder)
            ->post(route('cloud-numbers.bind', $number), ['pad_code' => 'AC001', 'restart_acknowledged' => '1'])
            ->assertForbidden();
    }

    #[Test]
    public function toggling_auto_renew_flips_it_and_updates_the_row_version(): void
    {
        Http::fake(['*/cloudNumber/autoRenew' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'status' => 'SAVED', 'item' => ['id' => 40, 'rowVersion' => '1'],
        ]])]);

        $user = User::factory()->create();
        $number = CloudNumber::factory()->create(['user_id' => $user->id, 'vmos_number_id' => 40, 'auto_renew' => true, 'row_version' => '0']);

        $this->actingAs($user)->post(route('cloud-numbers.auto-renew', $number))->assertSessionHas('status');

        $number->refresh();
        $this->assertFalse($number->auto_renew);
        $this->assertSame('1', $number->row_version);
    }

    #[Test]
    public function releasing_a_number_calls_vmos_and_marks_it_disabled(): void
    {
        Http::fake(['*/cloudNumber/release' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['status' => 'ACCEPTED']])]);

        $user = User::factory()->create();
        $number = CloudNumber::factory()->create(['user_id' => $user->id, 'number' => '447449641145']);

        $this->actingAs($user)->post(route('cloud-numbers.release', $number))->assertSessionHas('status');

        $this->assertSame('disabled', $number->fresh()->vmos_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'cloudNumber/release') && $r['number'] === '447449641145');
    }

    #[Test]
    public function checking_for_sms_stores_the_messages(): void
    {
        Http::fake(['*/cloudNumber/sms/list' => Http::response(['code' => 200, 'msg' => 'success', 'data' => [
            'total' => 1, 'records' => [
                ['id' => '23', 'sender' => '12139801374', 'content' => 'y1z6dx', 'receivedTime' => '2026-09-11 11:54:13'],
            ],
        ]])]);

        $user = User::factory()->create();
        $number = CloudNumber::factory()->create(['user_id' => $user->id, 'number' => '12139807035']);

        $this->actingAs($user)->post(route('cloud-numbers.sms', $number))->assertSessionHas('status');

        $this->assertNotEmpty($number->fresh()->raw_payload['sms']);
    }

    #[Test]
    public function a_stranger_cannot_manage_someone_elses_number(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $number = CloudNumber::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->post(route('cloud-numbers.release', $number))->assertForbidden();
        $this->actingAs($stranger)->post(route('cloud-numbers.auto-renew', $number))->assertForbidden();
        $this->actingAs($stranger)->post(route('cloud-numbers.sms', $number))->assertForbidden();
    }
}
