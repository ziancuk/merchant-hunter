<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * 38 provinces x 5 fictional outlets = 190 dummy Hitachi merchants (OUT-001..OUT-190) around each
 * provincial capital. Fully deterministic and idempotent (updateOrCreate by code).
 */
class OutletSeeder extends Seeder
{
    public const PER_PROVINCE = 5;

    /** Fixed (east km, north km) offsets from the capital centre; all within 6 km. */
    private const OFFSETS_KM = [[0.0, 0.0], [2.6, 1.4], [-3.1, 2.2], [1.8, -3.4], [-2.4, -2.9]];

    private const TYPES = [
        'Toko %s', 'Warung Bu %s', 'Apotek %s', 'Kedai %s', 'Bakery %s', 'Salon %s',
        'Laundry %s', 'Bengkel %s', 'Minimarket %s', 'Depot %s', 'Toko Roti %s', 'Optik %s',
    ];

    private const NAMES = [
        'Sinar Jaya', 'Berkah Abadi', 'Maju Makmur', 'Sumber Rezeki', 'Harapan Baru', 'Cahaya Mulia',
        'Bintang Timur', 'Mitra Sejahtera', 'Karya Utama', 'Sentosa Jaya', 'Rizki Barokah', 'Anugerah',
        'Mekar Sari', 'Tunas Muda', 'Lancar Jaya', 'Subur Makmur', 'Segar Waras', 'Nusantara Raya',
        'Bahagia', 'Sejahtera Abadi', 'Putra Mandiri', 'Dua Saudara', 'Pelangi', 'Mutiara',
        'Kencana', 'Permata Hati', 'Sumber Makmur', 'Cipta Rasa', 'Jaya Abadi', 'Fajar Baru',
        'Senja Indah', 'Teratai', 'Melati Putih', 'Kenanga', 'Kemuning', 'Dahlia', 'Anggrek',
        'Sehat Sentosa', 'Wijaya Kusuma', 'Mandala',
    ];

    private const PEOPLE = [
        'Tini', 'Sari', 'Ani', 'Wati', 'Yuli', 'Rina', 'Lastri', 'Endang', 'Marni', 'Susi',
        'Dewi', 'Nining', 'Parni', 'Sumi', 'Tuti', 'Warsih', 'Yanti', 'Ratna', 'Kasih', 'Minah',
    ];

    private const STREETS = ['Melati', 'Mawar', 'Kenanga', 'Merdeka', 'Pahlawan', 'Diponegoro', 'Sudirman', 'Gatot Subroto', 'Ahmad Yani', 'Veteran'];

    /** @return list<array{code: string, name: string, address: string, city: string, province_code: string, lat: float, lng: float}> */
    public static function rows(): array
    {
        $rows = [];
        $i = 0;
        foreach (ProvinceSeeder::rows() as [$provinceCode, , $capital, $lat, $lng]) {
            foreach (self::OFFSETS_KM as $slot => [$east, $north]) {
                $i++;
                $type = self::TYPES[$i % count(self::TYPES)];
                $round = intdiv($i, count(self::TYPES));
                $label = str_starts_with($type, 'Warung')
                    ? self::PEOPLE[$round % count(self::PEOPLE)]
                    : self::NAMES[($round + ($i % count(self::TYPES)) * 3) % count(self::NAMES)];

                $dLat = $north / 111.32;
                $dLng = $east / (111.32 * cos(deg2rad($lat)));

                $rows[] = [
                    'code' => sprintf('OUT-%03d', $i),
                    'name' => sprintf($type, $label),
                    'address' => sprintf('Jl. %s No. %d, %s', self::STREETS[($i + $slot) % count(self::STREETS)], 3 + ($i * 7) % 90, $capital),
                    'city' => $capital,
                    'province_code' => $provinceCode,
                    'lat' => round($lat + $dLat, 6),
                    'lng' => round($lng + $dLng, 6),
                ];
            }
        }

        return $rows;
    }

    public function run(): void
    {
        if (Province::query()->count() === 0) {
            $this->call(ProvinceSeeder::class);
        }
        $provinces = Province::query()->pluck('id', 'code');

        foreach (self::rows() as $row) {
            Outlet::query()->updateOrCreate(['code' => $row['code']], [
                'name' => $row['name'],
                'address' => $row['address'],
                'city' => $row['city'],
                'province_id' => $provinces[$row['province_code']],
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'geocode_source' => 'seed',
                'geocode_status' => 'ok',
                'is_active' => true,
                'is_simulated' => true,
            ]);
        }
    }
}
