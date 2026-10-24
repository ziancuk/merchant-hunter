# TEST-PLAN — POS Merchant Hunter

Tes ditulis bersamaan dengan kode (Sesi 2–3), dilaporkan di `docs/TEST-REPORT.md` (Sesi 4).

## Aturan

- Deterministik: tanpa internet, waktu dibekukan (`Carbon::setTestNow`, `Date` palsu di Vitest/Playwright `clock`), tanpa urutan antar-tes, seed tetap.
- Bug yang diperbaiki wajib punya tes regresi.
- Dilarang melemahkan asersi atau men-skip tes tanpa alasan tercatat (`->skip('alasan')` + entri di TEST-REPORT).
- Target cakupan ≥ 80% baris pada `app/Services` dan logika domain; angka sebenarnya dilaporkan, tidak dibulatkan.
- Skrip standar: `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, `npm run e2e`, dan `composer ci` (gabungan).

## Lapisan

| # | Lapisan | Alat | Cakupan minimum |
|---|---|---|---|
| 1 | Unit backend | Pest | Rumus skor (batas 0/100, tiap komponen, bobot kategori, baru buka), dedupe + fuzzy name, haversine, bounding box, normalisasi WhatsApp (`08…`, `+62…`, `62…`, spasi/strip, tidak valid), perencana rute (NN + 2-opt tidak lebih panjang dari NN), pembuat pesan template (placeholder), kalkulator biaya/dampak, aturan batas frekuensi, opt-out, penyusun alamat Nominatim, skor kecocokan geocoding |
| 2 | Unit frontend | Vitest | Composable/util filter, format (angka, jarak, tanggal), store, pewarnaan skor, konversi filter → query |
| 3 | Feature/integration | Pest + DB tes (SQLite memori) | Endpoint dan halaman Inertia sesuai `CONTRACTS.md`; otorisasi (tamu → redirect login, user login → boleh); Form Request; pipeline (status, aktivitas, peringatan dobel kontak, batas frekuensi, opt-out); master CRUD + impor CSV; enkripsi API key (kolom DB bukan plaintext; respons API tidak membocorkan nilai); ekspor CSV |
| 4 | Provider | Pest + `Http::fake` | Overpass (sampel respons, kosong, 429, 504, timeout, JSON rusak, mirror fallback), Google (sampel, error kuota, hanya place_id permanen), Nominatim (reverse, forward, 429), MockProvider deterministik (seed sama → hasil sama) |
| 5 | Scheduler | Pest | `hunter:sync` end-to-end dengan provider palsu: upsert, dedupe vs outlet Hitachi, deteksi baru buka (setelah baseline), tutup (N run berturut-turut / permanen), `scheduler_runs`, idempotensi (dua kali jalan tidak menggandakan), retry/backoff job, reverse geocoding terjadwal menghormati batas laju |
| 6 | Komponen | Vitest + Vue Test Utils | Kartu prospek, tombol WhatsApp (aktif/nonaktif, href), badge Simulasi, filter panel, Kanban, state loading/empty/error |
| 7 | E2E | Playwright (Chromium) | Skenario di bawah |
| 8 | Aksesibilitas | `@axe-core/playwright` | Halaman: login, peta, pipeline, dashboard, estimasi, master. Tanpa pelanggaran serius/kritis; pengecualian dicatat dengan alasan |
| 9 | Performa | Skrip ukur (Playwright + artisan) | 10.000+ titik: waktu muat peta, FPS pan/zoom, ukuran respons endpoint peta, jumlah query/request. Ambang awal: respons peta ≤ 500 ms & ≤ 1 MB gzip per viewport, ≤ 8 query per request, FPS ≥ 40 di Chromium SwiftShader (lebih longgar dari perangkat nyata). Angka asli dicatat |
| 10 | Keamanan | Pest | Mass assignment (field terlarang diabaikan), akses tanpa login ke semua rute, XSS (nama bisnis `<script>` ter-escape di respons/UI), `website` berskema `javascript:` dibuang, rate limit login (6 percobaan/menit → 429), CSRF aktif, seeder akun demo tidak jalan di production |
| 11 | QA eksploratori | Subagent QA-Browser (Sesi 4) | Bug dicatat: langkah, ekspektasi, aktual, keparahan |

## Tes tambahan (dari review dokumen)

- `hunter:digest`: isi (baru buka, panas, follow-up > 7 hari), pengecualian opt-out, idempotensi per tanggal.
- `hunter:purge-expired`: baris Google kedaluwarsa dikosongkan, `needs_refresh=true`, `place_id` tetap.
- Ekspor CSV: sel berawalan `= + - @` diberi awalan `'`.
- Override batas frekuensi: butuh alasan, maks. 2, tercatat di aktivitas.
- `missed_runs`: tidak naik untuk prospek di luar 2 km outlet, atau bila provider fallback/cache usang.
- White space: sel tanpa outlet dalam 500 m dan ≥ 15 prospek terdeteksi pada seed deterministik.
- Funnel kumulatif, `conversion_rate`, `new_7d` mengecualikan baseline.
- Nomor simulasi: `whatsapp_url` null kecuali `demo.allow_simulated_whatsapp`; telepon rumah ⇒ `invalid_phone`.
- Rekonsiliasi impor outlet menutup prospek yang cocok.
- Cakupan: `composer test -- --coverage` dengan pcov di CI (D-73).

## Skenario E2E minimum

1. Login/logout; tamu dialihkan ke login.
2. Intro bisa dilewati; preferensi diingat (localStorage).
3. Peta termuat: canvas ada, tanpa error console.
4. Filter provinsi/kategori mengubah jumlah titik (HUD).
5. Klik prospek membuka drawer dengan data benar.
6. Tautan WhatsApp berisi nomor ternormalisasi dan pesan terisi.
7. Klik WhatsApp mencatat aktivitas dan mengubah status.
8. Peringatan dobel kontak (akun lain sudah menghubungi).
9. Opt-out menyembunyikan WhatsApp.
10. Kanban drag & drop memindah status.
11. Perencana rute menghasilkan urutan dan tautan Google Maps.
12. Dashboard menampilkan KPI konsisten dengan data seed.
13. Master Data Source: aktif/nonaktif; simpan key tidak membocorkan nilai.
14. Riwayat scheduler tampil.
15. Badge "Simulasi" tampil pada data simulasi.

## Konfigurasi E2E

- Basis data: SQLite file khusus `database/e2e.sqlite` (git-ignored), `migrate:fresh --seed` + `hunter:seed-prospects --offline --seed=42`.
- Aplikasi: `php artisan serve` + hasil `npm run build`; `APP_ENV=e2e`, `HUNTER_BASEMAP=local` agar basemap memakai style lokal tanpa internet.
- WebGL headless: `launchOptions.args = ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist']`. Dokumentasikan dan sesuaikan di Sesi 3 bila perlu.
- Workers = 1 untuk E2E (SQLite); tiap file mereset data lewat CLI `hunter:demo-reset` (dipanggil dari `globalSetup`/fixture; tidak ada endpoint HTTP untuk reset).
- Waktu dibekukan lewat `page.clock` dan `APP_FAKE_NOW` (hanya dihormati bila `APP_ENV=e2e`).

## CI

Job backend (Pint + Pest + cakupan), frontend (ESLint + Vitest + build), e2e (Playwright). Detail di `GIT-WORKFLOW.md`.
