<?php

namespace Database\Factories;

use App\Models\DefectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectCategory>
 */
class DefectCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DEF-####'),
            'name' => fake()->randomElement(['Jahitan Loncat', 'Benang Putus', 'Noda', 'Ukuran Meleset']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
