# Panduan Menjalankan POS Merchant Hunter di Laptop Lain

Panduan langkah demi langkah untuk menjalankan aplikasi dari nol di komputer baru (macOS, Linux, atau Windows). Ringkasan singkat ada di [README](../README.md); dokumen ini lebih lengkap.

## 1. Kebutuhan sistem

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | ≥ 8.2 (diuji 8.2.29) | Ekstensi wajib: `pdo_sqlite`, `mbstring`, `intl`, `bcmath`, `zip` |
| Composer | 2.x | Pengelola paket PHP |
| Node.js + npm | ≥ 22 (diuji 22.19) | Untuk build frontend (Vue + Vite) |
| Git | terbaru | Untuk clone |
| Browser | Chrome / Edge / Firefox terbaru | Peta 3D butuh WebGL (aktifkan akselerasi hardware) |
| Internet | Ya, saat setup | Untuk `composer install`, `npm install`, dan tile peta OpenFreeMap saat aplikasi berjalan |

Tidak perlu MySQL/PostgreSQL/Redis/Docker: database memakai **SQLite** (satu file). Spesifikasi laptop: RAM ≥ 4 GB, ruang disk ≈ 1 GB (termasuk `vendor/` dan `node_modules/`).

Cek yang sudah terpasang:

```bash
php -v && php -m | grep -iE "pdo_sqlite|mbstring|intl|bcmath|zip"
composer -V
node -v && npm -v
```

## 2. Install prasyarat

### macOS (Homebrew)

```bash
brew install php@8.2 composer node@22 git
brew link --overwrite --force php@8.2 node@22
```

### Ubuntu / Debian

```bash
sudo apt update
sudo apt install -y git unzip curl php8.2-cli php8.2-sqlite3 php8.2-mbstring php8.2-intl php8.2-bcmath php8.2-zip php8.2-xml php8.2-curl
# Composer: https://getcomposer.org/download/
# Node 22: pakai nvm  ->  nvm install 22
```

### Windows

1. Pakai **WSL2 (Ubuntu)** lalu ikuti langkah Ubuntu di atas (paling mudah dan direkomendasikan), atau
2. Native: install [Laragon](https://laragon.org/) (sudah termasuk PHP, Composer, Node, Git), pastikan PHP ≥ 8.2 dan ekstensi `intl`, `bcmath`, `zip`, `pdo_sqlite` aktif di `php.ini`.

Catatan: perintah `touch` dan `cp` di bawah berlaku di macOS/Linux/WSL. Di PowerShell: `New-Item database\database.sqlite -ItemType File` dan `Copy-Item .env.example .env`.

## 3. Ambil kode

**Opsi A: dari GitHub** (direkomendasikan)

```bash
git clone https://github.com/ziancuk/merchant-hunter.git pos-merchant-hunter
cd pos-merchant-hunter
```

**Opsi B: pindah langsung dari laptop lama** (belum di-push)

```bash
# di laptop lama, dari folder induk project
tar --exclude=node_modules --exclude=vendor --exclude=.env \
    --exclude='database/*.sqlite' --exclude=public/build \
    -czf pos-merchant-hunter.tar.gz pos-merchant-hunter
# kirim file .tar.gz via flashdisk/AirDrop/cloud, lalu di laptop baru:
tar -xzf pos-merchant-hunter.tar.gz && cd pos-merchant-hunter
```

Jangan menyalin `.env`, `vendor/`, `node_modules/`, atau file `.sqlite`; semuanya dibuat ulang di langkah berikut.

## 4. Setup (sekali saja)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan hunter:seed-prospects --offline
npm install
npm run build
```

Keterangan:

- `migrate:fresh --seed` membuat tabel, 190 outlet dummy, dan 3 akun demo.
- `hunter:seed-prospects --offline` membuat ±14,6 ribu prospek dummy tanpa internet (±25 detik).
- `npm run build` menjalankan type-check lalu build produksi ke `public/build`.
- Opsional: isi `HUNTER_CONTACT_EMAIL` di `.env` dengan email Anda (dipakai sebagai User-Agent ke OSM). Jangan isi `ANTHROPIC_API_KEY` kecuali ingin fitur "Buat pesan dengan AI".

## 5. Jalankan

```bash
php artisan serve
```

Buka <http://127.0.0.1:8000> lalu login:

| Email | Kata sandi |
|---|---|
| `ayu@hunter.test` (atau `budi@…`, `citra@…`) | `demo-password-123` |

Kata sandi bisa diganti dengan mengisi `DEMO_USER_PASSWORD` di `.env` **sebelum** langkah seeding.

### Mode pengembangan (hot reload)

Ganti `npm run build` dan `php artisan serve` dengan:

```bash
composer dev
```

Menjalankan server, queue worker, log, dan Vite bersamaan. Hentikan dengan `Ctrl+C`.

## 6. Opsional: sinkronisasi dan scheduler

Untuk demo, data seed sudah cukup. Bila ingin menjalankan sinkronisasi malam secara lokal, buka dua terminal tambahan:

```bash
php artisan queue:work --queue=sync,default
php artisan schedule:work
```

Pemicu manual: `php artisan hunter:sync --provider=mock` (mock hanya jalan bila `HUNTER_DEMO_MODE=true` di `.env`).

## 7. Reset data demo

```bash
php artisan hunter:demo-reset
```

## 8. Menjalankan tes (opsional)

```bash
composer ci                       # Pint + Pest + ESLint + Vitest + build
npx playwright install chromium   # sekali saja
npm run e2e                       # ±7 menit, pakai database terpisah
```

## 9. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| `Your requirements could not be resolved ... php ^8.2` | PHP terlalu lama. Upgrade ke ≥ 8.2 dan pastikan `php -v` menunjuk versi baru. |
| `could not find driver` / `ext-intl missing` | Ekstensi PHP belum aktif. Install/aktifkan `pdo_sqlite`, `intl`, `mbstring`, `bcmath`, `zip`, lalu `composer install` ulang. |
| `No application encryption key has been specified` | Jalankan `php artisan key:generate`. |
| `Database file ... does not exist` | Buat file: `touch database/database.sqlite`, lalu `php artisan migrate:fresh --seed`. |
| Halaman putih / `Vite manifest not found` | Frontend belum di-build. Jalankan `npm run build` (atau `npm run dev` untuk mode dev). |
| Peringatan `EBADENGINE` saat `npm install` | Aman diabaikan pada Node 22.19, atau pakai Node ≥ 22.22. |
| Peta kosong / hitam | Pastikan internet aktif (tile OpenFreeMap) dan WebGL aktif di browser. Tanpa internet: set `HUNTER_BASEMAP=local` di `.env`. |
| Peta lambat | Aktifkan akselerasi hardware di browser; matikan mode hemat daya. |
| Port 8000 dipakai | `php artisan serve --port=8001`, lalu sesuaikan `APP_URL` di `.env`. |
| Login gagal | Pastikan seeding sukses (`php artisan migrate:fresh --seed`), pakai email lengkap `ayu@hunter.test`. |
| Perubahan `.env` tidak terbaca | `php artisan config:clear`, lalu restart `php artisan serve`. |
| `package-lock.json` berubah setelah install | Karena nama folder berbeda; abaikan atau `git checkout package-lock.json`. |

## 10. Catatan keamanan

- Semua data adalah **dummy**; akun demo hanya untuk lokal. Jangan dipakai di server publik.
- Untuk produksi: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`; seeder akun tidak berjalan di production. Buat akun lewat `php artisan hunter:create-user email nama`.
