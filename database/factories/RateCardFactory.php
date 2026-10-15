<?php

namespace Database\Factories;

use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Models\Client;
use App\Models\Component;
use App\Models\RateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCard>
 *
 * Tarif default tanpa artikel/komponen: berlaku sebagai tarif dasar klien.
 * Secara default berstatus APPROVED agar pengujian kalkulasi payroll berjalan lancar.
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
            'status' => RateCardStatus::APPROVED,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => now(),
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

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => RateCardStatus::PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'status' => RateCardStatus::REJECTED,
            'notes' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }
}
