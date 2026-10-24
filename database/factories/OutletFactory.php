<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Outlet> */
class OutletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'OUT-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'city' => fake()->city(),
            'province_id' => Province::factory(),
            'lat' => -6.2 + fake()->randomFloat(5, -0.05, 0.05),
            'lng' => 106.8 + fake()->randomFloat(5, -0.05, 0.05),
            'geocode_source' => 'seed',
            'geocode_status' => 'ok',
            'is_active' => true,
            'is_simulated' => true,
        ];
    }
}
