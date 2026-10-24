# CONTRACTS (BEKU)

Kontrak props Inertia dan endpoint JSON. Dibekukan di Sesi 1; perubahan wajib dicatat di `DECISIONS.md`. Semua rute di balik `auth` kecuali login. JSON snake_case. Waktu ISO-8601 UTC (`2026-10-24T01:00:00Z`). Respons error: `{"message": "...", "errors": {"field": ["..."]}}` dengan status 401/403/404/422/429.

Props Inertia bersama (semua halaman): `auth.user {id, name, email}`, `flash {success?, error?}`, `app {name, basemap: "openfreemap"|"local", ai_enabled: bool, env}`.

## Enum

| Nama | Nilai |
|---|---|
| `pipeline_status` | `new`, `contacted`, `demo`, `won`, `lost` |
| `lifecycle_status` | `open`, `closed` |
| `score_band` | `hot` (≥75), `warm` (55–74), `medium` (35–54), `cold` (<35) |
| `activity.type` | `created`, `status_change`, `contacted`, `note`, `opt_out`, `opt_in`, `rejected`, `system` |
| `map_mode` | `prospects`, `heatmap`, `whitespace`, `coverage` |
| `geocode_status` | `ok`, `needs_review`, `failed`, `manual` |

## 1. Halaman Inertia

| Rute | Komponen | Props |
|---|---|---|
| `GET /login` | `Auth/Login` | `{}` |
| `GET /` | `Map/Index` | `filter_options`, `hud`, `provinces_index`, `rejection_reasons`, `contact_rules` |
| `GET /pipeline` | `Pipeline/Index` | `view` (`kanban`\|`table`), `columns`, `filter_options`, `rejection_reasons`, `contact_rules` |
| `GET /dashboard` | `Dashboard/Index` | `kpis`, `funnel`, `penetration`, `top_categories`, `whitespace_top`, `activity_by_user`, `scheduler_health`, `digest` |
| `GET /impact` | `Impact/Index` | `assumptions`, `defaults`, `results` |
| `GET /master/data-sources` | `Master/DataSources` | `sources[]` |
| `GET /master/outlets` | `Master/Outlets` | `outlets` (paginasi), `filters`, `review_queue_count` |
| `GET /master/outlets/review` | `Master/OutletReview` | `queue[]` |
| `GET /master/categories` | `Master/Categories` | `categories[]` |
| `GET /scheduler` | `Scheduler/Runs` | `runs` (paginasi), `health` |
| `GET /master/settings` | `Master/Settings` | `settings` (aturan kontak, digest, sinkronisasi, skor, enrichment) |

Contoh:

```json
// GET / → Map/Index
{
  "filter_options": {
    "provinces": [{"id": 31, "name": "DKI Jakarta"}],
    "cities": ["Jakarta Pusat"],  // DISTINCT outlets.city
    "categories": [{"id": 1, "key": "cafe", "name": "Kafe"}],
    "pipeline_statuses": ["new","contacted","demo","won","lost"]
  },
  "hud": {"outlets": 190, "prospects_active": 9421, "new_7d": 312, "hot": 640},
  "provinces_index": [{"id": 31, "name": "DKI Jakarta", "lat": -6.2, "lng": 106.8}]
}

// GET /dashboard → Dashboard/Index
{
  "kpis": {"outlets": 190, "prospects_active": 9421, "new_7d": 312, "conversion_rate": 0.034, "deals_this_month": 7},
  // new_7d = first_seen_at ≥ now-7d AND is_baseline=false; conversion_rate = won_at≠null / contacted_at≠null (kumulatif); deals_this_month = won_at di bulan berjalan
  "funnel": {"found":9800,"contacted":900,"demo":120,"won":40,"lost":361},  // kumulatif dari contacted_at/demo_at/won_at; lost terpisah
  "penetration": [{"province_id":31,"province":"DKI Jakarta","outlets":5,"prospects":310,"won":3,"penetration":0.0097}],
  "top_categories": [{"category":"Kafe","count":1200,"avg_score":58}],
  "whitespace_top": [{"cell_id":"-6.20:106.80","lat":-6.2,"lng":106.8,"prospects":42,"nearest_outlet_m":1200,"province":"DKI Jakarta"}],
  "activity_by_user": [{"user_id":1,"name":"Ayu","contacted":14,"demo":3,"won":1}],
  "scheduler_health": {"last_run_at":"2026-10-24T18:00:00Z","status":"success","age_hours":6.2,"failed_last_7d":0},
  "digest": {"date":"2026-10-24","new_open":[{"id":1,"name":"…","score":78}],"hot":[],"follow_up":[]}
}
```

## 2. Autentikasi

