<?php

namespace Database\Factories;

use App\Models\CryptoPayment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CryptoPayment>
 */
class CryptoPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'currency' => 'USDT',
            'network' => 'TRC20',
            'pay_to_address' => 'T'.$this->faker->regexify('[A-Za-z0-9]{33}'),
            'amount_crypto' => $this->faker->randomFloat(2, 5, 20),
            'amount_usd' => $this->faker->randomFloat(2, 5, 20),
            'status' => CryptoPayment::STATUS_AWAITING_PAYMENT,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
