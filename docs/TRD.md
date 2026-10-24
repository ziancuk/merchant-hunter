# TRD — POS Merchant Hunter

Dokumen teknis. Keputusan dan sumber riset ada di `DECISIONS.md` (ID `D-xx`); skema di `DB-SCHEMA.md`; kontrak di `CONTRACTS.md`.

## 1. Alasan Pemilihan Stack

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Backend | Laravel 12 (PHP 8.2) | Cocok dengan PHP terpasang; scheduler, queue, enkripsi, validasi, auth bawaan; umum di tim Indonesia (D-01) |
| Frontend | Inertia + Vue 3 (script setup, TS) + Vite + Tailwind | Satu repo, tanpa API terpisah untuk halaman, tipe aman, cepat |
| Peta | MapLibre GL 6.15 + deck.gl 9.4 (`@deck.gl/maplibre`) | Globe, GPU layer 3D, tanpa key berbayar (D-10…D-17) |
| Basemap | OpenFreeMap dark + fallback lokal | Tanpa key (D-20…D-25) |
| DB | SQLite (WAL) default; kompatibel MySQL/PostgreSQL | Mudah dijalankan; tanpa PostGIS (D-50) |
| Queue/Scheduler | Laravel scheduler + queue driver `database` | Tanpa Redis |
| Tes | Pest, Vitest + VTU, Playwright + axe | Lihat `TEST-PLAN.md` |

## 2. Model Data

ERD dan seluruh kolom/indeks: **`DB-SCHEMA.md`** (beku). Ringkasan relasi: `provinces 1─n outlets`, `categories 1─n prospects`, `prospects 1─n prospect_activities`, `prospects 1─1 geocode_queue`, `scheduler_runs 1─n scheduler_run_outlets`.

Indeks kunci untuk kinerja peta: `prospects(lat,lng)` (bounding box), `prospects(province_id,score)`, `prospects(pipeline_status)`, `prospects(category_id)`, unik `(provider,place_id)`.

## 3. Kontrak Endpoint / Props Inertia

Lengkap dengan contoh payload di **`CONTRACTS.md`**. Prinsip: semua halaman di balik `auth`; JSON dari `routes/web.php` prefiks `/api` (sesi + CSRF, bukan token); output lewat API Resource; validasi lewat Form Request.

## 4. Desain Provider

```php
interface DataProvider {
    public function key(): string;                          // osm | mock | google
    public function isAvailable(): bool;                    // aktif & konfigurasi lengkap
    /** @return ProviderResult  (Collection<BusinessDTO>, meta: cached, api_calls) */
    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult;
    public function testConnection(): ConnectionStatus;     // untuk "Uji koneksi"
    public function usage(): UsageInfo;                     // kuota / perkiraan biaya
}
```

- **DTO**: `BusinessDTO` (provider, placeId, name, categoryKey, lat, lng, address?, phone?, website?, rating?, reviewCount?, openingHours?, isClosedPermanently, simulatedFields[], rawTags, fetchedAt). `OutletPoint`, `ProviderResult`, `ConnectionStatus`, `UsageInfo` adalah value object `readonly`.
- **Adapter**:
  - `OsmProvider`: query Overpass (D-30), jeda, retry/backoff, mirror, cache `api_cache`, User-Agent; tag → kategori lewat `categories.osm_filters`; mendeteksi lifecycle (D-35).
  - `MockProvider`: deterministik dari `crc32(outlet.code + seed)`; menghasilkan 55–95 bisnis per outlet (rata-rata 75 ⇒ minimal 10.450 prospek untuk 190 outlet) dalam radius 2 km (sebaran radial), nama fiktif dari kamus, kategori sesuai bobot, telepon palsu rentang **non-routable `0999-xxxx-xxxx`** (tidak pernah nomor operator nyata), rating/review. Semua field palsu ditandai `simulatedFields` ⇒ flag `*_simulated`. Tidak ada I/O jaringan.
  - `GoogleProvider`: Places API (New) `searchNearby` (maks. 20 hasil per panggilan, **tanpa paginasi**; satu panggilan per kelompok `categories.google_types`); aktif hanya bila key terisi **dan** `is_active`; hanya `place_id` permanen; field lain diberi `expires_at = now + 30 hari` (D-47); menghitung `calls_this_month` dan **menolak** panggilan bila `monthly_quota` terlampaui.
