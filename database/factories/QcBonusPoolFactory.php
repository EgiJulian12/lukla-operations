<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use App\Models\QcBonusPool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QcBonusPool>
 */
class QcBonusPoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = fake()->numberBetween(100, 5000);
        $rate = 100.0;
        $pool = $units * $rate;

        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'total_passed_units' => $units,
            'rate_per_unit' => $rate,
            'total_pool' => $pool,
            'recipient_count' => 3,
            'amount_per_recipient' => round($pool / 3, 2),
        ];
    }
}
