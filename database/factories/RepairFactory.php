<?php

namespace Database\Factories;

use App\Enums\RepairStatus;
use App\Models\QcRecord;
use App\Models\Repair;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repair>
 */
class RepairFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qc_record_id' => QcRecord::factory(),
            'assigned_repairer_id' => null,
            'status' => RepairStatus::PENDING,
            'completed_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function inRepair(): static
    {
        return $this->state(fn (): array => [
            'assigned_repairer_id' => User::factory(),
            'status' => RepairStatus::IN_REPAIR,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'assigned_repairer_id' => User::factory(),
            'status' => RepairStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
