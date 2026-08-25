<?php

namespace Tests\Feature;

use App\Models\CloudInstance;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // --- Access control ---------------------------------------------------

    #[Test]
    public function guests_are_redirected_from_the_admin_panel(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function customers_cannot_reach_any_admin_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.devices.index'),
            route('admin.orders.index'),
            route('admin.settings.edit'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    #[Test]
    public function admins_can_load_every_admin_page(): void
    {
        $admin = $this->admin();
        Sku::factory()->create();

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.users.show', $admin),
            route('admin.devices.index'),
            route('admin.orders.index'),
            route('admin.skus.index'),
            route('admin.settings.edit', 'site'),
            route('admin.settings.edit', 'payments'),
            route('admin.settings.edit', 'vmos'),
            route('admin.settings.edit', 'mail'),
            route('admin.access-keys.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    // --- Settings ---------------------------------------------------------

    #[Test]
    public function saving_site_settings_overrides_config_at_runtime(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.settings.site'), [
                'site_name' => 'Nova Cloud',
                'support_email' => 'help@nova.test',
            ])
            ->assertRedirect();

        $this->assertSame('Nova Cloud', Setting::get('site_name'));

        // A fresh request should see the stored value applied to config.
        $this->get(route('home'))->assertOk()->assertSee('Nova Cloud', escape: false);
    }

    #[Test]
    public function secrets_are_encrypted_at_rest_and_never_rendered_back(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.settings.vmos'), [
            'vmos_access_key' => 'ak_live_visible',
            'vmos_secret_key' => 'sk_live_supersecret',
        ])->assertRedirect();

        // Stored ciphertext must not contain the plaintext.
        $raw = Setting::where('key', 'vmos_secret_key')->first()->value;
        $this->assertStringNotContainsString('sk_live_supersecret', $raw);
        $this->assertSame('sk_live_supersecret', Setting::get('vmos_secret_key'));

        // And the settings page must not echo it back into the HTML.
        $this->actingAs($admin)->get(route('admin.settings.edit', 'vmos'))
            ->assertOk()
            ->assertDontSee('sk_live_supersecret');
    }

    #[Test]
    public function submitting_a_blank_secret_keeps_the_existing_value(): void
    {
        $admin = $this->admin();
        Setting::set('vmos_secret_key', 'sk_original', true);

        $this->actingAs($admin)->patch(route('admin.settings.vmos'), [
            'vmos_base_url' => 'https://api.vmoscloud.com',
            'vmos_secret_key' => '',
        ])->assertRedirect();

        $this->assertSame('sk_original', Setting::get('vmos_secret_key'));
    }

    #[Test]
    public function the_vmos_connection_test_reports_success(): void
    {
        Setting::set('vmos_access_key', 'ak', true);
        Setting::set('vmos_secret_key', 'sk', true);

        Http::fake(['*' => Http::response(['code' => 200, 'msg' => 'success', 'data' => ['configs' => [['configId' => 1]]]])]);

        $this->actingAs($this->admin())->post(route('admin.settings.test-vmos'))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'connection OK'));
    }

    #[Test]
    public function the_vmos_connection_test_surfaces_bad_credentials(): void
    {
        Setting::set('vmos_access_key', 'ak', true);
        Setting::set('vmos_secret_key', 'sk', true);

        Http::fake(['*' => Http::response(['code' => 2031, 'msg' => 'Invalid key'])]);

        $this->actingAs($this->admin())->post(route('admin.settings.test-vmos'))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid key'));
    }

    #[Test]
    public function the_vmos_connection_test_asks_for_credentials_when_missing(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.test-vmos'))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Access Key'));
    }

    // --- Device allocation ------------------------------------------------

    #[Test]
    public function an_admin_can_allocate_a_stock_device_to_a_customer(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => null, 'pad_code' => 'AC900001']);

        $this->actingAs($admin)->post(route('admin.devices.allocate', $device), [
            'user_id' => $customer->id,
            'allocation_note' => 'Paid by bank transfer',
        ])->assertRedirect();

        $device->refresh();
        $this->assertSame($customer->id, $device->user_id);
        $this->assertSame($admin->id, $device->allocated_by);
        $this->assertNotNull($device->allocated_at);

        // And the customer now sees it on their own dashboard.
        $this->actingAs($customer)->get(route('instances.index'))->assertOk()->assertSee('AC900001');
    }

    #[Test]
    public function deallocating_returns_a_device_to_stock_and_hides_it_from_the_customer(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $customer->id, 'pad_code' => 'AC900002']);

        $this->actingAs($admin)->post(route('admin.devices.deallocate', $device))->assertRedirect();

        $this->assertNull($device->fresh()->user_id);
        $this->assertSame(0, $customer->cloudInstances()->count());

        // The customer's dashboard falls back to the empty state.
        $this->actingAs($customer)->get(route('instances.index'))
            ->assertOk()
            ->assertSee('No cloud phones yet');
    }

    #[Test]
    public function importing_pulls_devices_from_vmos_into_stock(): void
    {
        Http::fake([
            '*/vcpcloud/api/padApi/userPadList' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => [
                    ['padCode' => 'AC777001', 'equipmentId' => 1001, 'cvmStatus' => 100, 'status' => 1, 'padName' => 'V08'],
                    ['padCode' => 'AC777002', 'equipmentId' => 1002, 'cvmStatus' => 100, 'status' => 0, 'padName' => 'V06'],
                ],
            ]),
        ]);

        Setting::set('vmos_access_key', 'ak', true);

        $this->actingAs($this->admin())->post(route('admin.devices.import'))->assertRedirect();

        $this->assertDatabaseHas('cloud_instances', ['pad_code' => 'AC777001', 'user_id' => null]);
        $this->assertDatabaseHas('cloud_instances', ['pad_code' => 'AC777002', 'user_id' => null]);
    }

    #[Test]
    public function importing_does_not_duplicate_or_steal_already_allocated_devices(): void
    {
        $customer = User::factory()->create();
        CloudInstance::factory()->create(['pad_code' => 'AC777003', 'user_id' => $customer->id]);

        Http::fake([
            '*/vcpcloud/api/padApi/userPadList' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => [['padCode' => 'AC777003', 'equipmentId' => 1003, 'cvmStatus' => 100, 'status' => 1]],
            ]),
        ]);
        Setting::set('vmos_access_key', 'ak', true);

        $this->actingAs($this->admin())->post(route('admin.devices.import'))->assertRedirect();

        $this->assertSame(1, CloudInstance::where('pad_code', 'AC777003')->count());
        $this->assertSame($customer->id, CloudInstance::where('pad_code', 'AC777003')->first()->user_id);
    }

    // --- User management --------------------------------------------------

    #[Test]
    public function an_admin_can_create_and_update_a_user(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ])->assertRedirect();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_admin);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'is_admin' => '1',
        ])->assertRedirect();

        $this->assertSame('Jane Smith', $user->fresh()->name);
        $this->assertTrue($user->fresh()->is_admin);
    }

    #[Test]
    public function the_last_admin_cannot_be_demoted_or_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_admin);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    #[Test]
    public function deleting_a_user_returns_their_devices_to_stock(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $device = CloudInstance::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $customer))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('cloud_instances', ['id' => $device->id, 'user_id' => null]);
    }

    // --- Orders -----------------------------------------------------------

    #[Test]
    public function an_admin_can_mark_an_order_paid_and_provision_it(): void
    {
        Http::fake([
            '*/vcpcloud/api/padApi/createMoneyOrder' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => [['id' => 1, 'orderId' => 'VMOS-1', 'equipmentId' => 4242]],
            ]),
        ]);

        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

        $this->actingAs($admin)->post(route('admin.orders.mark-paid', $order))->assertRedirect();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);

        $this->actingAs($admin)->post(route('admin.orders.provision', $order->fresh()))->assertRedirect();
        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertDatabaseHas('cloud_instances', ['order_id' => $order->id, 'equipment_id' => 4242]);
    }

    #[Test]
    public function bulk_markup_reprices_plans_from_cost(): void
    {
        $sku = Sku::factory()->create(['vmos_cost_price' => 10.00, 'price' => 10.00]);

        $this->actingAs($this->admin())
            ->post(route('admin.skus.bulk-markup'), ['markup_percent' => 50])
            ->assertRedirect();

        $this->assertEquals(15.00, $sku->fresh()->price);
    }
}
