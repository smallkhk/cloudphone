<?php

namespace Database\Factories;

use App\Models\CloudInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudInstance>
 */
class CloudInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pad_code' => strtoupper($this->faker->bothify('AC########')),
            'auto_renew' => true,
        ];
    }
}
