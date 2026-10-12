<?php

namespace Database\Factories;

use App\Models\Assembly;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assembly>
 */
class AssemblyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'client_id' => fn (array $attributes): int => (int) Order::findOrFail($attributes['order_id'])->client_id,
            'quantity' => fake()->numberBetween(10, 200),
            'assembled_by' => null,
            'assembled_at' => now(),
            'notes' => null,
        ];
    }
}
