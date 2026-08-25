<?php

namespace Database\Factories;

use App\Models\AccessKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessKeyFactory extends Factory
{
    protected $model = AccessKey::class;

    public function definition(): array
    {
        return [
            'code' => AccessKey::generateCode(),
            'label' => $this->faker->words(2, true),
            'is_active' => true,
            'expires_at' => null,
            'used_count' => 0,
        ];
    }
}
