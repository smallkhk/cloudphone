<?php

namespace Tests\Feature;

use App\Models\AccessKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    #[Test]
    public function an_admin_can_generate_an_access_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.access-keys.store'), ['label' => "Jane's laptop"])
            ->assertRedirect();

        $key = AccessKey::first();
        $this->assertSame("Jane's laptop", $key->label);
        $this->assertTrue($key->is_active);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/', $key->code);
    }

    #[Test]
    public function a_customer_cannot_manage_access_codes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.access-keys.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.access-keys.store'), [])->assertForbidden();
    }

    #[Test]
    public function an_admin_can_revoke_and_reenable_a_code(): void
    {
        $key = AccessKey::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.access-keys.toggle', $key))->assertRedirect();
        $this->assertFalse($key->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.access-keys.toggle', $key))->assertRedirect();
        $this->assertTrue($key->fresh()->is_active);
    }

    #[Test]
    public function an_admin_can_delete_a_code(): void
    {
        $key = AccessKey::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.access-keys.destroy', $key))->assertRedirect();

        $this->assertDatabaseMissing('access_keys', ['id' => $key->id]);
    }

    // --- Desktop app verification endpoint ---------------------------------

    #[Test]
    public function the_verify_endpoint_accepts_a_valid_active_code(): void
    {
        $key = AccessKey::factory()->create(['is_active' => true]);

        $this->postJson('/api/access-keys/verify', ['code' => $key->code])
            ->assertOk()
            ->assertJson(['valid' => true]);

        $this->assertSame(1, $key->fresh()->used_count);
        $this->assertNotNull($key->fresh()->last_used_at);
    }

    #[Test]
    public function the_verify_endpoint_is_case_and_whitespace_insensitive(): void
    {
        $key = AccessKey::factory()->create(['is_active' => true]);

        $this->postJson('/api/access-keys/verify', ['code' => ' '.strtolower($key->code).' '])
            ->assertOk()
            ->assertJson(['valid' => true]);
    }

    #[Test]
    public function the_verify_endpoint_rejects_a_revoked_code(): void
    {
        $key = AccessKey::factory()->create(['is_active' => false]);

        $this->postJson('/api/access-keys/verify', ['code' => $key->code])
            ->assertStatus(401)
            ->assertJson(['valid' => false]);
    }

    #[Test]
    public function the_verify_endpoint_rejects_an_expired_code(): void
    {
        $key = AccessKey::factory()->create(['is_active' => true, 'expires_at' => now()->subDay()]);

        $this->postJson('/api/access-keys/verify', ['code' => $key->code])
            ->assertStatus(401)
            ->assertJson(['valid' => false]);
    }

    #[Test]
    public function the_verify_endpoint_rejects_an_unknown_code(): void
    {
        $this->postJson('/api/access-keys/verify', ['code' => 'NOPE-NOPE-NOPE-NOPE'])
            ->assertStatus(401)
            ->assertJson(['valid' => false]);
    }
}
