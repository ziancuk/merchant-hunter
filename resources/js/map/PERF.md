# PERF — peta dengan 14.639 prospek (data seed), diukur 2026-10-24

Lingkungan: macOS arm64, Google Chrome headless dengan **SwiftShader** (WebGL di CPU; `--use-angle=swiftshader`), viewport 1440x900, `php artisan serve` (tanpa gzip, 1 proses) dan SQLite. Angka SwiftShader **jauh lebih lambat dari GPU nyata**; dipakai sebagai batas bawah dan untuk perbandingan relatif, bukan sebagai klaim FPS perangkat pengguna. Skrip pengukur ada di scratchpad (Playwright, `requestAnimationFrame` selama pan/zoom 3 dtk terprogram, `easeTo` linear).

## Endpoint (14.639 prospek aktif, 190 outlet)

| Permintaan | Ukuran mentah | gzip | Waktu server (5 kali) | Catatan |
|---|---|---|---|---|
| `GET /api/map/prospects` bbox nasional, `zoom=6` (semua titik) | 479.971 B | ~153.700 B | 114–176 ms | respons kolumnar; 14.639 titik, tidak terpotong (batas 20.000) |
| `GET /api/map/prospects` `zoom=4` (grid) | 2.343 B | ~1 KB | 99–118 ms | `type: grid`, sel 1 derajat |
| viewport kota (Jakarta, zoom 11) | 12.176 B | ~4 KB | 125–603 ms (dingin) | 368 titik |
| `GET /api/map/whitespace` nasional | 7.755 B | ~2 KB | 73–108 ms | 76 sel |
| `GET /api/map/outlets` | 10.846 B | ~3 KB | 73–81 ms | 190 outlet, dimuat sekali |

Ambang TEST-PLAN (<= 500 ms dan <= 1 MB gzip per viewport): terpenuhi (puncak 154 KB gzip). Tidak ada N+1 di sisi klien: satu permintaan per `moveend` (debounce 250 ms, permintaan lama dibatalkan dengan `AbortController`); nama tooltip diambil malas satu per prospek (di-cache 600 entri) dan tidak ikut memblokir interaksi.

## Klien

| Tahap | Waktu |
|---|---|
| `JSON.parse` + array bertipe (14.639 titik) | 2–8 ms |
| turunan atribut GPU (warna, tinggi, bobot, subset baru-buka/simulasi) | < 5 ms (satu kali per respons) |
| Peta siap (`load`, build produksi, basemap OpenFreeMap) sampai angka HUD pertama | 2,0–2,5 s (4,2 s pada muatan pertama yang dingin) |
| Muatan: chunk `app` 391 kB (134 kB gzip), **chunk `engine` (MapLibre + deck.gl) 2,0 MB (553 kB gzip), dimuat malas hanya di halaman peta**, worker MapLibre 517 kB | |

Memori (6 siklus Peta -> halaman lain -> Peta, GC dipaksa): kanvas tetap 1 di DOM; heap JS +~1,5 MB per siklus. Harness terpisah (hanya maplibre): +0,08 MB/siklus; dengan overlay deck.gl: +1,4 MB siklus pertama lalu ~0,3–0,6 MB/siklus. Sisa pertumbuhan berasal dari modul deck.gl/luma.gl (registri perangkat), bukan dari objek kita (`overlay.finalize()`, `map.remove()`, pelepasan listener, `AbortController`, `cancelAnimationFrame`, dan `ResizeObserver.disconnect()` semuanya dijalankan; tidak ada kanvas yatim).

## FPS pan/zoom di SwiftShader (angka rAF, median bingkai)

Skenario realistis (data nyata di viewport):

| Skenario | Titik di viewport | Pan | Zoom |
|---|---|---|---|
| Nasional zoom 6,3, cakram datar | 3.075 | 9,0 fps (117 ms) | 9,2 fps |
| Jawa zoom 9,2, kolom 3D miring 55 derajat | 394 | 12,7 fps (83 ms) | 13,6 fps |
| Jakarta zoom 12, kolom 3D miring 55 derajat | 368 | 11,6 fps | 12,9 fps |

Skenario beban-puncak 14.639 titik sekaligus (kamera nasional zoom 4,3, `loadAt` + `freezeLoads` hook dev), dengan dan tanpa `backdrop-filter` CSS panel kaca:

| Skenario | blur ON (pan / zoom) | blur OFF (pan / zoom) |
|---|---|---|
| E: dasar, grid ~40 gelembung | 12,0 / 12,2 fps | 26,0 / 26,9 fps |
| F: 14.639 cakram | 4,0 / 4,2 fps | 4,8 / 4,8 fps |
| G: 14.639 kolom 3D, pitch 50 | 2,6 / 3,0 fps | 3,1 / 3,1 fps |
| H: heatmap 14.639 titik | 11,8 / 11,7 fps | 17,4 / 16,9 fps |

Pembanding harness mandiri (deck.gl saja, tanpa UI/HUD): 100 titik = 59,7 fps; 14.639 titik `ScatterplotLayer` = 5,7–6,0 fps, sama untuk data objek/biner, `Float32` vs `Float64`, `pickable` on/off, interleaved vs overlaid. Jadi biaya 14,6 ribu instans adalah biaya rasterisasi CPU SwiftShader, bukan JS, unggah data, atau kode kita. `backdrop-filter` panel kaca menyumbang ~50% dari waktu bingkai dasar di SwiftShader (12 vs 26 fps).

Interpretasi jujur terhadap ambang TEST-PLAN (FPS >= 40 di SwiftShader): **tidak tercapai** pada beban puncak 14,6 ribu titik bersamaan (4–5 fps) dan juga tidak pada pemandangan realistis (9–13 fps) karena dasar SwiftShader + blur sudah 12 fps. Di GPU nyata beban yang sama adalah ratusan ribu instans/detik dan umumnya 60 fps; **tidak diverifikasi** (tidak ada perangkat GPU nyata di sesi ini). Rekomendasi untuk E2E: ukur dan catat angka, jangan jadikan 40 fps sebagai asersi lulus/gagal di SwiftShader; gunakan ambang relatif (mis. >= 2 fps pada 14,6 ribu titik, tidak ada bingkai > 1 dtk).

Mitigasi yang sudah ada: respons grid di bawah zoom 6, batas 20.000 titik dari server, kolom 3D hanya mulai zoom 9 (di bawahnya cakram datar), objek `data` deck.gl di-memo (tidak diunggah ulang saat denyut "baru buka" ~20 kali/dtk), `?motion=0` dan `prefers-reduced-motion` mematikan denyut/animasi.
