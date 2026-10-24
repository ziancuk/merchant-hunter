# ARCHITECTURE — POS Merchant Hunter

## 1. Konteks Sistem

```mermaid
flowchart LR
    U[Pengguna manajemen<br/>browser] -->|HTTPS, sesi| APP[POS Merchant Hunter<br/>Laravel + Inertia + Vue]
    APP --> DB[(SQLite / MySQL / PostgreSQL)]
    APP -. job .-> Q[Queue database]
    SCH[Laravel scheduler<br/>cron: schedule:run] --> Q
    Q --> OVP[Overpass API / mirror]
    Q --> NOM[Nominatim]
    Q --> GGL[Google Places<br/>opsional]
    APP -->|AI opsional| ANT[Anthropic API]
    U -->|tile| OFM[OpenFreeMap]
    U -->|wa.me| WA[WhatsApp]
    U -->|URL rute| GM[Google Maps]
    CSV[CSV outlet dari Hitachi POS] -->|impor manual| APP
```

Panggilan eksternal data hanya dari queue/scheduler. Browser langsung hanya ke tile basemap. Dua pengecualian dari request pengguna (D-60): "Uji koneksi" Data Source dan "Buat pesan dengan AI", keduanya dengan rate limit dan timeout.

## 2. Layer Backend

```mermaid
flowchart TD
    R[routes/web.php] --> C[Controller tipis]
    C --> FR[Form Request: validasi]
    C --> S[Service / Action]
    S --> M[Model Eloquent + Repository query]
    S --> P[Provider adapters via ProviderRegistry]
    P --> DTO[DTO: BusinessDTO, ProviderResult]
    C --> RES[API Resource / Inertia props]
```

Prinsip: controller hanya validasi → panggil Service/Action → kembalikan Resource/Inertia. Logika bisnis di `app/Services` dan `app/Actions`. DTO untuk data provider. Query kompleks (peta, white space, dashboard) di `app/Repositories`.

### Struktur `app/`

```
app/
  Actions/                 # satu aksi per kelas: ContactProspectAction, ChangeStatusAction, OptOutAction, ImportOutletsAction
  Console/Commands/        # hunter:*
  DTO/                     # BusinessDTO, OutletPoint, ProviderResult, ScoreInput, ContactCheck
  Enums/                   # PipelineStatus, LifecycleStatus, GeocodeStatus, ActivityType, ScoreBand
  Http/
    Controllers/           # Auth, Map, Prospect, Pipeline, Route, Dashboard, Impact, Master/*, SchedulerRun
    Requests/              # Form Request
    Resources/             # ProspectResource, OutletResource, ...
    Middleware/            # HandleInertiaRequests, SecurityHeaders
  Jobs/                    # SyncOutletJob, FinalizeRunJob, GeocodeProspectJob, GeocodeOutletJob
  Models/
  Repositories/            # MapQuery, DashboardQuery, WhiteSpaceQuery, ProspectFilter
  Services/
    Providers/             # DataProvider, OsmProvider, MockProvider, GoogleProvider, ProviderRegistry
    Geo/                   # Haversine, BoundingBox, GridAggregator
    Scoring/               # ScoreCalculator
    Dedupe/                # NameNormalizer, Similarity, Deduplicator
    Sync/                  # SyncService, LifecycleDetector
    Geocoding/             # GeocoderInterface, NominatimGeocoder, AddressFormatter, MatchScorer
    Contact/               # WhatsAppNormalizer, MessageBuilder, ContactPolicy, AiMessageService
    Routing/               # RoutePlanner (NN + 2-opt), GoogleMapsUrlBuilder
    Impact/                # ImpactCalculator
    Export/                # CsvExporter
database/
  migrations/ seeders/ factories/
```

## 3. Alur Data Scheduler

```mermaid
sequenceDiagram
    participant Cron as cron schedule:run
    participant Sch as hunter:sync
    participant Q as Queue(sync)
    participant Job as SyncOutletJob
    participant Reg as ProviderRegistry
    participant Prov as Provider (OSM/Mock/Google)
    participant Cache as api_cache
    participant Dd as Deduplicator
    participant DB as Database
    Cron->>Sch: 01:00 WIB
    Sch->>DB: scheduler_runs(running)
    Sch->>Q: dispatch job per outlet (≤200)
    Q->>Job: handle(outlet)
    Job->>Reg: providers aktif by priority
    Reg->>Prov: searchNearby(outlet, 2000m)
    Prov->>Cache: cek / simpan respons
    Prov-->>Job: ProviderResult(DTO)
    Job->>Dd: kecualikan merchant Hitachi, dedupe
    Job->>DB: upsert prospek, baru buka, tutup
    Job->>DB: hitung jarak/tetangga/kepadatan/skor
    Job->>DB: antrean geocode, scheduler_run_outlets
    Q->>Sch: batch selesai → finalisasi run
```