- **Registry** (`ProviderRegistry`): memuat `data_sources` aktif terurut `priority`; `resolveFor(outlet)` mengembalikan daftar provider.
- **Fallback**: provider dijalankan berurutan menurut `priority`; **provider pertama yang sukses menjawab menang** untuk outlet itu (kecuali `settings.sync.multi_provider = true`); bila gagal setelah retry, lanjut ke berikutnya; run `partial` bila ada outlet gagal di semua provider. Default: `osm:10` aktif, `google:5` nonaktif, **`mock:90` nonaktif pada sync produksi** — MockProvider hanya dipakai `hunter:seed-prospects --offline` (memanggil provider langsung, bukan registry) atau sebagai fallback bila `HUNTER_DEMO_MODE=true`. Prospek `provider=mock` terpisah dari `osm`.
- **Pengayaan online** (spesifikasi I): bila `settings.enrichment.simulated = true` (**default false**; hanya untuk demo), `EnrichmentService` mengisi rating/review/telepon kosong pada bisnis OSM lewat `MockProvider::enrich()` dan menandai `*_simulated = true`. Pada baris `provider=osm` hasil pengayaan, nilai simulasi **tidak dihitung dalam skor** (lihat §7) dan WhatsApp tidak aktif untuk nomor simulasi.

## 4b. Kategori Awal (seeder)

| key | Nama | Bobot | Filter OSM (ringkas) | google_types |
|---|---|---|---|---|
| restaurant | Restoran | 85 | amenity=restaurant, food_court | restaurant |
| cafe | Kafe | 80 | amenity=cafe | cafe, coffee_shop |
| fast_food | Makanan cepat saji | 75 | amenity=fast_food, ice_cream | fast_food_restaurant |
| bakery | Toko roti | 70 | shop=bakery | bakery |
| minimarket | Minimarket/kelontong | 90 | shop=convenience, supermarket, general, kiosk | convenience_store, supermarket |
| fashion | Pakaian & fashion | 65 | shop=clothes, shoes, bag, jewelry | clothing_store, shoe_store |
| pharmacy | Apotek | 75 | amenity=pharmacy | pharmacy |
| beauty | Salon & kecantikan | 70 | shop=hairdresser, beauty, cosmetics | hair_salon, beauty_salon |
| laundry | Laundry | 55 | shop=laundry, dry_cleaning | laundry |
| electronics | Elektronik & ponsel | 70 | shop=electronics, mobile_phone, computer | electronics_store |
| automotive | Bengkel | 50 | shop=car_repair, motorcycle_repair | car_repair |
| lodging | Hotel & penginapan | 60 | tourism=hotel, guest_house, hostel, motel | lodging |

Bobot dapat diubah di Master Kategori. Template WhatsApp awal per kategori (sopan, menyebut pengirim, menyertakan jalan keluar "balas STOP bila tidak berminat") ditulis oleh seeder BE-Domain.

## 5. Algoritma Dedupe

1. **Identitas**: kunci unik `(provider, place_id)` ⇒ `upsert`; kedua kali jalan tidak menggandakan.
2. **Bukan merchant Hitachi**: untuk tiap bisnis hasil, cari outlet dalam 100 m (bounding box lalu haversine). Jika ada **dan** `similarity(nama_normal(bisnis), nama_normal(outlet)) ≥ 0,80` ⇒ dikecualikan (bukan prospek). Jika jarak ≤ 30 m dan similarity ≥ 0,6 juga dikecualikan (alamat sama, nama sedikit beda).
3. **Normalisasi nama**: huruf kecil, hapus diakritik dan tanda baca, buang kata umum (`toko`, `warung`, `pt`, `cv`, `ud`, `resto`, `restoran`, `cafe`, `kafe`, `kedai`), rapikan spasi.
4. **Similarity**: rata-rata `Jaro-Winkler` dan `token_set_ratio` (implementasi PHP murni, tanpa ekstensi).
5. **Duplikat lintas provider** (hanya bila `settings.sync.multi_provider = true`, mis. OSM vs Google): bila `(provider,place_id)` beda tetapi jarak ≤ 40 m dan similarity ≥ 0,85 ⇒ digabung ke prospek berprioritas lebih tinggi (field kosong dilengkapi), provider lain dicatat di `raw_tags.merged_from`.

