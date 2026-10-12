<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\SampleRevision;
use App\Models\TimeStudy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeStudy>
 */
class TimeStudyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_revision_id' => SampleRevision::factory(),
            'client_id' => fn (array $attributes): int => (int) SampleRevision::findOrFail($attributes['sample_revision_id'])->client_id,
            'component_id' => Component::factory(),
            'duration_seconds' => fake()->numberBetween(30, 900),
            'recorded_by' => null,
            'recorded_at' => now(),
            'notes' => null,
        ];
    }
}
