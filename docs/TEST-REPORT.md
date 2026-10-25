# TEST-REPORT (Sesi 4)

Tanggal: 25 Oktober 2026. Lingkungan: macOS (Darwin 25.6), PHP 8.2.29, Node 22.19, SQLite, Chromium headless Playwright dengan rasterisasi CPU (SwiftShader). Data uji: seed deterministik 42 (190 outlet, 14.639 prospek aktif, 57 tutup).

Angka di bawah dijalankan ulang pada commit akhir sesi dan bukan disalin dari laporan agen.

## 1. Ringkasan hasil

| Lapisan | Alat | Hasil |
|---|---|---|
| Unit + Feature backend | Pest | **455 lulus**, 7.732 assertion, ±14 dtk |
| Frontend (util, composable, komponen) | Vitest + Vue Test Utils | **299 lulus**, 30 berkas |
| E2E + aksesibilitas + performa + keamanan | Playwright (Chromium) | **46 lulus**, ±7 menit (juga 42/42 setelah refactor, sebelum 4 tes baru ditambahkan; lihat catatan §4.1 tentang tes FPS) |
| Gaya kode | Laravel Pint, ESLint (`--max-warnings=0`), `vue-tsc` | bersih |
| Build produksi | Vite | berhasil |
| Keamanan dependensi | `composer audit`, `npm audit --omit=dev` | Composer bersih; npm 13 kerentanan transitif (lihat §7) |
| Pemindaian secret | `git grep` + `git log -p` | 0 temuan (lihat §7) |

`composer ci` (Pint + Pest + ESLint + Vitest + build) hijau; `npm run e2e` hijau.

## 2. Cakupan (angka asli)

| Area | Pengukuran | Hasil |
|---|---|---|
| Frontend (`resources/js`, Vitest v8) | pernyataan / cabang / fungsi / baris | **79,99% / 72,58% / 76,03% / 81,85%** |
| Backend `app/` (Pest) | — | **TIDAK TERUKUR di mesin ini**: PHP tidak punya pcov/xdebug, dan pemasangan pcov gagal karena `autoconf` lokal rusak (`perl5.30` hilang). |

Target PROMPT (≥ 80% baris pada `app/Services` dan logika domain) **belum dapat dibuktikan**. CI sudah memasang pcov (`coverage: pcov`, menjalankan `php artisan test --coverage`) sehingga angkanya akan muncul di GitHub Actions; ambang minimum belum dipasang di CI. Cakupan perilaku diyakini tinggi karena 455 tes mencakup tiap Action/Service domain, tetapi itu bukan angka. Cakupan frontend sedikit di bawah 80% pada pernyataan (79,99%), terutama layer `resources/js/map` yang bergantung pada WebGL dan dicakup oleh E2E, bukan Vitest.

## 3. Isi suite

**Pest (455)**: skor (kasus batas), dedupe + fuzzy name, haversine/bounding box, normalisasi WhatsApp (08…, +62…, 62…, tidak valid, rentang demo), perencana rute, template pesan, kalkulator dampak/biaya, batas frekuensi + opt-out; provider OSM/Google/Mock dengan `Http::fake` (sukses, error, timeout, rate limit); `hunter:sync` end-to-end dengan provider palsu (upsert, dedupe, baru buka, tutup, riwayat run, idempotensi); endpoint dan halaman Inertia sesuai kontrak, otorisasi (tamu ditolak), Form Request, pipeline + aktivitas + dobel kontak, master CRUD + impor CSV, enkripsi API key, mass assignment, rate limit login, header keamanan.

**Vitest (299)**: util filter/format/label, composable peta, komponen kartu prospek, tombol WhatsApp (aktif/nonaktif, `href`), badge Simulasi, FilterPanel, Kanban, tabel, state loading/empty/error, chart SVG.