| Endpoint | Body | Respons |
|---|---|---|
| `POST /login` | `{email, password, remember?}` | 302 ke `/`; 422 bila salah; 429 setelah 5 percobaan/menit |
| `POST /logout` | — | 302 ke `/login` |

## 3. Peta

### `GET /api/map/outlets`
Query: `province_id?`. Respons (kolumnar):
```json
{"count": 190, "ids":[1,2], "codes":["OUT-001"], "names":["Toko Sinar Jaya"], "lng":[106.8,112.7], "lat":[-6.2,-7.25], "province_ids":[31,35]}
```

### `GET /api/map/prospects`
Query: `bbox=west,south,east,north` (wajib), `zoom` (wajib, 0–22), `mode` (`prospects`|`heatmap`), filter: `province_id`, `city`, `category_id[]`, `score_min`, `score_max`, `rating_min`, `has_phone` (0/1; menghitung telepon simulasi juga), `pipeline_status[]`, `max_distance_m`, `new_open_only` (0/1), `include_closed` (0/1, default 0).

Respons `zoom >= 6` (`type: "points"`, maks 20.000; bila terpotong `truncated: true`):
```json
{"type":"points","count":3,"truncated":false,
 "ids":[101,102,103],"lng":[106.81,106.82,106.83],"lat":[-6.21,-6.22,-6.23],
 "score":[82,47,61],"status":[0,1,0],"flags":[1,0,2]}
```
`status`: indeks `pipeline_status` (0=new,1=contacted,2=demo,3=won,4=lost). `flags` bitmask: 1=baru buka, 2=simulasi, 4=tutup, 8=opt-out.

Respons `zoom < 6` (`type: "grid"`):
```json
{"type":"grid","cell_size_deg":0.5,"cells":[{"lng":106.5,"lat":-6.0,"count":812,"avg_score":54.2,"hot":40}]}
```
Header: `ETag`, `Cache-Control: private, max-age=30`.

### `GET /api/map/whitespace`
Query: `bbox`, filter kategori opsional. Sel 1 km dengan ≥ 15 prospek `open` dan **tidak ada outlet dalam 500 m**. Respons:
```json
{"cell_size_m":1000,"cells":[{"id":"-6.20:106.80","lng":106.80,"lat":-6.20,"prospects":42,"avg_score":57,"nearest_outlet_m":1200}]}
```

### `GET /api/search?q=`
Min. 2 karakter. Respons:
```json
{"outlets":[{"id":1,"name":"…","city":"…","lng":0,"lat":0}],
 "prospects":[{"id":5,"name":"…","category":"Kafe","city":"…","score":71,"lng":0,"lat":0}],
 "provinces":[{"id":31,"name":"DKI Jakarta","lng":0,"lat":0}]}
```
Maksimal 8 hasil per grup.

## 4. Prospek

### `GET /api/prospects/{id}` → `ProspectDetail`
```json
{
  "id": 101, "name": "Kopi Senja", "category": {"id":1,"key":"cafe","name":"Kafe"},
  "address": "Jl. Melati No. 4, Menteng, Jakarta Pusat", "address_source": "reverse_geocode",
  "lat": -6.21, "lng": 106.81,
  "phone": "0812-5555-0101", "phone_simulated": true, "whatsapp_url": "https://wa.me/6281255550101",
  "website": null,
  "rating": 4.6, "rating_simulated": true, "review_count": 312, "reviews_simulated": true,
  "data_updated_at": "2026-10-24T18:00:00Z", "first_seen_at": "2026-09-01T18:00:00Z",
  "score": 82, "score_band": "hot",
  "score_breakdown": [{"key":"category","label":"Kecocokan kategori","value":0.8,"points":20.0,"max":25,"reason":"Kafe: bobot 80"}],
  "nearest_outlet": {"id":3,"name":"Toko Sinar Jaya","distance_m":640},
  "neighbor_count_500m": 2, "area_density": 18,
  "pipeline_status": "new", "rejection_reason": null, "handled_by": null,
  "last_contacted_at": null, "last_contacted_by": null, "contact_count": 0,
  "do_not_contact": false,
  "badges": ["new_open","simulated"],
  "lifecycle_status": "open",
  "latest_note": null,
  "activities": [{"id":1,"type":"created","user":null,"body":"Ditemukan oleh sync","created_at":"…"}],
  "contact_warning": null
}
```
`badges` ⊂ {`new_open`, `simulated`, `closed`, `opt_out`}. `whatsapp_url` null bila tanpa nomor ponsel valid, `do_not_contact`, atau `phone_simulated = true` kecuali `settings.demo.allow_simulated_whatsapp` aktif (demo/E2E; nomor simulasi memakai rentang non-routable `0999-…`, UI menampilkan "Nomor simulasi"). Pesan ditambahkan klien dari composer. `website` hanya skema http/https (lainnya dibuang oleh Resource). `GET` ini tanpa panggilan eksternal; bila `address` null dan antrean `geocode_queue` pending, server menaikkan prioritasnya (tulis DB saja, D-42). `address` null ⇒ klien menampilkan koordinat + "Alamat belum tersedia".

