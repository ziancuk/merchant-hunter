# POS Merchant Hunter

[![CI](https://github.com/USERNAME/pos-merchant-hunter/actions/workflows/ci.yml/badge.svg)](https://github.com/USERNAME/pos-merchant-hunter/actions/workflows/ci.yml)

Aplikasi "mission control" untuk tim sales dan manajemen **PT Hitachi Channel Solutions Indonesia**: memetakan semua outlet Hitachi POS di peta 3D interaktif, mencari bisnis potensial dalam radius 2 km dari tiap outlet secara otomatis tiap malam, memberi skor prospek, dan memudahkan menghubungi mereka lewat WhatsApp.

Final project training Claude Code (Hacktiv8). Dokumen lengkap ada di [`docs/`](docs/): PRD, TRD, arsitektur, kontrak, rencana tes, laporan tes, keputusan, dan log kerja subagen.

> **Semua data outlet adalah DUMMY** dari seeder (190 outlet fiktif di koordinat kota-kota 38 provinsi). Tidak ada kode, data, logo, atau aset dari proyek Hitachi POS yang asli. Nilai berlabel **"Simulasi"** (rating, jumlah review, telepon palsu) berasal dari MockProvider dan tidak pernah nyata.

## Fitur

- **Peta 3D**: intro globe (bisa dilewati), ~14,6 ribu titik prospek (kolom/titik GPU), marker outlet, cincin radius 2 km, mode Prospek / Heatmap / White Space / Cakupan, filter kaca, command palette (Ctrl/Cmd+K).
- **Drawer prospek**: skor + rincian alasan, jarak ke outlet terdekat, efek tetangga (outlet dalam 500 m), riwayat aktivitas, badge Baru buka / Simulasi / Tutup, alamat dari reverse geocoding ter-cache.
- **WhatsApp**: tautan `wa.me` dengan nomor dinormalisasi (62…), template per kategori yang bisa diedit, opsional "Buat pesan dengan AI" (hanya bila `ANTHROPIC_API_KEY` diisi). Klik mencatat aktivitas dan mengubah status.
- **Pipeline**: Baru → Dihubungi → Demo → Deal / Ditolak; Kanban drag & drop, tabel, bulk action, peringatan dobel kontak, batas frekuensi, opt-out "Jangan hubungi lagi".
- **Perencana rute**: urutan kunjungan (nearest-neighbor + 2-opt) dan tautan Google Maps multi-waypoint tanpa API key.
- **Dashboard**: KPI, funnel, penetrasi provinsi (kartogram), kategori teratas, white space, aktivitas per akun, kesehatan scheduler, ringkasan harian, ekspor CSV.
- **Estimasi Dampak & Biaya**: kalkulator dengan asumsi yang bisa diedit (Google vs OSM Rp0).
- **Master**: Data Source (OSM Overpass, Mock, Google; API key terenkripsi; uji koneksi), Outlet (CRUD, impor CSV, geocoding + antrean verifikasi), Kategori (bobot skor, template pesan), Pengaturan, Riwayat Scheduler.
- **Scheduler malam**: `hunter:sync` 01:00 WIB; dedupe, deteksi "baru buka" dan "tutup", retry dengan backoff.

## Prasyarat

PHP ≥ 8.2 (ekstensi `pdo_sqlite`, `mbstring`, `intl`, `bcmath`), Composer 2, Node.js ≥ 22 (npm). Diuji dengan PHP 8.2.29 dan Node 22.19. Database SQLite bawaan; tanpa layanan eksternal.

## Setup dari nol

```bash
git clone <URL-REPO> pos-merchant-hunter && cd pos-merchant-hunter
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan hunter:seed-prospects --offline
npm install
npm run build
php artisan serve
```

Buka <http://127.0.0.1:8000>. `hunter:seed-prospects --offline` memakai MockProvider (tanpa internet, deterministik, ±9 detik) dan menghasilkan ±14,6 ribu prospek. `php artisan hunter:demo-reset` mengembalikan data demo ke kondisi seed.

### Akun demo (dummy, hanya lokal)

| Email | Kata sandi |
|---|---|
| `ayu@hunter.test`, `budi@hunter.test`, `citra@hunter.test` | `demo-password-123` (ubah dengan `DEMO_USER_PASSWORD` di `.env` **sebelum** seeding) |

Seeder akun tidak berjalan di `APP_ENV=production`. Tidak ada registrasi publik; tambah akun dengan `php artisan hunter:create-user email nama`. Semua akun berperan sama ("manajemen").

### Mode pengembangan

```bash
composer dev      # server, queue worker, log, dan Vite sekaligus
```

## Menjalankan scheduler dan antrean

Sinkronisasi memakai antrean database. Jalankan worker (antrean `sync` wajib ikut) dan scheduler:

```bash
php artisan queue:work --queue=sync,default
php artisan schedule:work                      # lokal; di server: cron "* * * * * php artisan schedule:run"
php artisan hunter:sync --provider=mock        # pemicu manual (mock hanya bila HUNTER_DEMO_MODE=true)
```

Jadwal (zona Asia/Jakarta): `hunter:sync` 01:00, `hunter:geocode` tiap jam (reverse geocoding Nominatim, ≤ 4 permintaan/menit), `hunter:score --all` 05:00, `hunter:digest` 06:30, `hunter:purge-expired` 02:30. Panggilan API eksternal **tidak pernah** terjadi di request pengguna, kecuali dua aksi sadar: "Uji koneksi" data source dan "Buat pesan dengan AI".

Variabel penting di `.env` (lihat `.env.example`): `HUNTER_OVERPASS_ENDPOINT`, `HUNTER_CONTACT_EMAIL` (dikirim sebagai User-Agent ke Nominatim/Overpass; isi dengan alamat Anda), `HUNTER_BASEMAP` (`openfreemap` default atau `local`), `HUNTER_DEMO_MODE`, `ANTHROPIC_API_KEY`/`ANTHROPIC_MODEL`.

## Mengaktifkan Google Provider

Nonaktif secara bawaan. Buka **Master → Data Source → Google**, isi API key (disimpan terenkripsi dengan `APP_KEY`; nilai tidak pernah ditampilkan kembali), atur kuota bulanan, klik **Uji koneksi**, lalu aktifkan dan atur prioritas. Gunakan Places API (New) `searchNearby`. Perkiraan biaya: halaman **Estimasi Dampak & Biaya**.

## Pengujian

```bash
composer ci        # Pint + Pest + ESLint + Vitest + build
npm run e2e        # Playwright (Chromium, data seed deterministik, tanpa internet, ±6 menit)
```

Perintah terpisah: `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`. Instal browser E2E sekali: `npx playwright install chromium`. Detail dan angka: [docs/TEST-REPORT.md](docs/TEST-REPORT.md), konfigurasi E2E: [tests/e2e/README.md](tests/e2e/README.md).

## Batasan dan lisensi data

- **OpenStreetMap** (Overpass, Nominatim): data © kontributor OpenStreetMap, lisensi ODbL; atribusi ditampilkan di aplikasi. Patuhi kebijakan penggunaan Overpass/Nominatim (jeda antar permintaan, User-Agent, cache). Kualitas data OSM Indonesia bervariasi: alamat dan telepon sering kosong.
- **Basemap**: OpenFreeMap (tanpa API key, tanpa SLA). Untuk produksi, pertimbangkan self-host.
- **Google Places**: hanya `place_id` yang boleh disimpan permanen; data lain adalah cache kedaluwarsa (lat/lng maks. 30 hari) dan di-refresh lewat `place_id`. Konten Google dibatasi oleh ketentuan layanannya (tidak untuk ditampilkan di peta non-Google); verifikasi ToS terbaru sebelum pilot (D-47).
- **Data simulasi** (MockProvider) hanya untuk demo; nomor simulasi bersifat non-routable (`0999…`) dan WhatsApp-nya hanya aktif bila `demo.allow_simulated_whatsapp` dihidupkan.
- **Etika outreach & UU PDP**: hanya data bisnis publik, batas frekuensi kontak, opt-out, dan catatan siapa menghubungi siapa. Lihat PRD §9(c).
- Performa peta diukur di Chrome headless dengan rasterisasi CPU (SwiftShader); FPS pada GPU nyata belum terukur.

## Mengirim ke GitHub (langkah pemilik repo)

Agen tidak melakukan `git push`. Setelah memastikan `git status` bersih:

1. Buat repo **kosong** di GitHub (tanpa README/.gitignore/lisensi), mis. `pos-merchant-hunter`.
2. Hubungkan dan kirim:
   ```bash
   git remote add origin https://github.com/USERNAME/pos-merchant-hunter.git
   git push -u origin main --tags
   ```
3. Ganti `USERNAME` pada badge CI di README ini (`sed -i '' 's/USERNAME/nama-anda/g' README.md` di macOS), commit, dan push.
4. Akses trainer: **Settings → General → Danger Zone → Change visibility → Public**, atau **Settings → Collaborators → Add people** untuk menambahkan trainer.
5. Aktifkan **Actions** (tab Actions → "I understand my workflows, go ahead and enable them") dan pastikan CI hijau (job backend, frontend, e2e). CI belum pernah dijalankan di GitHub; periksa log bila ada kegagalan.
6. Aktifkan Dependabot dan Secret scanning bila tersedia (Settings → Code security).

## Struktur singkat

```
app/            Controllers tipis → Actions/Services/Repositories; Services/Providers (OSM, Mock, Google)
database/       migrasi (skema: docs/DB-SCHEMA.md), seeder 190 outlet
resources/js/   Pages (Inertia), Components, map/ (MapLibre + deck.gl), composables, lib
tests/          Unit + Feature (Pest), e2e (Playwright)
docs/           PRD, TRD, ARCHITECTURE, CONTRACTS, DESIGN, DECISIONS, AGENTS-LOG, TEST-REPORT, REFACTORING, SLIDE-NOTES
```
