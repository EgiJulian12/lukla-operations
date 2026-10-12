<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po_number' => 'PO-'.fake()->unique()->numerify('######'),
            'supplier' => fake()->company(),
            'status' => PurchaseOrderStatus::DRAFT,
            'ordered_at' => now()->toDateString(),
            'expected_at' => fake()->dateTimeBetween('+3 days', '+3 weeks')->format('Y-m-d'),
            'approved_by' => null,
            'approved_at' => null,
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
