<?php

namespace Database\Factories;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 *
 * Minggu ditentukan dari jarak acak ke belakang supaya beberapa periode dalam
 * satu uji tidak menabrak unique(period_start, period_end).
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfWeek()->subWeeks(fake()->unique()->numberBetween(0, 500));

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::OPEN,
            'meal_allowance_percent' => 12,
            'qc_bonus_rate_per_unit' => 100,
            'total_sewing_cost' => 0,
            'calculated_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PayrollPeriodStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
