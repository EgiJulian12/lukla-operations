<?php

namespace Database\Factories;

use App\Models\LineAssignment;
use App\Models\ProductionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineAssignment>
 */
class LineAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_line_id' => ProductionLine::factory(),
            'user_id' => User::factory(),
            'assigned_at' => now(),
            'unassigned_at' => null,
        ];
    }

    /**
     * Penugasan yang sudah ditutup. Ditutup dengan mengisi unassigned_at,
     * bukan dihapus, agar riwayat alokasi tetap dapat diaudit.
     */
    public function ended(): static
    {
        return $this->state(fn (): array => ['unassigned_at' => now()]);
    }
}
