# Catatan QA Eksploratori Browser (QA-Browser)

Tanggal: 2026-10-25. Penguji: subagen QA-Browser. Kode aplikasi tidak diubah; bug hanya dilaporkan.

## Lingkungan

| Item | Nilai |
|---|---|
| Basis data | SQLite terpisah `/private/tmp/claude-501/qa-browser/qa.sqlite` (`migrate:fresh --seed` lalu `hunter:demo-reset`, setara `hunter:seed-prospects --offline --seed=42`): 14.639 prospek aktif, 57 tutup, 190 outlet |
| Server | `php artisan serve --port=8002`, `APP_ENV=local`, `DEMO_USER_PASSWORD=demo-password-123`, akun `ayu@hunter.test` (dan `budi@hunter.test` untuk uji dobel kontak) |
| Build | `public/build` yang sudah ada, tidak di-rebuild |
| Basemap | OpenFreeMap asli (`HUNTER_BASEMAP` default) berfungsi; tile `ERR_ABORTED` hanya pembatalan permintaan biasa. Cadangan lokal diuji lewat pemblokiran `openfreemap.org` (banner tampil) |
| WhatsApp | `demo.allow_simulated_whatsapp=true` hanya pada DB QA; `window.open` di-stub agar `wa.me` tidak benar-benar dibuka |
| Browser | Playwright Chromium dengan SwiftShader (flag WebGL dari README e2e), viewport 1440x900, dpr 1, skema warna gelap; tablet 768x1024 dan ponsel 390x844 (touch) |
| Skrip bantu | `/private/tmp/claude-501/qa-browser/*.mjs` |

Server dimatikan setelah selesai (port 8002 bebas). `database/database.sqlite` pengguna tidak disentuh.

## Alur yang dicoba dan hasil

| # | Alur | Hasil |
|---|---|---|
| 1 | Guest ke `/` dialihkan ke `/login`; login salah menampilkan "Email atau kata sandi salah."; login benar | Lulus |
| 2 | Intro globe (frame globe ditangkap), tombol "Lewati intro", preferensi tersimpan di localStorage (`hunter.map.intro-skipped`), `?intro=1` memaksa ulang | Lulus |
| 3 | Peta termuat (14.639, HUD, legenda, atribusi OpenFreeMap), tanpa `console.error`/`pageerror` | Lulus |
| 4 | Filter kategori, provinsi (kamera terbang ke provinsi), skor min/maks, baru buka, punya telepon, tampilkan tutup, atur ulang; skor min 90 + maks 10 menampilkan state kosong | Lulus (lihat B-09, B-12) |
| 5 | Klik prospek di peta membuka drawer (skor, rincian, badge Simulasi, lokasi, kontak, status, catatan, aktivitas); Esc menutup | Lulus |
| 6 | WhatsApp: tautan `wa.me/6299972678847?text=...` ternormalisasi dan terisi, template per kategori, pencatatan aktivitas dan status Dihubungi | Lulus |
| 7 | Dobel kontak: akun Budi melihat "Sudah dihubungi oleh Ayu Management 0 hari lalu", wajib alasan override, sisa 2 dari 2, aktivitas tercatat | Lulus (lihat B-10, B-11) |
| 8 | Opt-out: toggle menonaktifkan tombol WhatsApp, badge "Jangan hubungi" muncul di Kanban | Lulus |
| 9 | Kanban DnD (Baru, Dihubungi, Demo, Deal, Ditolak), dialog alasan penolakan (batal tidak mengubah), pindah mundur, hitungan kolom, tahan setelah reload | Lulus |
| 10 | Tabel pipeline, urut nama, pilih baris, bulk ubah status (baris sama dilewati dengan alasan), bulk Ditolak (minta alasan), bulk opt-out, bulk rencanakan rute (`/?route=...`), ekspor CSV | Lulus (lihat B-06) |
| 11 | Perencana rute: 7 prospek Palu, urutan NN, jarak per leg, total 13,6 km, garis rute, tautan Google Maps (`dir/?api=1&origin&destination&waypoints`, `target=_blank rel=noopener`), naik/turun, hapus, kosongkan, pilih lewat klik peta | Lulus (lihat B-05) |
| 12 | Mode Heatmap, White Space (tooltip sel: "25 prospek tanpa outlet, outlet terdekat 513 m"), Cakupan; `?mode=whitespace&lat&lng` | Lulus |
| 13 | Command palette Ctrl+K (navigasi, mode, aksi, pencarian prospek, state "Tidak ada hasil", masukan HTML tidak dieksekusi), `?` membuka pintasan | Lulus |
| 14 | Dasbor: KPI, corong, kesehatan penjadwal, kartogram, kategori, white space, aktivitas per akun, digest | Lulus (lihat B-08) |
| 15 | Dampak & biaya: perhitungan dicocokkan manual (68.400 panggilan x USD 32/1.000 x Rp 16.000 = Rp 35.020.800; 106,88 deal; Rp 534.375.000), ubah asumsi, nilai tak valid (422 + pesan), Kembalikan default | Lulus |
| 16 | Master data source: simpan key (DB terenkripsi `eyJpdiI6...`, DOM, respons API, data Inertia tidak memuat nilai; hanya "...cdef tersimpan"), hapus key (kolom jadi kosong), toggle aktif, uji koneksi | Lulus (lihat B-04) |
| 17 | Master outlet (tambah, validasi koordinat/lat 95 ditolak, kode duplikat ditolak, cari, nonaktifkan), kategori (bobot 150 ditolak, pratinjau template), verifikasi (antrean kosong), pengaturan (rentang ditolak) | Lulus (lihat B-07) |
| 18 | Riwayat scheduler: ringkasan, tabel run, detail per outlet | Lulus (lihat B-03) |
| 19 | Tema terang (persisten setelah reload), halaman lain dalam tema terang | Lulus (lihat B-13) |
| 20 | Tablet 768 dan ponsel 390: peta, filter, drawer, pipeline, dasbor, master, dampak; tanpa scroll horizontal | Sebagian (lihat B-01) |
| 21 | State galat: API peta 500 menampilkan "Gagal memuat data peta" + "Coba lagi"; basemap diblokir menampilkan banner "Basemap tidak tersedia. Menampilkan peta dasar lokal." dan peta tetap siap | Lulus |
| 22 | Catatan drawer (HTML ter-escape), ubah status, Ditolak dengan alasan, kembali ke Baru | Lulus |

