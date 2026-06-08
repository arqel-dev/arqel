<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'ORD-'.$this->faker->unique()->numberBetween(1000, 9999),
            'customer_name' => $this->faker->name(),
            'total' => $this->faker->randomFloat(2, 10, 999),
            'state' => $this->faker->randomElement(['pending', 'paid', 'shipped', 'delivered']),
        ];
    }
}
