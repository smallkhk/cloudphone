<?php

namespace Database\Factories;

use App\Models\CloudNumber;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudNumber>
 */
class CloudNumberFactory extends Factory
{
    protected $model = CloudNumber::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'sku_id' => Sku::factory(),
            'vmos_number_id' => $this->faker->numberBetween(1, 99999),
            'number' => $this->faker->numerify('4474#######'),
            'country_code' => 'GB',
            'purchase_client_token' => $this->faker->uuid(),
            'purchase_status' => CloudNumber::PURCHASE_COMPLETED,
            'auto_renew' => true,
            'row_version' => '0',
            'vmos_status' => 'normal',
            'expire_time' => now()->addDays(30),
            'delivered_at' => now(),
        ];
    }
}
