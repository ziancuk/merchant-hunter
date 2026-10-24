# AGENTS-LOG

Catatan orkestrasi subagent (bahan slide "Proses Pengerjaan").

## Sesi 1 (Fase 0–4)

| Fase | Subagent | Tugas | File disentuh | Hasil | Masalah |
|---|---|---|---|---|---|
| 0 | Orkestrator | Cek lingkungan (PHP 8.2.29, Composer 2.8.11, Node 22.19, npm 10.9, git 2.50); task list; docs awal | docs/AGENTS-LOG.md, docs/DECISIONS.md | Selesai | gitleaks tidak terpasang → pindai dengan `git grep` (D-03) |
| 1 | Spike-Peta (read-only) | Verifikasi MapLibre globe + deck.gl + Vue | — (laporan) | `maplibre-gl@6.15.0`, `deck.gl@9.4.0` via `@deck.gl/maplibre`; globe deck.gl eksperimental; intro globe MapLibre lalu overlay/mercator; spike wajib M3.0 (D-10…D-17) | Perilaku ColumnLayer/Heatmap di globe tidak terverifikasi |
| 1 | Riset-Tile (read-only) | 2 basemap gratis tanpa key | — (laporan) | OpenFreeMap `styles/dark` utama; CARTO kini wajib key → ditolak (D-20…D-25) | OpenFreeMap tanpa SLA; tampilan `dark` belum dinilai |
| 1 | Riset-Overpass (read-only) | Query radius 2 km, batas rate, cache, tutup/baru buka | — (laporan) | Satu query per outlet, jeda 5 dtk, kuota harian ±100 → 100 outlet/run; baru buka = heuristik lemah (D-30…D-38) | Ukuran respons dugaan; kalibrasi di Sesi 2 |
| 1 | Riset-Alamat (read-only) | Strategi reverse/forward geocoding | — (laporan) | Skrip terjadwal ≤ 4 req/menit, antrean prioritas, self-host sebagai jalur adopsi; skor kecocokan (D-40…D-47) | Syarat Google ToS belum terverifikasi penuh |
| 1–4 | Orkestrator | Menulis PRD, TRD, DB-SCHEMA, ARCHITECTURE, CONTRACTS, IMPLEMENTATION-PLAN, TEST-PLAN, GIT-WORKFLOW | docs/*.md | Selesai | — |
| 2–4 | Reviewer-Dokumen (read-only) | Periksa inkonsistensi/celah PRD+TRD+Arsitektur+Kontrak+Rencana | — (laporan) | 19 temuan (2 kritis, 11 mayor, 6 minor); semua diperbaiki di docs → DECISIONS D-60…D-73 | Temuan kritis: white space mustahil; nomor simulasi bisa di-WhatsApp |
| 2–4 | Orkestrator | Perbaikan dokumen dari review (skema, kontrak, TRD, rencana, kepemilikan berkas) | docs/*.md | Selesai | Tidak ada tes (belum ada kode) |

## Sesi 2 (Fase 5.0–5.1)

| Fase | Subagent | Tugas | File disentuh | Hasil | Masalah |
|---|---|---|---|---|---|
| 5.0 | Orkestrator | Scaffold Laravel 12 + Inertia v2 + Vue 3/TS + Tailwind 4; 12 migrasi verbatim; model, enum, DTO, interface; seeder referensi; auth + rate limit; jadwal; CI/template GitHub; skrip standar | seluruh root, app/, database/, config/, routes/, .github/ | 18 tes Pest + 11 Vitest hijau; `migrate:fresh --seed` OK; merge `chore/scaffold` | `pest --init` interaktif menggantung (dibunuh); Inertia client v3 vs server v2 (D-80) |
| 5.1 | BE-Provider-Scheduler (worktree) | Provider OSM/Mock/Google + registry, dedupe, sync, lifecycle, geocoding Nominatim, job, 7 command `hunter:*`, ReconcileProspectsAction | app/Services/{Providers,Sync,Dedupe,Geocoding}, app/Jobs, Commands, tests | 147 tes; seed offline 14.7 ribu prospek ±9 dtk | symlink vendor merusak autoload (D-89); tanpa pcov → cakupan belum terukur |
| 5.1 | BE-Domain (worktree) | Seeder 190 outlet, ScoreCalculator, 51 rute (peta, prospek, pipeline, rute, ekspor, dashboard, master, pengaturan), digest | database/seeders, app/{Services,Actions,Repositories,Http}, routes/web.php, tests | 269 tes; query peta ≤ 2 | Deviasi kontrak dicatat (D-86) |
| 5.1 | FE-Design-System (worktree) | docs/DESIGN.md, token Tailwind, 13 komponen ui, 10 komponen prospek, AppShell, composables, Login | resources/{css,js}, docs/DESIGN.md | 93 tes Vitest, lint + build hijau | Belum dilihat di browser (Sesi 3/4) |
| 5.2→M2.2 | Orkestrator | Merge 3 cabang `--no-ff` (tanpa konflik), bind ScoreCalculator/Nominatim/ConnectionTester, perbaiki `ActionProspectReconciler` (memanggil `handle` yang tidak ada → `execute`), kalibrasi mock (D-87) | AppServiceProvider, Services/Master, MockProvider, 2 tes | 398 tes Pest + 93 Vitest, Pint, ESLint, build hijau | Skor demo awal tanpa prospek panas → D-87 |

## Sesi 3 (Fase 5.2–5.3)

| Fase | Subagent | Tugas | File disentuh | Hasil | Masalah |
|---|---|---|---|---|---|
| 5.2 | FE-Peta (worktree) | Spike M3.0, peta MapLibre + deck.gl, intro globe, layer outlet/prospek/heatmap/white space/cakupan, rute, command palette, filter, drawer | resources/js/Pages/Map, resources/js/map, 1 slot di ProspectDrawer | 195 tes Vitest, lint + build hijau; data 14,6 ribu titik 114–176 ms | FPS SwiftShader 3–14 (D-94); onClick layer tidak menembak (D-92) |
| 5.2 | FE-Pipeline (worktree) | Kanban DnD, tabel, bulk, drawer/WhatsApp/opt-out, ekspor | resources/js/Pages/Pipeline, Components/pipeline | 137 tes Vitest hijau; DnD, WhatsApp, opt-out diverifikasi di browser | Menemukan bug normalizer nomor simulasi (D-96) dan CSS backdrop-filter (D-97) |
| 5.2 | FE-Dashboard-Master (worktree) | Dashboard, Estimasi Dampak, Master (sumber data, outlet + impor CSV + verifikasi, kategori, pengaturan), Riwayat Scheduler, chart SVG | resources/js/Pages/{Dashboard,Impact,Master,Scheduler}, Components/{charts,master} | 127 tes Vitest hijau; 8 halaman termuat tanpa error console | Seed tanpa prospek dihubungi/deal → funnel datar |
| 5.3 | Orkestrator | Merge 3 cabang `--no-ff` tanpa konflik; perbaiki normalizer simulasi, backdrop-filter, 3 temuan axe di AppShell/Drawer | app/Services/Contact, Resources, css, AppShell, Drawer | 399 Pest + 273 Vitest, Pint, ESLint, build hijau | — |
