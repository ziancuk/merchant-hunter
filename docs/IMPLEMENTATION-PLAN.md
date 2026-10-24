# IMPLEMENTATION-PLAN

Milestone kecil, urut dependensi, mengikuti URUTAN BUILD (PROMPT §9). Kontrak beku: `DB-SCHEMA.md` dan `CONTRACTS.md`. Setelah tiap milestone: test + lint + build hijau, commit (conventional), perbarui `AGENTS-LOG.md`, merge `--no-ff` dari branch (lihat `GIT-WORKFLOW.md`); tulis `docs/pr/NN-judul.md` untuk milestone besar.

Keterangan tahap build (PROMPT §9): **T1** Inti · **T2** Operasional · **T3** Manajemen · **T4** Nilai tambah.

## Sesi 2 — Scaffold + Backend + Design System

### M2.0 Scaffold (orkestrator, berurutan) — branch `chore/scaffold`
- `composer create-project laravel/laravel` (12), pasang Inertia (server+client), Vue 3 + TS, Tailwind, Pest, Pint, ESLint, Vitest, Playwright (konfigurasi saja), `@axe-core/playwright`.
- Migrasi + model + enum + factory **verbatim dari `DB-SCHEMA.md`**; seeder referensi (38 provinsi, kategori, alasan penolakan, data_sources).
- Auth (login/logout, rate limit 5/menit), middleware `auth` global, `HandleInertiaRequests` (props bersama), layout kosong, halaman Login sederhana, `hunter:create-user`, seeder akun demo.
- Skrip standar: `composer test|lint|ci`, `npm run test|lint|build|e2e`. `.env.example`, `.gitignore`, `.gitattributes`.
- CI `.github/workflows/ci.yml`, `pull_request_template.md`, `ISSUE_TEMPLATE/`, `dependabot.yml`.
- **Fondasi bersama (agar agent paralel tidak bentrok, D-68):** semua model Eloquent lengkap dengan relasi, scope, cast (`encrypted`), dan enum; `app/Services/Geo/*` (Haversine, BoundingBox, GridAggregator); interface + DTO: `DataProvider`, `GeocoderInterface`, `ProspectScorer` (dengan implementasi stub), `BusinessDTO`, `OutletPoint`, `ProviderResult`, `ScoreInput`; `config/hunter.php`; binding di `AppServiceProvider`; `DatabaseSeeder` yang memanggil seeder referensi; stub job `SyncOutletJob`, `GeocodeOutletJob`, `GeocodeProspectJob`, `FinalizeRunJob`; stub semua command `hunter:*`; **`routes/console.php` lengkap** (jadwal TRD §6); `hunter:create-user`; `lib/` dan `types/` dari `CONTRACTS.md`.
- **Selesai bila:** `migrate:fresh --seed` berhasil, tes auth (tamu ditolak, login ok, rate limit) hijau, `npm run build` hijau, CI lokal (`composer ci`) hijau.

### M2.1 (paralel, maks. 3 penulis, worktree masing-masing; tiap agent menulis tes)

