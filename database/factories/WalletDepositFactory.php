<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletDeposit>
 */
class WalletDepositFactory extends Factory
{
    protected $model = WalletDeposit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => 'USDT',
            'network' => 'TRC20',
            'pay_to_address' => 'T'.$this->faker->regexify('[A-Za-z0-9]{33}'),
            'amount_crypto' => $this->faker->randomFloat(2, 5, 100),
            'amount_usd' => $this->faker->randomFloat(2, 5, 100),
            'status' => WalletDeposit::STATUS_AWAITING_PAYMENT,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
