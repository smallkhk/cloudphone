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
            'source' => CustomerProxy::SOURCE_VMOS,
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

    /** A manually-added proxy — no order, no sku, no VMOS purchase. */
    public function custom(): static
    {
        return $this->state(fn () => [
            'order_id' => null,
            'sku_id' => null,
            'source' => CustomerProxy::SOURCE_CUSTOM,
            'vmos_proxy_id' => null,
            'account' => $this->faker->userName(),
            'password' => $this->faker->password(),
            'purchase_client_token' => null,
        ]);
    }
}
