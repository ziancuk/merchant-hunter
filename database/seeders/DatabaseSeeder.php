<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            CategorySeeder::class,
            CategoryTemplateSeeder::class,
            RejectionReasonSeeder::class,
            DataSourceSeeder::class,
            DemoUserSeeder::class,
            OutletSeeder::class,
        ]);
    }
}
