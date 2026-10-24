<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private const TEMPLATE = 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kami membantu bisnis {kategori} mengelola kasir dan stok dengan lebih mudah. Apakah boleh saya kirim info singkat dan jadwalkan demo gratis? Bila tidak berminat, balas STOP dan kami tidak akan menghubungi lagi.';

    /** @return list<array{key: string, name: string, weight: int, osm: array<string, list<string>>, google: list<string>}> */
    public static function rows(): array
    {
        return [
            ['key' => 'restaurant', 'name' => 'Restoran', 'weight' => 85, 'osm' => ['amenity' => ['restaurant', 'food_court']], 'google' => ['restaurant']],
            ['key' => 'cafe', 'name' => 'Kafe', 'weight' => 80, 'osm' => ['amenity' => ['cafe']], 'google' => ['cafe', 'coffee_shop']],
            ['key' => 'fast_food', 'name' => 'Makanan cepat saji', 'weight' => 75, 'osm' => ['amenity' => ['fast_food', 'ice_cream']], 'google' => ['fast_food_restaurant']],
            ['key' => 'bakery', 'name' => 'Toko roti', 'weight' => 70, 'osm' => ['shop' => ['bakery']], 'google' => ['bakery']],
            ['key' => 'minimarket', 'name' => 'Minimarket/kelontong', 'weight' => 90, 'osm' => ['shop' => ['convenience', 'supermarket', 'general', 'kiosk']], 'google' => ['convenience_store', 'supermarket']],
            ['key' => 'fashion', 'name' => 'Pakaian & fashion', 'weight' => 65, 'osm' => ['shop' => ['clothes', 'shoes', 'bag', 'jewelry']], 'google' => ['clothing_store', 'shoe_store']],
            ['key' => 'pharmacy', 'name' => 'Apotek', 'weight' => 75, 'osm' => ['amenity' => ['pharmacy']], 'google' => ['pharmacy']],
            ['key' => 'beauty', 'name' => 'Salon & kecantikan', 'weight' => 70, 'osm' => ['shop' => ['hairdresser', 'beauty', 'cosmetics']], 'google' => ['hair_salon', 'beauty_salon']],
            ['key' => 'laundry', 'name' => 'Laundry', 'weight' => 55, 'osm' => ['shop' => ['laundry', 'dry_cleaning']], 'google' => ['laundry']],
            ['key' => 'electronics', 'name' => 'Elektronik & ponsel', 'weight' => 70, 'osm' => ['shop' => ['electronics', 'mobile_phone', 'computer']], 'google' => ['electronics_store']],
            ['key' => 'automotive', 'name' => 'Bengkel', 'weight' => 50, 'osm' => ['shop' => ['car_repair', 'motorcycle_repair']], 'google' => ['car_repair']],
            ['key' => 'lodging', 'name' => 'Hotel & penginapan', 'weight' => 60, 'osm' => ['tourism' => ['hotel', 'guest_house', 'hostel', 'motel']], 'google' => ['lodging']],
        ];
    }

    public function run(): void
    {
        foreach (self::rows() as $i => $row) {
            // Weights and templates are editable in Master Kategori: do not overwrite existing rows.
            Category::firstOrCreate(['key' => $row['key']], [
                'name' => $row['name'],
                'weight' => $row['weight'],
                'whatsapp_template' => self::TEMPLATE,
                'osm_filters' => $row['osm'],
                'google_types' => $row['google'],
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }
}
