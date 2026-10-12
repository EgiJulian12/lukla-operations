<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'code' => fake()->unique()->bothify('CMP-####'),
            'name' => fake()->randomElement(['Badan Depan', 'Lengan', 'Kerah', 'Saku', 'Manset']),
            'sequence' => fake()->numberBetween(1, 5),
        ];
    }
}