Console: tidak ada `console.error`/`pageerror` aplikasi pada seluruh alur. Satu-satunya error konsol adalah respons 422 yang disengaja (nilai tak valid) dan `ERR_FAILED` saat basemap sengaja diblokir. Tidak ada entri baru di `storage/logs/laravel.log` selama sesi.

## Tabel bug

| ID | Keparahan | Judul | Langkah reproduksi | Ekspektasi | Aktual |
|---|---|---|---|---|---|
| B-01 | Mayor | Checkbox filter tertutup atribusi peta di ponsel | Viewport 390x844 (touch), buka `/`, tekan Filter, coba klik "Punya nomor telepon" / "Hanya yang baru buka" | Semua kontrol filter dapat dijangkau; panel bisa digulir atau tidak tertimpa | `data-testid="map-attribution"` (kotak atribusi, `bottom-24`) menimpa kedua checkbox dan mencegah klik (Playwright: "subtree intercepts pointer events"); panel tidak bisa digulir; sisa panel di balik navigasi bawah. Zoom control juga menimpa tepi kanan panel |
| B-02 | Minor | Nama aplikasi "Laravel" di header | Buka halaman apa pun | "POS Merchant Hunter" | `APP_NAME=Laravel` pada `.env` lokal pengguna (`.env.example` benar), sehingga brand di AppShell menampilkan "Laravel". Kemungkinan masalah konfigurasi lokal, bukan kode |
| B-03 | Minor | Status run scheduler berbahasa Inggris | Buka `/scheduler` | "Berhasil" | Badge "success" (ringkasan dan tabel); sel ringkasan "0,2 jam ·0" ambigu (tanpa label untuk jumlah gagal) |
| B-04 | Minor | Pesan uji koneksi berbahasa Inggris dan menyesatkan | Master Data Source, simpan key Google (sumber nonaktif), Uji koneksi | Pesan Indonesia (D-107), jelas bahwa sumber nonaktif | "Gagal · Google provider is inactive or has no API key · 2 ms", padahal key sudah tersimpan |
| B-05 | Minor | URL rute tidak diperbarui | Buka `/?route=ID1,ID2,ID3`, tekan Kosongkan (atau ubah rute), muat ulang | Rute kosong tetap kosong | Query `route=` tetap di URL; muat ulang memulihkan rute lama. Juga: rute lintas pulau 3.674 km tidak diberi peringatan |
| B-06 | Minor | Aktivitas "Dihubungi → Dihubungi" dan teks status Inggris | Drawer: hubungi prospek yang sudah Dihubungi (override); atau ubah status | Tanpa entri perubahan status bila sama; teks Indonesia | Entri "Dihubungi → Dihubungi"; deskripsi "Status diubah: new → contacted" memakai nilai internal Inggris |
| B-07 | Minor | Pesan galat pengaturan membocorkan kunci mentah | Master Pengaturan, isi "Peringatan kontak terakhir" 999, Simpan | Pesan berbahasa baku tanpa kunci internal | Toast "contact.warn days di luar rentang yang diizinkan." |
| B-08 | Minor | Persentase corong 0,0% menyesatkan | Dasbor dengan 7 dihubungi dari 14.696 ditemukan | Menampilkan 0,05% atau "<0,1%" | "0,0% dari tahap sebelumnya"; teks aksesibilitas kartogram diawali koma (", Jambi: Penetrasi ...") |
| B-09 | Minor | Filter skor min > maks tanpa pesan | Filter: skor min 90, maks 10 | Validasi inline | Hanya state kosong; tanpa petunjuk bahwa rentang terbalik |
| B-10 | Minor | "0 hari lalu" | Peringatan dobel kontak segera setelah kontak | "Hari ini" / "baru saja" | "Sudah dihubungi oleh Ayu Management 0 hari lalu" |
| B-11 | Minor | Alasan override 1 karakter diterima | Dobel kontak, isi alasan "a" | Panjang minimum bermakna | Tombol kirim aktif dengan satu karakter |
| B-12 | Minor | Tooltip mode peta di bawah tombol | Arahkan kursor ke tab "Heatmap"/"White Space", tooltip jatuh di bawah | Tooltip di atas tombol Filter/Rute | Tombol Filter dan Rute menimpa tooltip (terpotong); legenda tidak berubah menurut mode (white space masih menampilkan legenda prospek) |
| B-13 | Minor | Tema terang hanya mengubah chrome | Klik ikon tema | Peta ikut tema | Basemap tetap gelap (by design di `basemap.ts`); HUD terang di atas peta gelap |
| B-14 | Minor | Halaman 404 bawaan berbahasa Inggris | Buka `/halaman-tak-ada` | Halaman 404 Indonesia | "404 \| NOT FOUND" |
| B-15 | Minor | Lain-lain visual | Peta zoom tinggi; Kanban 1440 | - | Marker prospek tampil sebagai kolom bersegi (poligon/oval) alih-alih lingkaran; nama kartu Kanban terpotong ("Toko Sumber ..."); drawer menutupi navigasi atas; "Perkiraan biaya Rp 0 (gratis)" pada Google Places (berbayar) saat belum dipakai; kolom tarif Google kosong padahal asumsi Dampak 32/17 |

