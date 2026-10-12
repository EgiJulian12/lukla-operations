<?php

namespace Database\Factories;

use App\Enums\QcResult;
use App\Models\DefectCategory;
use App\Models\Order;
use App\Models\QcRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QcRecord>
 */
class QcRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'client_id' => fn (array $attributes): int => (int) Order::findOrFail($attributes['order_id'])->client_id,
            'assembly_id' => null,
            'result' => QcResult::PASS,
            'defect_category_id' => null,
            'quantity' => fake()->numberBetween(1, 100),
            'inspected_by' => null,
            'inspected_at' => now(),
            'notes' => null,
        ];
    }

    /**
     * Hasil selain lolos wajib membawa kategori cacat (FR-014).
     */
    public function scrapped(): static
    {
        return $this->state(fn (): array => [
            'result' => QcResult::SCRAP,
            'defect_category_id' => DefectCategory::factory(),
        ]);
    }
}
