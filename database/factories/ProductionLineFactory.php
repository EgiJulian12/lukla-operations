<?php

namespace Database\Factories;

use App\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLine>
 */
class ProductionLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('LINE-##'),
            'name' => 'Line '.fake()->bothify('##'),
            'capacity' => fake()->numberBetween(5, 15),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
