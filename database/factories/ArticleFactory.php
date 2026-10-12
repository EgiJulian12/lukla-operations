<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'code' => fake()->unique()->bothify('ART-####'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }
}