### `POST /api/prospects/{id}/contact-check`
Body `{}`. Respons:
```json
{"allowed": true, "blocked_reason": null,
 "warning": {"type":"recent_contact","by":"Ayu","at":"2026-10-22T03:00:00Z","days_ago":2},
 "min_interval_violation": false,
 "phone_normalized": "6281255550101",
 "message": "Halo Kopi Senja, …",
 "template_category": "cafe", "override_count": 0, "max_overrides": 2}
```
`blocked_reason`: `do_not_contact` \| `no_phone` \| `invalid_phone` (mis. telepon rumah 021…) \| `closed` \| null. `override_count` dan `max_overrides` disertakan. `min_interval_violation` true ⇒ kontak butuh `override_reason`.

### `POST /api/prospects/{id}/contact`
Klien **membuka `wa.me` secara sinkron lebih dulu** (URL dibangun di klien dari `phone_normalized` + pesan hasil `contact-check`, agar tidak diblokir popup blocker), lalu memanggil endpoint ini (`fetch keepalive`) untuk mencatat. Body `{message: string(1..1000), override_reason?: string}`. Efek: aktivitas `contacted` (meta: pesan, nomor ternormalisasi), `last_contacted_*`, `contact_count++`, `handled_by = user`, status `new → contacted`. Respons 200 `{"prospect": ProspectDetail}`. 422 bila opt-out/tanpa nomor; 422 `min_interval` bila melanggar batas tanpa `override_reason`; 422 bila `override_count ≥ max_overrides`.

### `POST /api/prospects/{id}/status`
Body `{status, rejection_reason_id?}` (wajib bila `lost`). Respons `{prospect: ProspectDetail}`. Mencatat aktivitas `status_change`/`rejected`, `handled_by = user`.

### `POST /api/prospects/{id}/notes`
Body `{body: string(1..2000)}` → aktivitas `note` (catatan hidup di `prospect_activities`; tidak ada kolom notes). Respons `{activity}`.

### `POST /api/prospects/{id}/opt-out` / `DELETE /api/prospects/{id}/opt-out`
Menyetel/membatalkan `do_not_contact`, aktivitas `opt_out`/`opt_in`. Respons `{prospect}`.

### `POST /api/prospects/{id}/message-ai`
Hanya bila `app.ai_enabled`; 404 bila tidak. Body `{tone?: "formal"|"ramah"}`. Respons `{message}`. Rate limit 10/menit.

### `POST /api/prospects/bulk`
Body `{ids: int[1..200], action: "status"|"opt_out", status?, rejection_reason_id?}`. Respons `{updated: n, skipped: [{id, reason}]}`.

## 5. Pipeline

### `GET /api/pipeline/cards`
Query: filter yang sama dengan peta (tanpa bbox), `status`, `per_status` (default 50), `cursor`. Respons `{columns: {new: {total, cards: [Card]}, contacted: …}}`.
`Card`: `{id, name, category, city, score, score_band, phone_present, last_contacted_at, handled_by, badges, do_not_contact}`.

### `GET /api/pipeline/table`
Query: filter + `sort` (`score`, `last_contacted_at`, `name`), `dir`, `page`, `per_page` (≤100). Respons paginasi Laravel `{data:[Card], meta:{current_page,last_page,total,per_page}}`.

## 6. Rute Kunjungan

### `POST /api/routes/plan`
Body `{prospect_ids: int[2..30], start?: {lat, lng}}` (default titik awal = prospek pertama). Respons:
```json
{"order":[101,105,102],
 "stops":[{"id":101,"name":"…","lat":-6.2,"lng":106.8,"leg_km":0},{"id":105,"leg_km":1.4}],
 "total_km": 5.2, "estimate_note": "Estimasi garis lurus; jarak jalan sebenarnya lebih panjang.",
 "path": [[106.8,-6.2],[106.81,-6.21]],
 "google_maps_urls": ["https://www.google.com/maps/dir/?api=1&origin=-6.2,106.8&destination=-6.23,106.83&waypoints=-6.21,106.81"]}
```
Satu URL per ≤ 9 waypoint.

## 7. Dashboard, Ekspor, Estimasi

