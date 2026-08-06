<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 5, 20);

        return [
            'user_id' => User::factory(),
            'sku_id' => Sku::factory(),
            'quantity' => 1,
            'unit_price' => $price,
            'total_price' => $price,
            'auto_renew' => true,
            'status' => Order::STATUS_PENDING_PAYMENT,
        ];
    }
}
