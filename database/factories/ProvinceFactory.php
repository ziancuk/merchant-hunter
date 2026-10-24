<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Province> */
class ProvinceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => fake()->unique()->state(),
            'capital_city' => fake()->city(),
            'lat' => fake()->latitude(-8, 5),
            'lng' => fake()->longitude(96, 140),
        ];
    }
}