## 6. Scheduler & Queue

```mermaid
flowchart TD
    A[01:00 WIB schedule hunter:sync] --> B[Buat scheduler_runs status=running]
    B --> C[Pilih ≤ max_outlets_per_run (default 200 = semua outlet aktif) outlet aktif, last_synced_at tertua]
    C --> D[Dispatch SyncOutletJob per outlet, serial pada queue 'sync']
    D --> E[ProviderRegistry resolve + cache cek]
    E --> F[searchNearby per provider berurutan]
    F --> G[Dedupe + upsert prospek]
    G --> H[Deteksi baru buka / tutup]
    H --> I[Hitung jarak, tetangga, kepadatan, skor]
    I --> J[Antrean geocode_queue untuk alamat kosong]
    J --> K[Tulis scheduler_run_outlets]
    K --> L{Semua outlet selesai?}
    L -->|ya| M[Finalisasi run: success/partial/failed]
    A2[tiap jam hunter:geocode] --> N[Proses ≤ 100 item, jeda 15 dtk, cek feature flag]
```

- `SyncOutletJob`: `$tries = 0` dengan `retryUntil()` (+30 menit) dan `$maxExceptions = 3`, `backoff = [30, 60, 120]` detik, `WithoutOverlapping` per outlet; `ShouldBeUnique` per outlet+run. (`release(delay)` karena slot belum siap **tidak** menghabiskan percobaan karena `tries` tidak dibatasi.)
- Kinerja Overpass: satu worker queue `sync`; jeda antar request 5 dtk dilakukan di `OverpassClient` (bukan `sleep` dalam job panjang: gunakan `release(delay)` bila slot belum siap).
- Penutupan run: job `FinalizeRunJob` dijalankan via `Bus::batch(...)->then()/finally()`.
- Jadwal (`routes/console.php`, dibuat lengkap di M2.0): `hunter:sync` 01:00; `hunter:geocode` tiap jam; `hunter:purge-expired` 02:30; `hunter:score --all` 05:00 (menyegarkan faktor umur "baru buka"); `hunter:digest` 06:30; reset `calls_this_month` tanggal 1 pukul 00:05.
- Batas laju Nominatim **bersama** untuk reverse dan forward geocoding (satu kunci `Cache::lock`/RateLimiter `nominatim`, 4 req/menit).
- Perintah: `hunter:sync {--outlet=} {--limit=} {--provider=} {--fresh}` (`--fresh` = abaikan `api_cache`); `hunter:geocode-outlets` (outlet tanpa koordinat, forward geocode); `hunter:geocode` (reverse queue); `hunter:score {--all}`; `hunter:seed-prospects {--offline} {--seed=42}`; `hunter:demo-reset`; `hunter:digest`; `hunter:purge-expired`; `hunter:create-user`.
- Idempotensi: upsert pada `(provider, place_id)`; deteksi baru buka hanya bila `outlets.baseline_done_at` sudah terisi *sebelum* run (prospek sync baseline diberi `is_baseline = true`).
- **`missed_runs`** naik hanya bila (a) prospek berada dalam 2 km dari outlet yang sedang disinkron, (b) provider asal prospek itu menjawab **segar dan sukses** pada run ini (bukan cache usang atau fallback), dan (c) prospek tidak ada di hasil. Sekali per run per prospek.
- `hunter:seed-prospects --offline` juga mensimulasikan satu "run kedua" (±3% prospek baru, `is_baseline=false`, sebagian `is_new_open`) agar demo punya "baru buka" dan KPI "baru 7 hari".
- **Baru buka** (D-36): bisnis baru yang pertama muncul setelah baseline outlet ⇒ `is_new_open = true`; dimatikan setelah **30 hari** dari `first_seen_at`.
- **Tutup** (D-35): lifecycle ⇒ `closed` (permanent); tidak muncul 3 sync berturut-turut ⇒ `closed (missing_runs)`; muncul lagi ⇒ dibuka kembali, `missed_runs = 0`.

