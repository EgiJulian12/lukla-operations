<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Bahan generik/pasaran secara default — client_id null berarti bahan
     * dipakai lintas klien, sesuai arti kolomnya di tabel materials.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'code' => fake()->unique()->bothify('MAT-####'),
            'name' => fake()->randomElement(['Katun Combed 30s', 'Polyester', 'Denim', 'Drill', 'Rib']),
            'uom' => 'meter',
            'category' => fake()->randomElement(['Kain Utama', 'Aksesoris', 'Benang']),
        ];
    }

    /**
     * Bahan titipan milik klien tertentu (skema CMT / Semi-CMT).
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => ['client_id' => $client->getKey()]);
    }
}
