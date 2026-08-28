<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 5, 100);

        return [
            'user_id' => User::factory(),
            'type' => WalletTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'balance_after' => $amount,
            'description' => $this->faker->sentence(3),
        ];
    }
}