**Playwright (46)**, `tests/e2e`:
- Auth: login/logout, tamu dialihkan.
- Peta: intro bisa dilewati dan diingat, peta termuat tanpa `console.error`, filter mengubah jumlah titik di HUD, klik prospek membuka drawer, rute menghasilkan urutan dan tautan Google Maps, badge Simulasi.
- Kontak: `wa.me` berisi nomor ternormalisasi dan pesan terisi, klik mencatat aktivitas + status, peringatan dobel kontak, opt-out menyembunyikan WhatsApp.
- Pipeline: Kanban drag & drop, alasan penolakan, tampilan tabel.
- Dashboard: KPI konsisten dengan data. Master: aktif/nonaktif sumber data, key tidak bocor, riwayat scheduler.
- Aksesibilitas: axe (`wcag2a/2aa/21a/21aa/best-practice`) di login, peta (dengan drawer), pipeline (Kanban/tabel), dashboard, dampak, master (sumber data/outlet/kategori/pengaturan), scheduler. Tidak ada pelanggaran serius/kritis; satu pengecualian selektor (`<canvas>` peta) dengan alasan di `tests/e2e/support/axe.ts`.
- Keamanan: rate limit login (429 pada percobaan ke-6, juga per IP), akses tanpa login, CSRF, XSS pada nama bisnis, header CSP/Permissions-Policy, skrip inline asing diblokir.
- Performa: lihat §5.

Konfigurasi WebGL headless: `--use-gl=angle --use-angle=swiftshader --enable-unsafe-swiftshader --ignore-gpu-blocklist` (dokumentasi: `tests/e2e/README.md`).

## 4. Catatan jujur tentang hasil

1. Satu tes E2E (`perf.spec.ts`, FPS pan/zoom) gagal **tiga kali** dalam run agen saat mesin sedang sibuk (load average 6–12) dengan FPS 1,5–1,9 melawan ambang 2; lulus sendirian 4 dari 4 dan lulus pada run penuh orkestrator. Penyebab yang paling mungkin adalah beban mesin, **tetapi tidak dibuktikan** dengan pembanding pada kode sebelum perubahan. Ambang tidak diubah. Tes ini bisa flaky pada mesin lambat.
2. `lockForUpdate` pada `ContactProspectAction` adalah no-op di SQLite; tes hanya memvalidasi pembacaan ulang dan increment atomik, bukan balapan sungguhan.
3. Tes lama `MasterApiTest` "rate-limits connection tests" ditemukan bergantung pada internet (provider OSM menembak jaringan sungguhan); diperbaiki dengan `Http::fake()` (bukan melemahkan asersi).
4. Tes `re-queues forward geocoding for a failed outlet` diubah sengaja (perilaku baru D-106: status tetap `failed`, tidak diubah endpoint); ditambah tes 422.

## 5. Performa (14.639 titik; SwiftShader; data: `tests/e2e/perf-results.json`)

| Metrik | Terukur | Ambang |
|---|---|---|
| `/api/map/prospects` bbox negara, zoom 6 | 14.639 titik, 480 kB mentah, **151 kB gzip**, median 154 ms (HTTP hangat, `artisan serve`), 1 query aplikasi | ≤ 500 ms, ≤ 1 MB gzip |
| Waktu siap peta (data + HUD) | 3,3 dtk | < 30 dtk (pengaman) |
| Muat 14,6 ribu titik → frame tergambar | ±380–590 ms (parse 8 ms) | — |
| FPS pan / zoom, 14,6 ribu titik digambar ulang tiap frame | 2,7 / 2,2 (run ini); 3–4 pada run lain | ≥ 2 (`E2E_MIN_FPS`) |
| Query aplikasi per request API | 1–7 | ≤ 8 |
| Query aplikasi per halaman | `/` 7, `/pipeline` 11, `/dashboard` 16 | ≤ 30 |

FPS ≥ 40 pada TEST-PLAN adalah target GPU nyata; SwiftShader merasterisasi di CPU sehingga 2–4 fps terukur apa adanya. Pengaman E2E hanya mendeteksi renderer yang macet. **FPS pada GPU nyata belum terukur.** Hasil ukur sisi server dan perbaikannya (ekspor CSV 0,80 → 0,08 dtk, indeks komposit, bundel `app` 525 → 284 kB): [REFACTORING.md](REFACTORING.md). Pada 103 ribu baris (uji skala Review-Performa) semua endpoint tetap < 300 ms kecuali ekspor CSV sebelum diperbaiki.

