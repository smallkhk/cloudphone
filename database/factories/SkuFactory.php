<?php

namespace Database\Factories;

use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sku>
 */
class SkuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vmos_config_id' => $this->faker->unique()->numberBetween(1, 1000),
            'vmos_good_id' => $this->faker->unique()->numberBetween(1, 1000),
            'android_version' => $this->faker->randomElement(['13', '14']),
            'name' => $this->faker->words(3, true),
            'config_model' => $this->faker->word(),
            'duration_label' => '1 month',
            'duration_minutes' => 43200,
            'vmos_cost_price' => $this->faker->randomFloat(2, 3, 15),
            'price' => $this->faker->randomFloat(2, 5, 20),
            'active' => true,
            'sell_out' => false,
        ];
    }
}
