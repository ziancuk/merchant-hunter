<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /** @return list<array{string, string, string, float, float}> code, name, capital, lat, lng */
    public static function rows(): array
    {
        return [
            ['AC', 'Aceh', 'Banda Aceh', 5.548290, 95.323753],
            ['SU', 'Sumatera Utara', 'Medan', 3.595196, 98.672223],
            ['SB', 'Sumatera Barat', 'Padang', -0.947100, 100.417200],
            ['RI', 'Riau', 'Pekanbaru', 0.507068, 101.447779],
            ['KR', 'Kepulauan Riau', 'Tanjung Pinang', 0.918600, 104.458600],
            ['JA', 'Jambi', 'Jambi', -1.610100, 103.613100],
            ['SS', 'Sumatera Selatan', 'Palembang', -2.976074, 104.775429],
            ['BB', 'Kepulauan Bangka Belitung', 'Pangkal Pinang', -2.129100, 106.113300],
            ['BE', 'Bengkulu', 'Bengkulu', -3.800400, 102.255400],
            ['LA', 'Lampung', 'Bandar Lampung', -5.429700, 105.261000],
            ['BT', 'Banten', 'Serang', -6.120000, 106.150300],
            ['JK', 'DKI Jakarta', 'Jakarta Pusat', -6.208763, 106.845599],
            ['JB', 'Jawa Barat', 'Bandung', -6.917464, 107.619125],
            ['JT', 'Jawa Tengah', 'Semarang', -6.966667, 110.416664],
            ['YO', 'DI Yogyakarta', 'Yogyakarta', -7.797068, 110.370529],
            ['JI', 'Jawa Timur', 'Surabaya', -7.257472, 112.752090],
            ['BA', 'Bali', 'Denpasar', -8.670458, 115.212631],
            ['NB', 'Nusa Tenggara Barat', 'Mataram', -8.583300, 116.116700],
            ['NT', 'Nusa Tenggara Timur', 'Kupang', -10.177200, 123.607000],
            ['KB', 'Kalimantan Barat', 'Pontianak', -0.026330, 109.342504],
            ['KT', 'Kalimantan Tengah', 'Palangka Raya', -2.209600, 113.916600],
            ['KS', 'Kalimantan Selatan', 'Banjarmasin', -3.316694, 114.590111],
            ['KI', 'Kalimantan Timur', 'Samarinda', -0.502100, 117.153800],
            ['KU', 'Kalimantan Utara', 'Tanjung Selor', 2.837700, 117.365300],
            ['SA', 'Sulawesi Utara', 'Manado', 1.474830, 124.842079],
            ['ST', 'Sulawesi Tengah', 'Palu', -0.895400, 119.870700],
            ['SN', 'Sulawesi Selatan', 'Makassar', -5.147665, 119.432732],
            ['SG', 'Sulawesi Tenggara', 'Kendari', -3.972000, 122.514900],
            ['GO', 'Gorontalo', 'Gorontalo', 0.543500, 123.056800],
            ['SR', 'Sulawesi Barat', 'Mamuju', -2.675800, 118.888500],
            ['MA', 'Maluku', 'Ambon', -3.695400, 128.182900],
            ['MU', 'Maluku Utara', 'Sofifi', 0.735100, 127.568700],
            ['PA', 'Papua', 'Jayapura', -2.533300, 140.716700],
            ['PB', 'Papua Barat', 'Manokwari', -0.861500, 134.062000],
            ['PS', 'Papua Selatan', 'Merauke', -8.493200, 140.401800],
            ['PT', 'Papua Tengah', 'Nabire', -3.366700, 135.483300],
            ['PP', 'Papua Pegunungan', 'Wamena', -4.095200, 138.941700],
            ['PD', 'Papua Barat Daya', 'Sorong', -0.876200, 131.255900],
        ];
    }

    public function run(): void
    {
        foreach (self::rows() as [$code, $name, $capital, $lat, $lng]) {
            Province::updateOrCreate(['code' => $code], [
                'name' => $name, 'capital_city' => $capital, 'lat' => $lat, 'lng' => $lng,
            ]);
        }
    }
}
