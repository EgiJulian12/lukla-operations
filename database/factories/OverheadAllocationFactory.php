<?php

namespace Database\Factories;

use App\Models\OverheadAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverheadAllocation>
 *
 * Bulan ditentukan dari jarak acak ke belakang supaya tidak menabrak
 * unique(period_month).
 */
class OverheadAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $overhead = fake()->randomFloat(2, 1000000, 10000000);
        $units = fake()->numberBetween(100, 10000);

        return [
            'period_month' => now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(0, 240))->toDateString(),
            'total_overhead' => $overhead,
            'total_units' => $units,
            'overhead_per_unit' => round($overhead / $units, 4),
        ];
    }
}