| Agent | Branch | Menyentuh | Isi | Selesai bila |
|---|---|---|---|---|
| **BE-Provider-Scheduler** | `feat/be-provider-scheduler` | `app/Services/Providers/*`, `app/Services/Sync/*`, `app/Services/Dedupe/*`, `app/Services/Geocoding/*`, `app/Jobs/*`, `app/Console/Commands/Hunter{Sync,Geocode,GeocodeOutlets,Score,SeedProspects,DemoReset,PurgeExpired}Command.php`, `tests/Unit|Feature/Providers|Sync|Dedupe|Geocoding` (tidak menyentuh model, `routes/console.php`, atau `config/`; kebutuhan tambahan diminta lewat orkestrator) | DataProvider + DTO + registry + OSM (Overpass, cache, backoff, mirror), Mock deterministik, Google (aktif bila key), dedupe + fuzzy, baru buka/tutup, reverse geocoding terjadwal (antrean, 4/menit bersama forward geocoding, feature flag), forward geocoding + skor kecocokan + `GeocodeOutletJob`, `scheduler_runs`, `ReconcileProspectsAction`, simulasi "run kedua" di `seed-prospects` (±3% baru), purge Google, kuota bulanan | `Http::fake` untuk sukses/error/timeout/429; `hunter:sync` end-to-end dengan provider palsu idempoten; tes hijau, cakupan service ≥ 80% |
| **BE-Domain** | `feat/be-domain` | `database/seeders/Outlet*`, `app/Services/Scoring|Contact|Routing|Impact|Export/*`, `app/Actions/*`, `app/Repositories/*`, `app/Http/Controllers|Requests|Resources/*`, `app/Console/Commands/HunterDigestCommand.php`, `routes/web.php`, `tests/…/Domain` (memakai provider/geocoder lewat interface dari M2.0) | Seeder 190 outlet (38×5, koordinat nyata kota provinsi), `ScoreCalculator`, haversine/bbox/grid, endpoint peta (viewport/grid), white space, pencarian, detail prospek, pipeline + aktivitas + dobel kontak + batas frekuensi + opt-out + bulk, WhatsApp normalizer + MessageBuilder + AI service (tanpa key ⇒ 404), RoutePlanner (NN+2-opt) + URL Google Maps, CSV export, ImpactCalculator + settings, master CRUD (+impor CSV, review queue, enkripsi key, uji koneksi), digest (+ `hunter:digest`), dashboard queries (funnel kumulatif, KPI), `GET/PUT /api/settings`, rekonsiliasi saat impor outlet (memanggil `ReconcileProspectsAction`), Form Request, Resource | Semua endpoint sesuai `CONTRACTS.md` (tes feature memeriksa bentuk payload), otorisasi, mass assignment, XSS; tes hijau |
| **FE-Design-System** | `feat/fe-design-system` | `docs/DESIGN.md`, `tailwind.config`, `resources/css/*`, `resources/js/Components/ui/*`, `resources/js/Components/prospect/*`, `resources/js/Layouts/*`, `resources/js/composables/*`, tes Vitest | Token warna/tipografi/spasi, font (Google Fonts), komponen ui: GlassPanel, Button, Badge, Drawer, CommandPalette, Skeleton, EmptyState, ErrorState, Tooltip, KPI counter; **komponen bersama**: `AppShell` (HUD + dock), `ProspectCard`, `ScoreBreakdown`, `SimulasiBadge`, `WhatsAppButton`, `WhatsAppComposer`, `ProspectDrawer`, `FilterPanel`; composables useTheme/useReducedMotion/useFilters/useDebounce/useShortcuts; tema dark-first + light | DESIGN.md; komponen punya tes Vitest dengan props dari CONTRACTS.md; build hijau |

Dependensi: BE-Provider-Scheduler dan BE-Domain berbagi model/geo/interface/config yang seluruhnya dibuat di M2.0 dan tidak diubah di M2.1. `ScoreCalculator` milik BE-Domain, diikat ke `ProspectScorer` di `AppServiceProvider` saat integrasi M2.2 (stub di M2.0 agar BE-Provider-Scheduler bisa menguji). `hunter:seed-prospects` memakai `MockProvider` + `ProspectScorer`. BE-Domain memanggil `DataProvider::testConnection` dan `GeocodeOutletJob` lewat interface/stub M2.0.

### M2.2 Integrasi (orkestrator) — branch `chore/integrate-be`
Merge ketiga cabang `--no-ff`, ikat `ProspectScorer` ke `ScoreCalculator`, selesaikan konflik, jalankan `composer ci` + `npm run build`; verifikasi manual `migrate:fresh --seed && hunter:seed-prospects --offline` menghasilkan ≥ 10.000 prospek (MockProvider 55–95/outlet ⇒ ≥ 10.450 dijamin) dengan `scheduler_runs`. Perbarui AGENTS-LOG, tag **`v0.2-backend`**.

## Sesi 3 — Frontend + Integrasi + E2E

### M3.0 Spike peta (orkestrator, 1 jam) — `feat/map-spike`
Uji ColumnLayer/HexagonLayer/HeatmapLayer di globe dan di Chromium SwiftShader; putuskan D-11/D-13; catat di DECISIONS.md. Hasil menentukan brief FE-Peta.

### M3.1 (paralel, maks. 3 agent, worktree)

