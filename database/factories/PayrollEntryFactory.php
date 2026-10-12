<?php

namespace Database\Factories;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pieces = fake()->numberBetween(10, 500);
        $sewing = round($pieces * fake()->randomFloat(2, 500, 3000), 2);

        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'user_id' => User::factory(),
            'total_pieces' => $pieces,
            'sewing_amount' => $sewing,
            'meal_allowance_amount' => 0,
            'total_amount' => $sewing,
        ];
    }
}
