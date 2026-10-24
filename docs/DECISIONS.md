# DECISIONS

Format: keputusan · alasan · alternatif. Tanda **[V]** = terverifikasi lewat riset sumber resmi pada 2026-10-24, **[D]** = dugaan yang harus diuji di implementasi.

## Lingkungan

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-01 | **Laravel 12** (PHP 8.2.29 terpasang, Composer 2.8.11, Node 22.19, npm 10.9) | Laravel 12 mendukung PHP 8.2; paling baru yang kompatibel | Laravel 11 |
| D-02 | Vue 3 + TypeScript + Inertia + Vite + Tailwind; Pest; Vitest; Playwright | Sesuai spesifikasi | — |
| D-03 | gitleaks tidak terpasang → pemindaian rahasia memakai `git grep`/`git log -p` pola kunci sebelum `v1.0.0` | Tidak ada izin memasang paket sistem | Pasang gitleaks via brew bila pemilik mau |

## Peta (hasil Spike-Peta)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-10 | **Jalur A**: `maplibre-gl@6.15.0` + `deck.gl@9.4.0` lewat paket **`@deck.gl/maplibre`** (`MapLibreOverlay`, bukan `@deck.gl/mapbox`), ditambah `@luma.gl/core` dan `@luma.gl/engine` `~9.4.0` | **[V]** peer `maplibre-gl ^4.5.1 \|\| ^5 \|\| ^6`; semua paket deck.gl `~9.4.0` | Pin ke maplibre-gl 5.x bila 6.x (rilis 2026-07) bermasalah |
| D-11 | Intro = **globe MapLibre murni** (tanpa deck.gl). Setelah fly-to Indonesia selesai, overlay deck.gl dipasang; bila uji menunjukkan layer 3D salah di globe, peta pindah ke `mercator` (`setProjection`) sebelum overlay dipasang. Kolom 3D hanya aktif pada zoom ≥ ambang (default 5); di bawahnya titik 2D | **[V]** deck.gl menyatakan globe via `GlobeView` eksperimental; ColumnLayer/HexagonLayer/HeatmapLayer di globe tidak terverifikasi **[D]** | Three.js untuk intro (jalur B) bila globe MapLibre kurang memuaskan |
| D-12 | **Spike wajib di awal Sesi 3** (milestone M3.0): uji ColumnLayer, HexagonLayer, HeatmapLayer di globe dan SwiftShader; hasilnya menentukan D-11 | Mengurangi risiko sebelum membangun fitur | — |
| D-13 | Heatmap memakai heatmap **native MapLibre** (layer `heatmap`) bila `HeatmapLayer` deck.gl bermasalah | **[V]** HeatmapLayer render-to-texture sering bermasalah di interleaved/globe | HexagonLayer |
| D-14 | Vue: objek `map`/`overlay` disimpan non-reaktif (`shallowRef`/`markRaw`); `onBeforeUnmount`: `overlay.finalize()`, `map.removeControl`, `map.remove()`, lepas listener; layer dibuat ulang dengan `id` stabil. Worker: `setWorkerUrl` dengan `maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url` | Hindari kebocoran memori; contoh docs | — |
| D-15 | Performa 10.000+ titik: data kolom (typed array/binary attributes) dari server; filter di server (`bbox` + filter, debounce 250 ms); `zoom < 6` → server mengembalikan agregat grid; `updateTriggers` hanya untuk accessor yang berubah | **[D]** 10 ribu titik ringan untuk deck.gl; batas praktis ratusan ribu | Muat semua sekali + `DataFilterExtension` (GPU) |
| D-16 | `antialias: true` pada Map bila ArcLayer/PathLayer tampak kasar | **[V]** MapLibre antialias default false | — |
| D-17 | Playwright: flag Chromium `--use-gl=angle --use-angle=swiftshader --enable-unsafe-swiftshader --ignore-gpu-blocklist`; tes WebGL2 via `canvas.getContext('webgl2')`; parameter `?intro=0&motion=0` hanya di env e2e | **[D]** belum diuji | — |