## 6. Bug yang ditemukan dan statusnya

**Fase 6–7 (review kode):**

| ID | Keparahan | Temuan | Status | Tes regresi |
|---|---|---|---|---|
| K1 | Mayor (500) | Kategori `osm_filters: null` memicu 500 | Diperbaiki (D-101) | `CategoryFiltersTest` |
| M2 | Mayor | `LIKE … ESCAPE '\'` rusak di MySQL | Diperbaiki (D-103) | `LikeEscapeTest` |
| M6 | Mayor | Balapan batas kontak/override | Diperbaiki sebagian (D-108; lihat §4.2) | `ContactConcurrencyTest` |
| M8 | Mayor | `retryGeocode` menandai `ok` tanpa koordinat | Diperbaiki (D-106) | `MasterApiTest` |
| M9 | Mayor | Uji koneksi membocorkan teks exception | Diperbaiki (D-107) | `ConnectionMessageTest` |
| M3 | Mayor | Agregasi white space di PHP O(sel × outlet) | **Ditunda** (D-113); cache dingin 40 ms pada 14,6 ribu baris | — |
| M5 | Mayor (desain) | Semua akun login boleh mengubah master/pengaturan | **Diterima** (satu role, D-53) | — |

**QA browser (`docs/qa-browser-notes.md`):** 1 mayor + 14 minor, tanpa bug kritis.

| ID | Keparahan | Ringkas | Status |
|---|---|---|---|
| B-01 | Mayor | Panel Filter ponsel tidak bisa digulir dan tertimpa atribusi | Diperbaiki + tes |
| B-03…B-11, B-14 | Minor | Label Inggris, "0,0%", rentang skor terbalik, "0 hari lalu", alasan override 1 karakter, 404 Inggris, bocor nama kunci, URL rute basi | Diperbaiki + tes |
| B-02 | Minor | Header "Laravel" pada `.env` lokal lama | Konfigurasi lokal (`.env.example` benar; `.env` lokal dibenahi) |
| B-12, B-13, B-15 | Minor | Tooltip mode tertimpa tombol, basemap tidak mengikuti tema terang, visual kecil, rute lintas pulau tanpa peringatan | **Ditunda** (D-127) |

**Audit keamanan:** 0 kritis, 2 mayor (CSP belum ada; cookie/APP_DEBUG di contoh env), 8 minor — CSP kini enforce dan diuji di browser tanpa pelanggaran; hardening env, limiter login per IP, flag debug dari server, redaksi `last_error`, pembatasan `config`, throttle impor/ekspor, seeder akun demo aman di production: **diperbaiki** (D-118…D-127).

## 7. Keamanan dasar dan pemindaian

- Auth: semua rute di balik `auth` kecuali login; logout via POST; sesi diregenerasi; rate limit 5/menit per email+IP dan 20/menit per IP.
- API key data source: cast `encrypted`, `$hidden`, hanya `has_key` + 4 karakter terakhir; diuji tidak muncul di DOM, respons, props Inertia, log.
- Mass assignment, XSS (nama bisnis OSM, tautan website difilter skema `http(s)`), CSRF, SQL (tanpa input terinterpolasi), CSV formula injection (sanitasi awalan `'`).
- **Pemindaian secret**: `git grep -nIE` pada HEAD dengan pola `AKIA…`, `AIza…`, `sk-ant-…`, `sk-…`, `-----BEGIN … PRIVATE KEY-----`, `ghp_…` dan padanan pada seluruh riwayat (`git log -p --all`): **0 temuan**; `.env`, `*.sqlite`, `.claude/` tidak pernah ditambahkan ke git. Pemindaian akhir sebelum tag dicatat di bawah (§9). Keterbatasan: pola tidak menangkap format token lain (Slack, Stripe, generik); gitleaks tidak terpasang.
- `npm audit --omit=dev`: 13 kerentanan transitif (4 moderate, 9 high) pada parser berkas dalam deck.gl/loaders.gl (fflate, image-size). Aplikasi tidak memasukkan berkas pihak luar ke parser itu; risiko praktis rendah. `npm audit fix --force` tidak dijalankan karena perubahan breaking. Dipantau.

