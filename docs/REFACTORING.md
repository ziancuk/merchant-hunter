# REFACTORING (Fase 6)

Tiga reviewer read-only (Backend, Frontend, Performa) bekerja paralel, lalu dua agen penulis memperbaiki per area di worktree terpisah (`refactor/backend`, `refactor/frontend`). Orkestrator menggabungkan dengan `--no-ff` dan memverifikasi ulang. Keputusan lengkap: DECISIONS.md D-100…D-117.

Pengukuran Review-Performa memakai 14.639 prospek (seed 42) dan salinan 102.872 baris, dijalankan in-process dengan `DB::listen` dan `EXPLAIN QUERY PLAN` pada SQLite. HTTP nyata dan FPS GPU nyata tidak diukur.

## Hasil terukur

| Area | Sebelum | Sesudah | Keputusan |
|---|---|---|---|
| Ekspor CSV prospek (14,6 ribu baris) | 0,80 dtk (hidrasi Eloquent) | 0,08 dtk (query builder + cursor), keluaran identik byte-per-byte | D-104 |
| Kolom Kanban (EXPLAIN) | 12,0 ms, temp B-tree sort | 0,30 ms, indeks komposit | D-100 |
| Tabel pipeline sort skor | 6,6 ms | 0,18 ms | D-100 |
| Query `handled_by` | 4,2 ms (scan) | 0,02 ms | D-100 |
| Query settings per `contactRules()` | 10–12 | 1 | D-102 |
| Chunk JS `app` | 525 kB (gzip 174 kB) | 284 kB (gzip 100 kB), satu chunk per halaman | D-115 |
| Chunk mesin peta | 2.009 kB satu chunk | `maplibre` 1.047 kB + `deckgl` 985 kB terpisah | D-115 |
| `Map/Index.vue` | 843 baris | 349 baris + 5 composable + `MapOverlays` | — |
| `Pipeline/Index.vue`, `ProspectDrawer.vue` | 371 / 368 baris | 322 / 284 baris | — |

## Temuan yang diperbaiki
- **Bug**: kategori dengan `osm_filters: null` menyebabkan 500 (kolom NOT NULL) → kini 422 (D-101). `retryGeocode` menandai `ok` tanpa koordinat → hanya untuk status `failed` (D-106). Uji koneksi membocorkan teks exception → pesan generik (D-107). Race pada batas kontak → `lockForUpdate` + `increment` (D-108).
- **Portabilitas**: `LIKE ... ESCAPE '\\'` rusak di MySQL → helper `LikeEscape` dengan `ESCAPE '!'` (D-103).
- **Duplikasi**: tiga salinan invalidasi cache white space → satu `WhitespaceCache::bump()` (D-105); pola klik-luar/Escape → `useDismissable`; label status tersebar → `lib/labels.ts` (D-114).
- **Kode mati**: scope `Prospect` tak terpakai, seam `class_exists` pada reconciler, aturan `mode` yang diabaikan (D-112).
- **Istilah**: Deal/Ditolak di seluruh UI (D-98 → D-114).
- **Layer peta**: tidak ada kebocoran memori (listener, ResizeObserver, rAF, overlay dibersihkan di unmount). Denyut idle dihentikan dan dikurangi ke ~12 fps (D-116).

## Ditunda (dengan alasan)
- **Agregasi SQL white space (M3)**: pada 14,6 ribu baris cache dingin 40 ms, hangat 1,6 ms; belum mendesak. Perlu dikerjakan sebelum data jauh lebih besar.
- **Otorisasi per peran (M5)**: hanya satu role (D-53); semua endpoint master terbuka bagi akun login secara sengaja. Perlu Gate bila ada peran lain.
- **Refaktor besar controller ke Action**, memecah layer pulse, `Drawer.vue` memakai `useDismissable`.
- **Full-text search**: `LIKE %x%` tetap scan penuh (29 ms pada 14,6 ribu, ≤56 ms pada 103 ribu).
- **`GET /api/prospects/{id}` menulis `geocode_queue`** (D-42): tulis pada request baca, bisa berebut kunci dengan worker di SQLite.
- **Kompresi**: `php artisan serve` tidak mengompres; web server produksi harus gzip/brotli.

## Tidak teruji
FPS pada GPU nyata (SwiftShader hanya 3–5 fps, D-94), HTTP nyata di bawah beban, `lockForUpdate` (no-op di SQLite; tes hanya memvalidasi baca ulang dan increment), MySQL/PostgreSQL (hanya SQLite yang dijalankan).
