<?php

return [
    'prospects' => [
        'filename' => 'prospek',
        'sheet' => 'Prospek',
        'columns' => [
            'id', 'nama', 'kategori', 'kota', 'provinsi', 'alamat', 'telepon', 'simulasi_telepon', 'rating',
            'jumlah_review', 'skor', 'status', 'ditangani_oleh', 'terakhir_dihubungi', 'jarak_outlet_m',
        ],
    ],
    'dashboard' => [
        'filename' => 'dashboard',
        'columns' => ['bagian', 'nama', 'nilai', 'prospek', 'menang', 'penetrasi'],
        'section_kpi' => 'KPI',
        'section_funnel' => 'Funnel',
        'section_penetration' => 'Penetrasi',
    ],
];