## Basemap (hasil Riset-Tile)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-20 | **OpenFreeMap** `https://tiles.openfreemap.org/styles/dark` (cadangan `fiord`, atau `liberty`/`positron` + `setPaintProperty` gelap). Sumber tile diambil lewat TileJSON `/planet` (jangan hardcode path versi) | **[V]** HTTP 200, CORS `*`, tanpa key; isi style `dark` belum dinilai visualnya **[D]** | Protomaps PMTiles self-host |
| D-21 | **CARTO Dark Matter ditolak sebagai dasar**: dokumentasi resmi kini mewajibkan API key (komersial gratis 1 juta tile/bulan) | **[V]** | Dipakai hanya bila pemilik mau mendaftar key |
| D-22 | Atribusi tampil di peta: `OpenFreeMap © OpenMapTiles Data from OpenStreetMap`, plus tautan ke openstreetmap.org/copyright | **[V]** contoh kredit resmi; ODbL mewajibkan kredit | — |
| D-23 | **Fallback**: style lokal minimal (background gelap + garis provinsi opsional) dipakai bila fetch style > 5 dtk atau source error; banner "Basemap tidak tersedia". Env `HUNTER_BASEMAP=local` memaksa style lokal (E2E, offline) | OpenFreeMap tanpa SLA, "dapat dihentikan sewaktu-waktu" **[V]** | — |
| D-24 | Untuk pakai korporat: minta konfirmasi tertulis ke info@openfreemap.org atau self-host tile; dicatat di PRD (risiko R6) | ToS tidak mengatur pemakaian komersial secara eksplisit **[V]** | — |
| D-25 | Globe: diaktifkan dari kode (`projection: 'globe'`), bukan dari style | **[V]** style tidak mendeklarasikan projection | — |

## Provider data (hasil Riset-Overpass)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-30 | **Satu query Overpass per outlet** (`around:2000`), `out center tags;`, `[timeout:60][maxsize:64MiB]`, filter regex per kunci (`amenity`, `shop`, `tourism`, `craft`) + blok lifecycle (`disused/abandoned/was/removed/demolished:*`) yang dijalankan sebagai query terpisah bila lambat | Hasil mudah dipetakan, kegagalan terisolasi, cache sederhana **[D]** | Bounding box per kota + filter haversine |
| D-31 | **Batas laju**: User-Agent `pos-merchant-hunter/1.0 (<kontak dari env>)`, satu proses serial, jeda 5 dtk (konfigurasi), cek `/api/status`, retry 3× backoff 30/60/120 dtk + jitter (429 minimum 30 dtk), circuit breaker setelah 5 kegagalan beruntun, fallback mirror `overpass.private.coffee` | **[V]** 429/406 = tunggu 30 dtk; slot per IP; kebijakan mirror | maps.mail.ru dihindari (data/hosting Rusia, kebijakan tidak jelas) |
| D-32 | **Kuota harian**: kebijakan instance utama = batas ringan < 10.000 query/hari, < 1 GB/hari, < 10 menit waktu proses/hari **[V]**; 190 query/malam jauh di bawah itu, tetapi waktu proses perlu dipantau. Run malam memproses `max_outlets_per_run` (default **200** = semua outlet aktif, sesuai spesifikasi "tiap outlet tiap malam"), urut `last_synced_at` tertua; bila diturunkan lewat setting, outlet bergantian. Bila `/api/status` menunjukkan slot habis, run dilanjutkan keesokan malamnya. Deteksi tutup 3 run berarti ≥ 3 malam | Menghormati kebijakan penggunaan; revisi dari draf awal (100/run) berdasarkan Reviewer-Dokumen | Mirror private.coffee untuk sisanya |
| D-33 | Cache respons: kunci `sha256(query + lat/lon 5 desimal)`, TTL 7 hari, JSON dikompresi di tabel `api_cache`; dipakai saat 429/504 | **[D]** data OSM berubah lambat | — |
| D-34 | Identitas bisnis OSM = `osm_type/osm_id` (mis. `node/123`) sebagai `place_id`; dedupe `(provider, place_id)` | Stabil | — |
| D-35 | **"Tutup"**: bisnis dengan tag lifecycle (`disused:*`, `abandoned:*`, `was:*`, dll.) cocok nama/lokasi → tutup segera ("permanen"); menghilang dari hasil saja → `missed_runs++`, ditandai tutup setelah **3** sync outlet berturut-turut; label "kemungkinan tutup" | **[V]** lifecycle prefix; hilang dari hasil kurang andal **[D]** | — |
| D-36 | **"Baru buka"** = heuristik lemah: muncul setelah baseline pertama outlet (sync pertama outlet = baseline, tidak ada yang ditandai baru) dan umur `first_seen_at` ≤ 30 hari; `start_date` dipakai bila ada. UI melabeli jelas dan PRD menyatakan keterbatasannya | **[D]** `start_date` jarang terisi di Indonesia | — |
| D-37 | Telepon/alamat bukan syarat prospek; wajib: nama + kategori + koordinat. Kelengkapan kontak masuk skor | **[D]** data OSM Indonesia tipis | — |
| D-38 | Ukuran respons kota besar diperkirakan 0,2–1 MB/outlet **[D]**: dikalibrasi dengan 5 outlet nyata di Sesi 2 sebelum angka dipakai di dokumen | Tidak ada sumber resmi | — |

