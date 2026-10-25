<?php

return [
    'not_found' => 'Data tidak ditemukan.',
    'too_many_requests' => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.',

    'back_to_map' => 'Kembali ke peta',

    'pages' => [
        '401' => ['title' => 'Perlu masuk', 'message' => 'Sesi Anda tidak valid atau sudah berakhir. Silakan masuk kembali.'],
        '403' => ['title' => 'Akses ditolak', 'message' => 'Anda tidak memiliki izin untuk membuka halaman ini.'],
        '404' => ['title' => 'Halaman tidak ditemukan', 'message' => 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.'],
        '419' => ['title' => 'Sesi kedaluwarsa', 'message' => 'Halaman sudah terlalu lama terbuka. Muat ulang halaman lalu coba lagi.'],
        '429' => ['title' => 'Terlalu banyak permintaan', 'message' => 'Anda mengirim permintaan terlalu cepat. Tunggu sebentar lalu coba lagi.'],
        '500' => ['title' => 'Terjadi kesalahan di server', 'message' => 'Maaf, ada masalah di sisi kami. Coba lagi beberapa saat lagi; bila berlanjut, hubungi admin.'],
        '503' => ['title' => 'Sedang dalam pemeliharaan', 'message' => 'Layanan sementara tidak tersedia. Silakan kembali beberapa saat lagi.'],
    ],
];
