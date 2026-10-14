<?php

namespace Database\Factories;

use App\Enums\OrderMaterialStatus;
use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'sample_id' => null,
            'sample_revision_id' => null,
            'order_number' => fake()->unique()->bothify('ORD-####'),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => fake()->numberBetween(50, 500),
            'status' => OrderStatus::CONFIRMED,
            'warehouse_material_status' => OrderMaterialStatus::EMPTY,
            'due_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function inProduction(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::IN_PRODUCTION]);
    }
}