## Geocoding (hasil Riset-Alamat)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-40 | Antarmuka `GeocoderInterface` dengan driver `nominatim_public` (default), `nominatim_self`, `photon`; base URL dari konfigurasi | Jalur adopsi korporat: self-host Nominatim ekstrak Indonesia **[D]** | — |
| D-41 | **Reverse geocoding** hanya di scheduler lewat tabel `geocode_queue`: maks. **4 request/menit** (jeda 15 dtk) dan batas `max_per_run` (default 100) karena skrip terjadwal dihitung bulk | **[V]** kebijakan Nominatim: skrip terjadwal ≤ 4/menit, bulk dibatasi, cache wajib | Self-host untuk backlog ribuan |
| D-42 | Prioritas antrean: prospek yang pernah dibuka di drawer/di-hover > skor tinggi > sisanya; skor rendah boleh tidak pernah di-geocode (tampilkan koordinat + "Alamat belum tersedia") | Menekan volume | — |
| D-43 | Parameter: `format=jsonv2&zoom=18&addressdetails=1&accept-language=id`; alamat satu baris dari `road`(+`house_number`), `neighbourhood/suburb/village`, `city_district/county`, `city/town/regency`, `state`, `postcode` (buang kosong dan duplikat); simpan `display_name` mentah sebagai cadangan | **[D]** nama field Indonesia bervariasi | — |
| D-44 | Feature flag `GEOCODING_ENABLED` untuk mematikan layanan tanpa update kode; berhenti saat 403/429 | **[V]** aplikasi harus bisa dimatikan | — |
| D-45 | **Forward geocoding** (impor outlet): `q="{nama}, {kota}"`, `countrycodes=id`, `viewbox` pusat kota + `bounded=1`, `limit=5`. Skor 0–100 = 0,5·similarity nama (Jaro-Winkler/token set setelah normalisasi) + 0,3·skor jarak (`1 − km/20`) + 0,1·kesesuaian tipe + 0,1·importance. ≥ 80 otomatis; 50–79 antrean verifikasi; < 50 ditolak (gagal) | **[D]** ambang harus dikalibrasi | — |
| D-46 | Atribusi OSM/ODbL ditampilkan di footer; hasil Nominatim disimpan jangka panjang, disegarkan berkala (6–12 bulan) | **[V]** atribusi wajib; tidak ada batas cache dari OSM | — |
| D-47 | **Google Places**: hanya `place_id` permanen; semua field lain `expires_at` ≤ 30 hari, refresh via place_id; konten Google tidak ditampilkan di peta non-Google dilarang secara ketentuan → **dicatat sebagai batasan lisensi**, provider Google nonaktif default dan dokumentasi menyarankan verifikasi teks ToS terbaru | **[D]** halaman ToS terpotong saat riset; harus diverifikasi sebelum pilot | — |

## Domain & arsitektur

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-50 | Basis data SQLite (WAL) default; tanpa PostGIS; jarak via bounding box + haversine PHP/SQL ekspresi portabel | Spesifikasi | MySQL/PostgreSQL |
| D-51 | **Skema DB dan kontrak dibekukan sebagai dokumen** (`docs/DB-SCHEMA.md`, `docs/CONTRACTS.md`) di Sesi 1 karena Sesi 1 tanpa kode; berkas migrasi ditulis verbatim dari DB-SCHEMA.md di Fase 5.0. Perubahan sesudahnya wajib lewat entri di DECISIONS.md | PROMPT melarang kode aplikasi di Sesi 1 | — |
| D-52 | Penetrasi per provinsi ditampilkan sebagai **kartogram petak/radial** (bukan choropleth batas poligon) | Menghindari pengiriman data batas wilayah dan masalah lisensi; lebih khas | Choropleth dengan GeoJSON batas provinsi |
| D-53 | Akun dibuat lewat seeder dan `php artisan hunter:create-user`; tanpa halaman registrasi publik | Hanya satu role, akun dikelola admin sistem | Halaman master pengguna |
| D-54 | Zona waktu `Asia/Jakarta`; scheduler `hunter:sync` pukul 01:00; `hunter:geocode` tiap jam setelah sync; `hunter:digest` 06:30 | Spesifikasi | — |
| D-55 | Pengamanan API key: cast `encrypted` Laravel (APP_KEY); respons tidak pernah memuat nilai, hanya `has_key` dan 4 karakter terakhir bertopeng | Spesifikasi | — |
| D-56 | Rute: nearest-neighbor + 2-opt di PHP pada jarak haversine (rute garis lurus, label "estimasi"); tautan Google Maps `https://www.google.com/maps/dir/?api=1&origin=…&destination=…&waypoints=…` (maks. 9 waypoint per tautan, dipecah bila lebih) | Tanpa API key | OSRM publik (dilarang dipanggil saat request pengguna) |
| D-57 | Skor 0–100 dihitung di PHP (`ScoreCalculator`), disimpan di `prospects.score` + `score_breakdown` JSON dan dihitung ulang oleh `hunter:score`; rumus di TRD §7 | Query peta cepat, tanpa hitung saat request | Kolom virtual |
| D-58 | Rate limit login 5/menit per email+IP | Keamanan dasar | — |
| D-59 | Aturan kontak default (dapat diubah di `settings`): peringatan dobel kontak 7 hari; batas frekuensi 1 kontak/14 hari per prospek (kontak ulang di dalam batas memerlukan alasan eksplisit, dicatat; opt-out memblokir total) | Etika outreach | — |

