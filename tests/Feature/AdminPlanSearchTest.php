<?php

namespace Tests\Feature;

use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPlanSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    #[Test]
    public function it_paginates_the_catalogue_instead_of_rendering_every_plan(): void
    {
        Sku::factory()->count(60)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.skus.index'));

        $response->assertOk();
        $this->assertCount(40, $response->viewData('skus')->items());
        $this->assertSame(60, $response->viewData('skus')->total());
    }

    #[Test]
    public function it_searches_by_name_model_and_duration(): void
    {
        Sku::factory()->create(['name' => 'Galaxy S23', 'config_model' => 'sm-s911', 'duration_label' => '30 days']);
        Sku::factory()->create(['name' => 'Pixel 8', 'config_model' => 'gp-8', 'duration_label' => '7 days']);

        $byName = $this->actingAs($this->admin)->get(route('admin.skus.index', ['q' => 'galaxy']));
        $this->assertSame(1, $byName->viewData('skus')->total());

        $byModel = $this->actingAs($this->admin)->get(route('admin.skus.index', ['q' => 'gp-8']));
        $this->assertSame(1, $byModel->viewData('skus')->total());

        $byDuration = $this->actingAs($this->admin)->get(route('admin.skus.index', ['q' => '7 days']));
        $this->assertSame(1, $byDuration->viewData('skus')->total());
    }

    #[Test]
    public function it_filters_by_android_version_and_status(): void
    {
        Sku::factory()->create(['android_version' => '13', 'active' => true]);
        Sku::factory()->create(['android_version' => '14', 'active' => false]);

        $this->assertSame(1, $this->actingAs($this->admin)
            ->get(route('admin.skus.index', ['android' => '14']))->viewData('skus')->total());

        $this->assertSame(1, $this->actingAs($this->admin)
            ->get(route('admin.skus.index', ['status' => 'hidden']))->viewData('skus')->total());
    }

    #[Test]
    public function it_bulk_hides_only_the_plans_matching_the_current_filter(): void
    {
        $galaxy = Sku::factory()->create(['name' => 'Galaxy S23', 'active' => true]);
        $pixel = Sku::factory()->create(['name' => 'Pixel 8', 'active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.skus.bulk-status'), ['active' => 0, 'q' => 'galaxy'])
            ->assertRedirect();

        $this->assertFalse($galaxy->fresh()->active);
        $this->assertTrue($pixel->fresh()->active, 'A plan outside the filter must not be touched.');
    }

    #[Test]
    public function bulk_markup_can_be_scoped_to_the_current_filter(): void
    {
        $galaxy = Sku::factory()->create(['name' => 'Galaxy S23', 'vmos_cost_price' => 10, 'price' => 11]);
        $pixel = Sku::factory()->create(['name' => 'Pixel 8', 'vmos_cost_price' => 10, 'price' => 11]);

        $this->actingAs($this->admin)->post(route('admin.skus.bulk-markup'), [
            'markup_percent' => 50,
            'scoped' => 1,
            'q' => 'galaxy',
        ])->assertRedirect();

        $this->assertSame('15.00', $galaxy->fresh()->price);
        $this->assertSame('11.00', $pixel->fresh()->price);
    }
}