| Endpoint | Keterangan |
|---|---|
| `GET /prospects/export.csv` | Query: filter yang sama; kolom: id, nama, kategori, kota, provinsi, alamat, telepon, simulasi_telepon, rating, jumlah_review, skor, status, ditangani_oleh, terakhir_dihubungi, jarak_outlet_m. Sel yang diawali `= + - @` diberi awalan `'` (CSV injection) |
| `GET /dashboard/export.csv` | KPI + funnel + penetrasi per provinsi |
| `GET /api/digest?date=` | `digest` seperti di props dashboard |
| `GET /api/settings`, `PUT /api/settings` | Body/respons: `{contact:{warn_days,min_interval_days,max_overrides}, digest:{email_to,hot_threshold,follow_up_days}, sync:{max_outlets_per_run}, scoring:{params}, enrichment:{simulated}, demo:{allow_simulated_whatsapp}}`; validasi rentang; dipakai halaman `Master/Settings` |
| `PUT /api/settings/impact` | Body: `{outlets, google_syncs_per_month, google_groups_per_outlet, avg_pages, details_ratio, cost_per_1000_search, cost_per_1000_details, usd_idr, prospects_per_outlet, conv_contact_to_demo, conv_demo_to_won, deal_value_idr, hours_saved_per_week, hourly_cost_idr}` → `{assumptions, results}` |

`avg_pages` default 1 (Places API (New) `searchNearby` tanpa paginasi). `results`: `{google_calls_per_month, google_cost_usd, google_cost_idr, osm_cost_idr: 0, prospects_found, expected_deals, expected_revenue_idr, hours_saved_per_month}`. Perhitungan murni di `ImpactCalculator`; klien menampilkan hasil dan boleh mengubah asumsi lalu memanggil `PUT`.

## 8. Master

### Data Source
- `GET /api/master/data-sources` → `{sources: [{id,key,name,is_active,priority,has_key,key_hint:"…a1b2",monthly_quota,calls_this_month,cost_search_per_1000,cost_details_per_1000,last_run_at,last_status,last_error,config}]}` — **tidak pernah** memuat nilai key.
- `PUT /api/master/data-sources/{id}` Body: `{is_active?, priority?, api_key?, clear_key?, monthly_quota?, cost_search_per_1000?, cost_details_per_1000?, config?}` → sumber (tanpa key).
- `POST /api/master/data-sources/{id}/test` → `{ok, message, latency_ms}`; timeout 10 dtk; dibatasi laju (pengecualian eksplisit atas aturan "tanpa panggilan eksternal dari request", D-60).
- Google tidak bisa diaktifkan tanpa key (422).

### Outlet
- `GET /api/master/outlets?search=&province_id=&geocode_status=&page=` → paginasi `{data:[Outlet], meta}`.
- `POST /api/master/outlets`, `PUT /api/master/outlets/{id}`, `DELETE /api/master/outlets/{id}` (soft: `is_active=false`).
- `POST /api/master/outlets/import` multipart `file` (CSV ≤ 2 MB; kolom `code,name,address,city,province,lat,lng`) → `{created, updated, geocoded, needs_review, failed, errors:[{row,message}]}`. Geocoding by nama+kota dilakukan lewat antrean (job), bukan di request. Setelah impor, `ReconcileProspectsAction` mengecualikan prospek lama yang cocok dengan outlet baru (`closed_reason=hitachi_merchant`).
- `POST /api/master/outlets/{id}/retry-geocode` → antre ulang forward geocoding (status `failed`).
- `GET /api/master/outlets/review` → antrean `[ {outlet, candidates:[{display_name,lat,lng,score}]} ]`; `POST /api/master/outlets/{id}/resolve` Body `{candidate_index}` atau `{lat, lng}` → status `manual`.

### Kategori
- `GET/POST/PUT /api/master/categories` Body: `{key, name, weight(0..100), whatsapp_template, osm_filters?, is_active}`. Template wajib berisi placeholder valid.

### Riwayat Scheduler
`GET /api/scheduler/runs?page=` → `{data:[{id,command,status,started_at,finished_at,duration_ms,outlets_total,outlets_ok,outlets_failed,prospects_new,prospects_updated,prospects_closed,api_calls,error}], meta}`; `GET /api/scheduler/runs/{id}` menambah `outlets:[…]`.

## 9. Kode Status Umum

| Kode | Arti |
|---|---|
| 200/201 | Berhasil |
| 302 | Redirect (login/logout) |
| 401 | Tidak login (JSON) |
| 404 | Tidak ada / fitur nonaktif (AI) |
| 422 | Validasi atau aturan bisnis (opt-out, batas frekuensi) |
| 429 | Rate limit |