## Keputusan hasil Reviewer-Dokumen (2026-10-24)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-60 | Aturan "tanpa panggilan eksternal dari request pengguna" punya **dua pengecualian eksplisit**: "Uji koneksi" Data Source (timeout 10 dtk, rate limit) dan "Buat pesan dengan AI" (rate limit); keduanya dipicu aksi sadar pengguna dan diwajibkan oleh spesifikasi | Konsistensi dokumen | — |
| D-61 | **White space** = sel grid 1 km dengan ≥ 15 prospek open dan **tanpa outlet dalam 500 m** (bukan 2 km, karena prospek hanya ditemukan ≤ 2 km dari outlet) | Definisi awal mustahil terpenuhi | Penemuan area di luar outlet (grid kota), di luar lingkup |
| D-62 | **Nomor simulasi tidak boleh berubah jadi kontak nyata**: rentang non-routable `0999-…`; `whatsapp_url` null untuk `phone_simulated` kecuali `demo.allow_simulated_whatsapp` (demo/E2E); enrichment simulasi pada data OSM default **off**; skor mengabaikan rating/telepon simulasi pada baris nyata | Aturan keras: jangan tampilkan simulasi seolah nyata | — |
| D-63 | MockProvider **nonaktif pada sync produksi**; hanya `seed-prospects --offline` atau fallback bila `HUNTER_DEMO_MODE=true`; provider pertama yang sukses menang per outlet; `missed_runs` hanya naik bila provider asal menjawab segar dan prospek ada dalam 2 km outlet yang disinkron | Hindari data palsu bercampur nyata; hindari tutup palsu pada radius tumpang tindih | `sync.multi_provider=true` |
| D-64 | Funnel **kumulatif** dari `contacted_at/demo_at/won_at/lost_at`; `conversion_rate = won/contacted`; `new_7d` mengecualikan `is_baseline`; "perlu follow-up" = status `contacted`/`demo` tanpa aktivitas > `digest.follow_up_days` (7), tidak opt-out | Metrik terdefinisi | Dihitung dari activities (lebih lambat) |
| D-65 | Klik WhatsApp: klien membuka `wa.me` **sinkron**, lalu mencatat lewat `fetch keepalive`; telepon rumah → `invalid_phone`; override batas frekuensi maks. 2 per prospek | Popup blocker Safari; cegah spam | — |
| D-66 | Google: `lat/lng` nullable + `needs_refresh`; `categories.google_types`; Places API (New) `searchNearby` tanpa paginasi (`avg_pages=1`) | Kepatuhan lisensi dan akurasi biaya | — |
| D-67 | `prospects.notes` dihapus; catatan hanya di `prospect_activities` (`type=note`) | Hindari dua sumber kebenaran | — |
| D-68 | Pembagian kepemilikan berkas diperbaiki (lihat IMPLEMENTATION-PLAN): M2.0 membuat semua model, `Services/Geo`, interface/DTO, `config/hunter.php`, `routes/console.php` lengkap, stub job; komponen UI bersama (drawer, composer WhatsApp, filter panel, AppShell) dimiliki FE-Design-System; UI rute dimiliki FE-Peta | Cegah konflik antar agent paralel | — |
| D-69 | Akun demo: seeder membaca `DEMO_USER_PASSWORD` dari env (default hanya di README), tidak jalan di production; `APP_FAKE_NOW` hanya dihormati di `APP_ENV=e2e` | Keamanan | — |
| D-70 | `city` prospek diturunkan dari `outlets.city` outlet terdekat, ditimpa reverse geocoding; `filter_options.cities` = DISTINCT `outlets.city`; index `prospects.city` | OSM jarang punya kota | — |
| D-71 | Impor outlet baru menjalankan rekonsiliasi: prospek lama yang cocok ditutup dengan `closed_reason=hitachi_merchant` | Dedupe tidak hanya saat sync | — |
| D-72 | Geocoding outlet memakai `GeocoderInterface` (Nominatim); Google tidak dipakai untuk geocoding di v1 (hanya Places Nearby) | Sederhana dan patuh lisensi | — |
| D-73 | Pest coverage butuh pcov: dipasang di job backend CI (`shivammathur/setup-php` `coverage: pcov`) | Target ≥ 80% | xdebug |

