<?php

namespace Tests\Feature;

use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSku(string $name, array $overrides = []): Sku
    {
        return Sku::factory()->create(array_merge([
            'name' => $name,
            'android_version' => '13',
            'active' => true,
            'sell_out' => false,
            'price' => 9.99,
        ], $overrides));
    }

    #[Test]
    public function it_groups_durations_of_the_same_device_into_one_section(): void
    {
        $this->makeSku('Galaxy S23', ['duration_label' => '7 days', 'duration_minutes' => 10080, 'price' => 4.99]);
        $this->makeSku('Galaxy S23', ['duration_label' => '30 days', 'duration_minutes' => 43200, 'price' => 14.99]);
        $this->makeSku('Pixel 8', ['duration_label' => '30 days', 'duration_minutes' => 43200, 'price' => 19.99]);

        $response = $this->get(route('plans.index'));

        $response->assertOk();
        // Two device families, three plans.
        $response->assertSee('2 devices available');
        $response->assertSee('Galaxy S23');
        $response->assertSee('Pixel 8');
        // The group header shows the cheapest duration as the "from" price.
        $response->assertSee('$4.99');
    }

    #[Test]
    public function the_same_duration_offered_on_two_android_versions_only_shows_once(): void
    {
        // Same device name, same duration/price, sold under two Android
        // versions — without an Android filter picked this used to render
        // as two identical-looking duration buttons.
        $this->makeSku('Galaxy S21+ 5G', ['android_version' => '13', 'duration_label' => '1 day', 'duration_minutes' => 1440, 'price' => 1.30]);
        $this->makeSku('Galaxy S21+ 5G', ['android_version' => '14', 'duration_label' => '1 day', 'duration_minutes' => 1440, 'price' => 1.30]);

        $response = $this->get(route('plans.index'));

        $response->assertOk();
        // The price only ever renders on the per-duration button, so counting
        // it (rather than the duration label, which also appears once in the
        // unrelated "Any duration" search dropdown) proves there's only one
        // button instead of two.
        $body = $response->getContent();
        $this->assertSame(1, substr_count($body, '$1.30'));
    }

    #[Test]
    public function it_filters_devices_by_search_term(): void
    {
        $this->makeSku('Galaxy S23');
        $this->makeSku('Pixel 8');

        $response = $this->get(route('plans.index', ['q' => 'pixel']));

        $response->assertOk();
        $response->assertSee('Pixel 8');
        $response->assertDontSee('Galaxy S23');
    }

    #[Test]
    public function it_filters_devices_by_android_version(): void
    {
        $this->makeSku('Galaxy S23', ['android_version' => '13']);
        $this->makeSku('Pixel 8', ['android_version' => '14']);

        $response = $this->get(route('plans.index', ['android' => '14']));

        $response->assertOk();
        $response->assertSee('Pixel 8');
        $response->assertDontSee('Galaxy S23');
    }

    #[Test]
    public function it_paginates_device_families_rather_than_plans(): void
    {
        // 20 devices × 3 durations — the old page rendered all 60 cards at once.
        foreach (range(1, 20) as $i) {
            foreach ([10080, 43200, 129600] as $minutes) {
                $this->makeSku("Device {$i}", ['duration_minutes' => $minutes]);
            }
        }

        $response = $this->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('20 devices available');
        $response->assertSee('showing 1–12');
        $this->get(route('plans.index', ['page' => 2]))->assertOk()->assertSee('showing 13–20');
    }

    #[Test]
    public function it_tells_the_visitor_when_a_search_matched_nothing(): void
    {
        $this->makeSku('Galaxy S23');

        $this->get(route('plans.index', ['q' => 'nokia']))
            ->assertOk()
            ->assertSee('Nothing matched');
    }

    #[Test]
    public function it_hides_inactive_sold_out_and_unpriced_plans(): void
    {
        $this->makeSku('Hidden device', ['active' => false]);
        $this->makeSku('Sold out device', ['sell_out' => true]);
        $this->makeSku('Free device', ['price' => 0]);
        $this->makeSku('Real device');

        $response = $this->get(route('plans.index'));

        $response->assertSee('Real device');
        $response->assertDontSee('Hidden device');
        $response->assertDontSee('Sold out device');
        $response->assertDontSee('Free device');
    }
}
