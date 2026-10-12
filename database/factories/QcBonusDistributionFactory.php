<?php

namespace Database\Factories;

use App\Models\QcBonusDistribution;
use App\Models\QcBonusPool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QcBonusDistribution>
 */
class QcBonusDistributionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qc_bonus_pool_id' => QcBonusPool::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