Catatan data seed (bukan bug aplikasi): titik prospek Palu tersebar sampai ke laut/teluk, alamat "belum tersedia" (mode offline), label basemap OpenFreeMap berbahasa Inggris/lokal.

Tidak ditemukan bug kritis.

## Hal yang tidak bisa diuji / keterbatasan

- FPS nyata: SwiftShader (rasterisasi CPU), jadi kelancaran pan/zoom tidak mewakili GPU sungguhan.
- `wa.me` tidak dibuka sungguhan (di-stub); hanya URL yang dicek.
- Sinkronisasi nyata (`hunter:sync` ke Overpass/Google) tidak dijalankan; riwayat scheduler hanya berisi satu run seed. Uji koneksi Google dengan key sungguhan tidak dilakukan (hanya key palsu).
- Impor CSV outlet, antrean verifikasi dengan data (kosong), pengiriman digest email, dan geocoding Nominatim tidak diuji.
- Layar sentuh nyata, VoiceOver/pembaca layar, Safari/Firefox, dan mode `prefers-reduced-motion` tidak diuji (axe sudah ada di suite e2e).
- Autentikasi hanya diuji untuk alur normal; batas laju dan CSRF diliput suite e2e.
- Perubahan pada `.env.example`, `.gitignore`, dan `README.md` di working tree bukan dari QA ini.

## Screenshot (docs/screenshots/)

01-peta-penuh, 02-intro-globe, 03-drawer-prospek, 04-kanban, 05-dashboard, 06-white-space, 07-rute, 08-master-data-source, 09-estimasi-biaya, 10-heatmap, 11-command-palette, 12-whatsapp-composer, 13-tema-terang, 14-ponsel, 15-riwayat-scheduler (semua PNG, dpr 1, di bawah 600 KB, sudah dilihat satu per satu). Isi 15 hanya satu baris run; 10 (heatmap) pada zoom negara terlihat mirip mode prospek karena heatmap deck.gl hanya tampak jelas di zoom lebih dekat.
