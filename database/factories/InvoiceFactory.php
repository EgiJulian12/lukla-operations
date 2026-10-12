<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'client_id' => Client::factory(),
            'order_id' => null,
            'status' => InvoiceStatus::DRAFT,
            'amount' => fake()->randomFloat(2, 1000000, 50000000),
            'issued_at' => null,
            'due_at' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'issued_by' => null,
            'notes' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::ISSUED,
            'issued_at' => now()->toDateString(),
        ]);
    }
}
