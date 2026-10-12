<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CLI-####'),
            'name' => fake()->company(),
            'type' => ClientType::MAKLON,
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->bothify('client-####@lukla.test'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    /**
     * Brand internal Snøhund — klien biasa dengan type = internal, bukan kasus khusus.
     */
    public function internal(): static
    {
        return $this->state(fn (): array => [
            'type' => ClientType::INTERNAL,
            'name' => 'Snøhund',
        ]);
    }
}