## Keputusan Sesi 2 (2026-10-24)

| ID | Keputusan | Alasan | Alternatif |
|---|---|---|---|
| D-80 | Klien `@inertiajs/vue3` dipin ke **^2** (adapter server `inertia-laravel` ^2.0) | npm menarik v3 secara default, tidak cocok dengan adapter server v2 | Naik ke Inertia v3 di kedua sisi |
| D-81 | `config('app.timezone')` tetap **UTC** (penyimpanan UTC sesuai DB-SCHEMA); zona Asia/Jakarta hanya dipakai jadwal (`->timezone()`) dan tampilan klien | Menghindari pergeseran waktu tersimpan | `APP_TIMEZONE=Asia/Jakarta` global |
| D-82 | `Prospect::$fillable` **tidak** memuat kolom pipeline/kontak (`pipeline_status`, `do_not_contact`, `handled_by`, `last_contacted_*`, `contact_count`, `*_at` tahap); hanya Action yang mengubahnya dengan `forceFill` | Tes mass assignment (TEST-PLAN lapisan 10) | `$guarded` |
| D-83 | Header keamanan M2.0: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`. **CSP ditunda** ke Sesi 3/4 (perlu diuji bersama MapLibre worker, blob:, OpenFreeMap, Google Fonts) | CSP ketat berisiko merusak peta tanpa pengujian browser | CSP sekarang |
| D-84 | Tes Feature memakai `withoutVite()` (tanpa manifest build); `npm run build` menjalankan `vue-tsc --noEmit` lebih dulu | Backend CI tidak perlu build frontend | — |
| D-85 | Seeder kategori memakai `firstOrCreate` (tidak menimpa bobot/template yang diedit di Master Kategori); template WhatsApp default satu teks umum, BE-Domain boleh menambah per kategori | Idempotensi tanpa menghapus suntingan | `updateOrCreate` |
| D-86 | Deviasi BE-Domain dari CONTRACTS (diterima, tidak merusak): error aturan bisnis 422 membawa kunci tambahan `code` (`opt_out`, `closed`, `no_phone`, `invalid_phone`, `min_interval`, `max_overrides`, `not_enough_stops`, `ai_unavailable` [502]); sel white space 0,01° (≈1,1 km) agar id berformat `-6.21:106.80` (`cell_size_m` tetap 1000); `ImpactCalculator` menambah asumsi `conv_found_to_contact` (0,2) dan hasil `hours_saved_value_idr`; impor outlet menambah `queued`; nomor simulasi ⇒ `blocked_reason=invalid_phone` kecuali `demo.allow_simulated_whatsapp`; `ChangeStatusAction` mengisi mundur timestamp tahap sebelumnya agar funnel monoton | Implementasi nyata | Ubah kontrak |
| D-87 | MockProvider: rating condong 3,4–5,0 (`3.4+1.6·√u`), ulasan s.d. 1500, sebaran radial eksponen 0,85 (lebih padat di pusat) | Dengan rumus skor beku, sebaran awal menghasilkan 0 prospek "panas" (maks 72); sekarang maks 84, 13 panas, ±2.500 hangat+ dari 14.696 prospek | Mengubah rumus skor (dilarang: beku) |
| D-88 | Worker antrean dijalankan dengan `--queue=sync,default`; seed offline menulis satu baris `scheduler_runs` (`command=hunter:sync`, `details.mode=offline_seed`) | Job sync memakai antrean `sync` | — |
| D-89 | Worktree agent: `vendor/` harus salinan nyata (bukan symlink) karena autoload `App\` mengikuti symlink ke repo utama | Ditemukan kedua agent backend | — |
