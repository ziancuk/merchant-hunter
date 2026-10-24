<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'weight' => fake()->numberBetween(40, 95),
            'whatsapp_template' => 'Halo {nama_bisnis}, saya {nama_pengirim}. Kategori {kategori}.',
            'osm_filters' => ['amenity' => ['cafe']],
            'google_types' => ['cafe'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