| Agent | Branch | Menyentuh | Isi | Selesai bila |
|---|---|---|---|---|
| **FE-Peta** | `feat/fe-peta` | `resources/js/Pages/Map*`, `resources/js/map/*` | Peta penuh layar, intro globe + lewati + localStorage, fly-to, layer outlet (glow)/prospek (kolom 3D/titik)/heatmap/white space/cakupan (cincin 2 km)/rute, **perencana rute (`RoutePanel` + layer garis rute + tautan Google Maps)**, memakai `FilterPanel`, `ProspectDrawer`, `CommandPalette`, `AppShell` dari FE-Design-System, HUD, atribusi, fallback basemap, pembersihan memori | Tes Vitest util/composable; dengan 10.000 titik interaksi mulus (terukur) |
| **FE-Pipeline** | `feat/fe-pipeline` | `resources/js/Pages/Pipeline*` | Kanban drag & drop, tabel + bulk, riwayat aktivitas & catatan, alasan penolakan, integrasi `ProspectDrawer`/`WhatsAppComposer` (peringatan dobel kontak, opt-out, AI opsional, override batas frekuensi) | Tes komponen (Kanban, tabel, state loading/empty/error) hijau |
| **FE-Dashboard-Master** | `feat/fe-dashboard-master` | `resources/js/Pages/Dashboard*|Impact*|Master*|Scheduler*`, `resources/js/Components/charts/*` | Dashboard (KPI hitung naik, funnel kumulatif, kartogram penetrasi, kategori, white space, aktivitas akun, kesehatan scheduler, ringkasan harian), Estimasi Dampak & Biaya, Master Data Source/Outlet(+impor CSV+antrean verifikasi+retry geocode)/Kategori/**Pengaturan** (aturan kontak, digest, sinkronisasi, skor), Riwayat Scheduler, ekspor CSV | Tes komponen hijau; semua asumsi dapat diedit |

Dependensi: ketiganya memakai `Components/ui`, komponen bersama, `AppShell`, dan composables dari Sesi 2 serta endpoint dari BE-Domain; tidak saling membutuhkan hasil. Kontrak dari `CONTRACTS.md`. Pipeline membuka lokasi di peta lewat URL (`/?prospect=ID`), bukan impor langsung.

### M3.2 Integrasi (orkestrator) — `chore/integrate-fe`
Merge, selesaikan konflik, `composer ci` hijau. Verifikasi navigasi dock antar halaman, shortcut global, alur rute dari pipeline ke peta (`/?route=ID,ID`).

### M3.3 QA-E2E (subagen, `test/qa-e2e`; hanya `tests/e2e`, `playwright.config.ts`)
Suite Playwright sesuai `TEST-PLAN.md` (15 skenario + axe + tes performa). CI job e2e hijau. Tag **`v0.3-app`**.

## Sesi 4 — Refactoring, Verifikasi, Paket

- **M4.1 Review paralel** (read-only): Review-Backend, Review-Frontend, Review-Performa (ukur 10.000+ titik). Hasil → `REFACTORING.md`; perbaikan per area; test tetap hijau.
- **M4.2 Verifikasi nyata**: `migrate:fresh --seed`, `hunter:seed-prospects --offline`, build, serve; QA-Browser menelusuri seluruh alur, screenshot ke `docs/screenshots/`, `TEST-REPORT.md`; Reviewer-Keamanan; perbaiki dan tes regresi.
- **M4.3 Paket**: README, `SLIDE-NOTES.md`, kerangka PPTX (Pembuat-Slide), Cek-Setup-Bersih; pemindaian rahasia; tag **`v1.0.0`**.

## Pemetaan tahap build → milestone

| Tahap | Isi | Milestone |
|---|---|---|
| T1 Inti | login, seeder outlet, provider + scheduler, prospek, peta 3D + intro, drawer, WhatsApp, skor | M2.0, M2.1 (BE), M3.1 (FE-Peta bagian inti) |
| T2 Operasional | filter, pipeline, master data source, riwayat scheduler | M2.1, M3.1 (FE-Pipeline, FE-Dashboard-Master master/riwayat) |
| T3 Manajemen | dashboard, estimasi, white space, heatmap, ekspor | M3.1 |
| T4 Nilai tambah | rute, command palette, digest, AI | M2.1, M3.1 |

Titik demo: setelah M2.2 (CLI + data + API), setelah M3.2 (aplikasi penuh).

## Risiko jadwal

| Risiko | Mitigasi |
|---|---|
| Aplikasi baru dapat didemo penuh (peta) setelah Sesi 3; setelah Sesi 2 demo = CLI + API + dokumen (risiko eksplisit atas PROMPT §9) | Sesi 3 memprioritaskan T1–T3 lebih dulu |
| Layer 3D di globe bermasalah | M3.0 spike; fallback mercator/2D (D-11) |
| WebGL headless lambat | Flag SwiftShader, `?motion=0`, timeout besar |
| Overpass lambat saat uji | Semua tes memakai `Http::fake`; demo offline |
| Deadline 29 Oktober 2026 23.59 WIB | Urutan build menjaga setiap tahap bisa didemo; Sesi 3 memprioritaskan T1–T3 sebelum T4 |
