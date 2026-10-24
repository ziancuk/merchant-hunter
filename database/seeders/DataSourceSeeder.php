<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        // Defaults per TRD §4: osm active, google off until a key is set, mock off for production sync.
        $rows = [
            ['key' => 'osm', 'name' => 'OpenStreetMap (Overpass)', 'is_active' => true, 'priority' => 10,
                'monthly_quota' => null, 'cost_search_per_1000' => 0, 'cost_details_per_1000' => 0],
            ['key' => 'google', 'name' => 'Google Places', 'is_active' => false, 'priority' => 5,
                'monthly_quota' => 5000, 'cost_search_per_1000' => null, 'cost_details_per_1000' => null],
            ['key' => 'mock', 'name' => 'Simulasi (MockProvider)', 'is_active' => false, 'priority' => 90,
                'monthly_quota' => null, 'cost_search_per_1000' => 0, 'cost_details_per_1000' => 0],
        ];
        foreach ($rows as $row) {
            DataSource::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
