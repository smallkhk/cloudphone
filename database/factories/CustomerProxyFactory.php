<?php

namespace Database\Factories;

use App\Models\CustomerProxy;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProxy>
 */
class CustomerProxyFactory extends Factory
{
    protected $model = CustomerProxy::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'sku_id' => Sku::factory(),
            'vmos_proxy_id' => $this->faker->numberBetween(1, 99999),
            'host' => $this->faker->ipv4(),
            'port' => $this->faker->numberBetween(1024, 65535),
            'account' => $this->faker->userName(),
            'country_code' => 'US',
            'purchase_client_token' => $this->faker->uuid(),
            'purchase_status' => CustomerProxy::PURCHASE_COMPLETED,
            'delivered_at' => now(),
        ];
    }
}
