<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Per-category WhatsApp templates (polite, name the sender, offer an opt-out).
 * Only rows still on the generic default template are updated, so edits made in Master Kategori survive.
 */
class CategoryTemplateSeeder extends Seeder
{
    /** Must equal CategorySeeder::TEMPLATE. */
    private const DEFAULT_TEMPLATE = 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kami membantu bisnis {kategori} mengelola kasir dan stok dengan lebih mudah. Apakah boleh saya kirim info singkat dan jadwalkan demo gratis? Bila tidak berminat, balas STOP dan kami tidak akan menghubungi lagi.';

    private const OPT_OUT = 'Bila tidak berminat, balas STOP dan kami tidak akan menghubungi lagi.';

    /** @return array<string, string> category key => body (before the opt-out sentence) */
    public static function bodies(): array
    {
        return [
            'restaurant' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk bisnis {kategori} seperti Anda, kasir kami membantu catat pesanan meja, split bill, dan laporan penjualan harian tanpa repot. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'cafe' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Di jam ramai, kasir kami membantu {kategori} mencatat pesanan lebih cepat, mengelola stok bahan, dan melihat menu terlaris. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'fast_food' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk usaha {kategori}, kasir kami mempercepat antrean, menyatukan pembayaran tunai dan digital, dan merapikan laporan harian. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'bakery' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kasir kami membantu {kategori} memantau stok roti harian, mengurangi sisa produksi, dan mencatat penjualan dengan rapi. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'minimarket' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk {kategori}, kasir kami mendukung scan barcode, pantauan stok otomatis, dan laporan laba per produk. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'fashion' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kasir kami membantu toko {kategori} mengelola ukuran dan warna per item, promo musiman, dan stok antar cabang. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'pharmacy' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk {kategori}, kasir kami membantu mencatat stok obat, tanggal kedaluwarsa, dan penjualan harian dengan lebih teliti. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'beauty' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kasir kami membantu {kategori} mengatur layanan dan produk, komisi staf, serta riwayat pelanggan. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'laundry' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk usaha {kategori}, kasir kami membantu mencatat order per berat, status cucian, dan tagihan pelanggan. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'electronics' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Kasir kami membantu toko {kategori} melacak nomor seri, garansi, dan stok aksesori dengan rapi. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'automotive' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk {kategori}, kasir kami membantu mencatat jasa servis, suku cadang, dan riwayat kendaraan pelanggan. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
            'lodging' => 'Halo {nama_bisnis}, saya {nama_pengirim} dari tim Hitachi POS. Untuk bisnis {kategori}, kasir kami membantu mengelola tagihan kamar, layanan tambahan, dan laporan okupansi. Boleh saya kirim info singkat dan jadwalkan demo gratis?',
        ];
    }

    public function run(): void
    {
        foreach (self::bodies() as $key => $body) {
            Category::query()
                ->where('key', $key)
                ->where('whatsapp_template', self::DEFAULT_TEMPLATE)
                ->update(['whatsapp_template' => $body.' '.self::OPT_OUT]);
        }
    }
}