## 7. Rumus Skor (0–100)

Dihitung oleh `ScoreCalculator` (fungsi murni) dari `ScoreInput`:

```
skor = round( 25·kategori + 20·kualitas + 15·kedekatan + 15·kepadatan + 10·kontak + 15·baru )
```

Tiap komponen 0–1 (poin = bobot × nilai):

| Komponen | Bobot | Rumus |
|---|---|---|
| kategori | 25 | `categories.weight / 100` |
| kualitas | 20 | `0,6·r + 0,4·v` dengan `r = clamp((rating−3)/2, 0, 1)`, `v = min(1, log10(1+reviews)/log10(501))`; tanpa rating ⇒ `0,25`. Rating/review **simulasi** dihitung hanya pada baris `is_simulated` (demo penuh, alasan diberi label "(simulasi)"); pada baris nyata dengan pengayaan simulasi diperlakukan seperti tanpa rating |
| kedekatan (efek tetangga) | 15 | `0,5·(1 − min(d,2000)/2000) + 0,5·t`, `d` = jarak ke outlet terdekat (m), `t` = 0 / 0,6 / 0,85 / 1 untuk 0 / 1 / 2 / ≥3 outlet dalam 500 m |
| kepadatan | 15 | `min(1, n/25)`, `n` = prospek lain `open` dalam 500 m |
| kontak | 10 | `0,5·telepon + 0,25·website + 0,25·alamat` (masing-masing 0/1); telepon simulasi dihitung hanya pada baris `is_simulated` |
| baru | 15 | `max(0, 1 − umur_hari/30)` bila `is_new_open`, selain itu 0 |

Pita: **Panas ≥ 75**, **Hangat 55–74**, **Sedang 35–54**, **Dingin < 35**. `score_breakdown` menyimpan array `[{key,label,value,max,points,reason}]` (sama dengan CONTRACTS) agar UI menampilkan alasan teks (mis. "3 outlet Hitachi dalam 500 m"). Parameter dapat disetel lewat `settings.scoring.params`; default di kode. Prospek `closed` diberi skor 0; `do_not_contact` tetap dihitung tetapi dikecualikan dari rekomendasi.
Kasus batas yang diuji: semua komponen 0 ⇒ 0; semua maksimum ⇒ 100; rating 3,0/5,0; review 0/500+; jarak 0/2000/>2000; `clamp`.

## 8. Strategi Performa 10.000+ Titik

- **Server**: `GET /api/map/prospects` menerima `bbox`, `zoom`, filter; `zoom < 6` ⇒ agregat grid (`cell`, `count`, `avg_score`, `lat`, `lng`); `zoom ≥ 6` ⇒ titik kolumnar `{ids[], lng[], lat[], score[], status[], flags[]}` maks 20.000 titik; query tunggal ber-indeks; tanpa N+1 (tidak ada relasi dimuat); kompresi gzip; cache HTTP singkat (`Cache-Control: private, max-age=30` + ETag).
- **Klien**: `Float32Array`/binary attributes ke deck.gl; `updateTriggers` terbatas; debounce 250 ms pada `moveend`; layer pickable hanya yang perlu; hentikan permintaan lama (`AbortController`).
- **Kolom 3D**: aktif pada zoom ≥ 5; di bawahnya titik 2D (D-11).
- **Ambang uji**: lihat `TEST-PLAN.md` lapisan 9 (≤ 500 ms, ≤ 1 MB gzip, ≤ 8 query).
- **Pengukuran nyata** dilakukan Fase 6; angka dicatat di `TEST-REPORT.md`, tidak ditebak.
- Detail pemilihan titik: `GET /api/prospects/{id}` memuat detail penuh saat klik (bukan di daftar).
- White space dihitung di server dengan grid 1 km: `sel = prospek open dalam sel`; **`white_space = count ≥ 15` dan tidak ada outlet dalam 500 m dari pusat sel** (prospek hanya ditemukan ≤ 2 km dari outlet, jadi white space berarti area padat bisnis di tepi/antara cakupan, bukan di luar 2 km); hasil di-cache 10 menit (`Cache::remember`) dan dibatalkan setelah `hunter:sync`. Haversine dihitung di PHP setelah bounding box (tidak mengandalkan fungsi matematika SQL, D-50).

