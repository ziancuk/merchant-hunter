<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RejectionReason;
use Illuminate\Database\Seeder;

class RejectionReasonSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            'sudah_pakai_pos' => 'Sudah memakai sistem POS lain',
            'tidak_berminat' => 'Tidak berminat',
            'harga' => 'Keberatan harga',
            'tutup' => 'Usaha sudah tutup',
            'nomor_salah' => 'Nomor salah / tidak aktif',
            'lainnya' => 'Lainnya',
        ];
        $i = 0;
        foreach ($rows as $key => $label) {
            RejectionReason::updateOrCreate(['key' => $key], ['label' => $label, 'sort_order' => $i++]);
        }
    }
}