## 8. Tidak teruji

- FPS pada GPU nyata; cakupan baris backend (angka); MySQL/PostgreSQL (hanya SQLite).
- `hunter:sync` nyata ke Overpass/Google dan uji koneksi dengan key Google sungguhan; geocoding Nominatim nyata.
- `wa.me` sungguhan (`window.open` di-stub; hanya URL diperiksa); pengiriman email digest; AI "Buat pesan" (`ai_enabled` nonaktif).
- Impor CSV outlet di browser dan antrean verifikasi berisi data (dicakup Pest/Vitest saja).
- Safari/Firefox, layar sentuh nyata, pembaca layar, `prefers-reduced-motion`, kontras tema terang secara menyeluruh.
- Job CI di GitHub Actions (belum pernah dijalankan; pemilik harus memeriksa tab Actions setelah push).
- Setup dari folder bersih: lihat §9.

## 9. Setup dari folder bersih dan pemindaian akhir

**Setup dari nol (lulus):** `git clone` commit HEAD ke folder kosong, lalu perintah README persis tanpa menyalin `.env`, `vendor`, `node_modules`, atau database. Hasil: `composer install` 9 dtk, `migrate:fresh --seed` 1 dtk, `hunter:seed-prospects --offline` 23 dtk (14.696 prospek), `npm install` 20 dtk, `npm run build` 28 dtk; total ±1,5 menit. `/login` 200, `/` tanpa login 302 ke `/login`, login akun demo lalu `/`, `/pipeline`, `/dashboard`, `/master/data-sources` semuanya 200. Di clone itu `composer ci` hijau (Pest 455, Vitest 299, 78 dtk) dan `npm run e2e` 46/46 (6,6 menit). Perintah `hunter:demo-reset`, `hunter:create-user`, `schedule:list`, `hunter:sync --provider=mock`, `queue:work --queue=sync,default --once` berjalan tanpa error. `git ls-files` tidak memuat `.env`, sqlite, `vendor`, `node_modules`. Koreksi README yang ditemukan (durasi seed/E2E, peringatan EBADENGINE, opsi `--password`, jam `schedule:list` dalam UTC) sudah diterapkan.

Batasan: HUD peta pada clone itu tidak dibuka di browser oleh pemeriksa (jumlah prospek diverifikasi lewat tinker; peta diuji E2E). Satu-satunya perubahan terlacak di clone adalah field `name` di `package-lock.json` karena nama folder clone berbeda (README mencatatnya).

**Pemindaian secret akhir sebelum tag v1.0.0 (25 Oktober 2026):**
- HEAD: `git grep -nIE` dengan pola `AKIA[0-9A-Z]{16}`, `AIza[0-9A-Za-z_-]{35}`, `sk-ant-…`, `sk-[A-Za-z0-9]{32,}`, `-----BEGIN … PRIVATE KEY-----`, `ghp_…` (di luar lockfile): **0 temuan**.
- Riwayat: seluruh `git log --all -p` (79 commit, 66.103 baris diff) dipindai dengan skrip Python memakai pola yang sama ditambah token Slack (`xox[baprs]-…`): **0 temuan**. (Upaya pertama dengan `git log -G` gagal karena batas kompleksitas ugrep dan hasil 0-nya dibuang; pemindaian di atas yang valid.)
- Berkas sensitif terlacak: hanya `.env.example` (sengaja). `.env`, `*.sqlite`, `.pem`, `.claude/` tidak pernah ditambahkan ke riwayat. Tidak ada remote, berkas, atau tautan GitLab.
- Keterbatasan: pola tidak mencakup semua format token (mis. Stripe, token generik); gitleaks tidak terpasang. Nilai password demo (`demo-password-123`, `e2e-password-123`) memang ada di seeder/skrip lokal dan README, bukan rahasia.

