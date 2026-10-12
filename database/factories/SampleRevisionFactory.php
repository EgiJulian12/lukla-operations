<?php

namespace Database\Factories;

use App\Enums\SampleStatus;
use App\Models\Sample;
use App\Models\SampleRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleRevision>
 *
 * client_id diturunkan dari sample induk karena FK komposit
 * sample_revisions(sample_id, client_id) menolak ketidakcocokan di level DB.
 */
class SampleRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'client_id' => fn (array $attributes): int => (int) Sample::findOrFail($attributes['sample_id'])->client_id,
            'version' => 1,
            'specs' => ['bahan' => fake()->word(), 'warna' => fake()->safeColorName()],
            'material_usage' => ['kain' => fake()->randomFloat(2, 0.5, 3)],
            'status' => SampleStatus::DRAFT,
            'notes' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => SampleStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
