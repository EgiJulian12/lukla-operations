<?php

namespace Database\Factories;

use App\Enums\SampleStatus;
use App\Models\Article;
use App\Models\Client;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sample>
 *
 * Artikel diturunkan dari klien yang sama, supaya sample tidak pernah mengacu
 * ke artikel milik klien lain.
 */
class SampleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'article_id' => fn (array $attributes): int => Article::factory()
                ->create(['client_id' => $attributes['client_id']])
                ->getKey(),
            'code' => fake()->unique()->bothify('SMP-####'),
            'title' => fake()->words(3, true),
            'status' => SampleStatus::DRAFT,
            'current_revision_id' => null,
            'approved_revision_id' => null,
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => SampleStatus::APPROVED]);
    }
}
