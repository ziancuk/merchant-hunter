<?php

return [
    'bands' => [
        'hot' => 'Bidik Utama',
        'warm' => 'Menjanjikan',
        'medium' => 'Berpotensi',
        'cold' => 'Pantau',
    ],

    'labels' => [
        'category' => 'Kecocokan kategori',
        'quality' => 'Kualitas (rating & ulasan)',
        'proximity' => 'Kedekatan dengan outlet',
        'density' => 'Kepadatan area',
        'contact' => 'Kelengkapan kontak',
        'new' => 'Baru buka',
    ],

    'reasons' => [
        'closed' => 'Usaha tutup: skor 0',
        'category' => 'Bobot kategori :weight',
        'rating_ignored' => 'Rating tidak dihitung (nilai netral :value)',
        'rating_missing' => 'Belum ada rating (nilai netral :value)',
        'rating' => 'Rating :rating dari :reviews ulasan',
        'neighbors' => ':count outlet Hitachi dalam 500 m',
        'no_neighbors' => 'Tidak ada outlet Hitachi dalam 500 m',
        'distance_unknown' => '; jarak ke outlet belum diketahui',
        'nearest' => '; outlet terdekat :distance m',
        'density' => ':count prospek lain dalam 500 m',
        'contact_none' => 'Belum ada telepon, website, atau alamat',
        'contact_have' => 'Tersedia: :items',
        'contact_phone' => 'telepon',
        'contact_website' => 'website',
        'contact_address' => 'alamat',
        'not_new' => 'Bukan usaha baru buka',
        'new_age' => 'Baru buka :days hari lalu',
        'new_expired' => 'Baru buka, tetapi sudah lewat :days hari',
    ],
];
