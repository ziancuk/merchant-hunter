<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prospect> */
class ProspectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'mock',
            'place_id' => 'mock/'.fake()->unique()->numerify('########'),
            'name' => fake()->company(),
            'category_id' => Category::factory(),
            'lat' => -6.2 + fake()->randomFloat(5, -0.05, 0.05),
            'lng' => 106.8 + fake()->randomFloat(5, -0.05, 0.05),
            'is_simulated' => true,
            'score' => fake()->numberBetween(10, 95),
            'first_seen_at' => now()->subDays(5),
            'last_seen_at' => now(),
            'fetched_at' => now(),
            'lifecycle_status' => 'open',
            'pipeline_status' => 'new',
        ];
    }
}