## 4. Alur Permintaan Peta

```mermaid
sequenceDiagram
    participant B as Browser (Vue)
    participant I as Inertia GET /
    participant API as GET /api/map/prospects
    participant Repo as MapQuery
    participant DB as Database
    B->>I: buka halaman peta
    I-->>B: props: opsi filter, HUD, preferensi
    B->>B: init MapLibre (globe) → intro → fly-to
    B->>API: bbox + zoom + filter (debounce 250 ms)
    API->>Repo: query ber-indeks
    Repo->>DB: SELECT kolom minimal WHERE bbox AND filter
    DB-->>Repo: baris
    Repo-->>API: kolumnar atau agregat grid
    API-->>B: JSON gzip (+ETag)
    B->>B: deck.gl layer dari typed array
    B->>API: klik → GET /api/prospects/{id}
    API-->>B: detail + aktivitas + skor
```

## 5. Frontend

```
resources/js/
  app.ts                       # bootstrap Inertia
  Layouts/                     # AppShell (HUD mengambang, nav radial/dock)
  Components/
    ui/                        # GlassPanel, Button, Badge, Drawer, CommandPalette, Skeleton, EmptyState, ErrorState, Tooltip  ← FE-Design-System
    prospect/                  # ProspectCard, ScoreBreakdown, SimulasiBadge, WhatsAppButton ← FE-Pipeline
    charts/                    # KPI counter, funnel, kartogram         ← FE-Dashboard-Master
  Pages/
    Auth/Login.vue
    Map/Index.vue (+ Map/*)    # FE-Peta
    Pipeline/Index.vue         # FE-Pipeline
    Dashboard/Index.vue        # FE-Dashboard-Master
    Impact/Index.vue
    Master/DataSources.vue, Outlets.vue, Categories.vue
    Scheduler/Runs.vue
  map/                         # FE-Peta: createMap, intro, layers/*, useMapData, usePicking, basemap, fallbackStyle
  composables/                 # useFilters, useDebounce, useReducedMotion, useShortcuts, useTheme
  lib/                         # format, whatsapp, colors, api (axios), types
  types/                       # tipe sesuai CONTRACTS.md
tests/
  Unit/ Feature/ e2e/
```

Aturan kepemilikan file (agar subagent paralel tidak bentrok, D-68):
- **FE-Design-System** (selesai di Sesi 2): `Components/ui/*` (termasuk KPI counter), konfigurasi tema/CSS, `composables/*` (useTheme, useReducedMotion, useFilters, useDebounce, useShortcuts), **`Layouts/AppShell`** (HUD, dock navigasi), serta komponen bersama berbasis kontrak: `Components/prospect/*` (`ProspectCard`, `ScoreBreakdown`, `SimulasiBadge`, `WhatsAppButton`, `WhatsAppComposer`, `ProspectDrawer`, `FilterPanel`). Diuji dengan props contoh dari `CONTRACTS.md` (tanpa backend).
- **FE-Peta**: `Pages/Map*`, `map/*` (termasuk panel & layer perencana rute, `RoutePanel`).
- **FE-Pipeline**: `Pages/Pipeline*` (Kanban, tabel, bulk, aktivitas) memakai komponen bersama.
- **FE-Dashboard-Master**: `Pages/Dashboard*`, `Pages/Impact*`, `Pages/Master*` (termasuk `Master/Settings`), `Pages/Scheduler*`, `Components/charts/*`.
- **Orkestrator (5.0)**: `lib/`, `types/` (dari CONTRACTS.md); penambahan sesudahnya lewat orkestrator.

## 6. Konvensi Kode

- Bahasa: identifier, komentar, commit **Inggris**; teks UI dan dokumen Indonesia.
- PHP: PSR-12 via Laravel Pint; `declare(strict_types=1)`; enum untuk status; kelas `final` untuk service; tidak ada logika di controller/model selain scope & cast.
- Vue: `<script setup lang="ts">`, komponen ≤ ~250 baris (pecah dan ekstrak composable), props bertipe, tanpa `any` tanpa alasan, ESLint.
- Nama: `Prospect*`, `Outlet*`; REST bernama jamak; JSON snake_case, Inertia props snake_case (selaras DB).
- Objek peta: non-reaktif (D-14), selalu dibersihkan.
- Tidak ada panggilan eksternal dari request pengguna (kecuali dua pengecualian D-60).
- Test berdampingan dengan kode; commit kecil conventional.
