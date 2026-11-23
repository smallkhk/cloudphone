<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessKeyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function old_desktop_builds_open_with_any_code(): void
    {
        $this->postJson('/api/access-keys/verify', ['code' => 'ANYTHING'])->assertOk()->assertJson(['valid' => true]);
        $this->postJson('/api/access-keys/verify', [])->assertOk()->assertJson(['valid' => true]);
    }

    #[Test]
    public function the_admin_access_codes_page_is_gone(): void
    {
        $this->assertFalse(Route::has('admin.access-keys.index'));
    }
}