## 9. Keamanan

| Area | Tindakan |
|---|---|
| Auth | Sesi Laravel; semua rute di `middleware('auth')`; tamu → `/login` |
| Rate limit | Login 5/menit (email+IP, D-58); endpoint AI 10/menit/akun; endpoint peta 300/menit |
| CSRF | Aktif (XSRF cookie, Axios/Inertia otomatis) |
| Enkripsi key | Cast `encrypted` + APP_KEY; respons tidak memuat nilai; log dimasking; `.env` di-ignore (D-55) |
| Validasi | Form Request untuk setiap input; `$fillable` eksplisit (mass assignment) |
| XSS | Vue meng-escape default; tidak ada `v-html` pada data eksternal; nama bisnis dari OSM diuji; `website` hanya http/https (skema `javascript:` dibuang di Resource) |
| SQL | Eloquent/Query Builder dengan binding |
| Header | CSP dasar (self + OpenFreeMap + fonts.googleapis/gstatic), `X-Frame-Options`, `Referrer-Policy` |
| Rahasia | `.env.example` saja; tanpa kunci di kode; pemindaian pola kunci sebelum tag `v1.0.0` |
| Panggilan eksternal | Hanya dari job/command; **dua pengecualian eksplisit** (D-60) yang dipicu aksi sadar pengguna: "Uji koneksi" (dibatasi laju, timeout 10 dtk) dan "Buat pesan dengan AI" (dibatasi laju) |
| Audit | `prospect_activities` mencatat siapa/kapan/apa |

## 10. Strategi Testing

Ringkas: unit (Pest/Vitest), feature, provider (`Http::fake`), scheduler end-to-end, komponen, E2E Playwright + axe, performa terukur, keamanan dasar. Rincian lengkap: **`TEST-PLAN.md`**.

## 11. Batasan Lisensi

| Sumber | Batasan | Penanganan |
|---|---|---|
| OSM (Overpass/Nominatim) | ODbL: atribusi wajib; kebijakan penggunaan layanan publik (batas laju, User-Agent, cache) | Atribusi di footer peta; jeda dan cache; skrip terjadwal ≤ 4 req/menit pada Nominatim (D-41) |
| OpenFreeMap | Tanpa SLA; kredit "OpenFreeMap © OpenMapTiles Data from OpenStreetMap" | Atribusi tampil; fallback lokal; self-host untuk produksi |
| **Google Places** | Hanya `place_id` yang boleh disimpan permanen; lat/lng maks. 30 hari; konten lain berupa cache berkedaluwarsa; larangan pre-fetch/bulk cache; konten Google tidak boleh ditampilkan di peta non-Google | Provider **nonaktif default**; kolom `expires_at`; `hunter:purge-expired`; peringatan di UI Data Source; **pemilik wajib memverifikasi ToS terbaru dan menyetujui sebelum pengaktifan (belum terverifikasi penuh, D-47)** |
| Peta MapLibre/deck.gl | MIT/BSD/Apache | Tidak ada batasan khusus |
| Data simulasi | Bukan data nyata | Flag di DB + badge "Simulasi" |

## 12. Konfigurasi (`.env.example`)

`APP_*`, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `APP_TIMEZONE=Asia/Jakarta`, `HUNTER_OVERPASS_ENDPOINT`, `HUNTER_OVERPASS_MIRROR`, `HUNTER_CONTACT_EMAIL` (untuk User-Agent), `HUNTER_NOMINATIM_URL`, `GEOCODING_ENABLED=true`, `HUNTER_BASEMAP=openfreemap|local`, `HUNTER_DEMO_MODE=false`, `DEMO_USER_PASSWORD` (seeder akun demo; seeder tidak jalan di `APP_ENV=production`), `APP_FAKE_NOW=` (hanya dihormati bila `APP_ENV=e2e`), `ANTHROPIC_API_KEY=` (kosong ⇒ tombol AI disembunyikan), `ANTHROPIC_MODEL=claude-sonnet-5-5`, `MAIL_*` (opsional untuk digest). Kunci provider (Google) disimpan di `data_sources.api_key` terenkripsi, bukan di `.env`.
