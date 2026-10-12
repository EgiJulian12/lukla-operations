<?php

namespace Database\Factories;

use App\Enums\RateType;
use App\Models\Client;
use App\Models\Component;
use App\Models\RateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCard>
 *
 * Tarif default tanpa artikel/komponen: berlaku sebagai tarif dasar klien.
 * Uji yang butuh tarif spesifik komponen menimpanya lewat create().
 */
class RateCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'article_id' => null,
            'component_id' => null,
            'rate_type' => RateType::SEWING,
            'rate' => fake()->randomFloat(2, 500, 5000),
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'created_by' => null,
            'notes' => null,
        ];
    }

    public function forComponent(Component $component): static
    {
        return $this->state(fn (): array => [
            'component_id' => $component->getKey(),
            'article_id' => $component->article_id,
        ]);
    }

    public function cutting(): static
    {
        return $this->state(fn (): array => ['rate_type' => RateType::CUTTING]);
    }

    public function effectiveFrom(string $date): static
    {
        return $this->state(fn (): array => ['effective_from' => $date]);
    }
}
