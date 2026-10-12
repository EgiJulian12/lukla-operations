<?php

namespace Database\Factories;

use App\Enums\OutputStatus;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutputEntry>
 *
 * Pesanan diturunkan dari klien yang sama, dan penjahit serta komponen dibuat
 * baru setiap kali, sehingga factory tidak menabrak unique(user_id,
 * component_id, interval_start) milik entry lain.
 */
class OutputEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMinutes(30);

        return [
            'order_id' => Order::factory(),
            'client_id' => fn (array $attributes): int => (int) Order::findOrFail($attributes['order_id'])->client_id,
            'production_line_id' => ProductionLine::factory(),
            'user_id' => User::factory(),
            'component_id' => Component::factory(),
            'quantity' => fake()->numberBetween(5, 60),
            'interval_start' => $start,
            'interval_end' => $start->copy()->addMinutes(30),
            'status' => OutputStatus::SUBMITTED,
            'verified_by' => null,
            'verified_at' => null,
            'notes' => null,
        ];
    }

    public function verified(?User $by = null): static
    {
        return $this->state(fn (): array => [
            'status' => OutputStatus::VERIFIED,
            'verified_by' => $by?->getKey() ?? User::factory(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => OutputStatus::REJECTED]);
    }
}
