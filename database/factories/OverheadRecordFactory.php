<?php

namespace Database\Factories;

use App\Models\OverheadRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverheadRecord>
 */
class OverheadRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_month' => now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(0, 240))->toDateString(),
            'category' => fake()->randomElement(['Listrik', 'Air', 'Internet', 'Operasional']),
            'amount' => fake()->randomFloat(2, 500000, 5000000),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
