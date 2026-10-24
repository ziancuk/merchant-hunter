# SPIKE M3.0 — MapLibre 6 + deck.gl 9.4 (hasil, 2026-10-24)

Untuk dipindahkan orkestrator ke `docs/DECISIONS.md` (menggantikan/menutup D-11, D-12, D-13, D-16, D-17).

## Cara uji

- Harness mandiri (esbuild, tanpa Vue) memuat `maplibre-gl@6.15.0` + `@deck.gl/maplibre@9.4.0` (`MapLibreOverlay`), layer `ColumnLayer`, `ScatterplotLayer`, `HeatmapLayer`, `HexagonLayer`, 10.000 titik, matriks proyeksi (`globe`, `mercator`) x mode overlay (`interleaved: true|false`).
- Chrome 64-bit (aplikasi Google Chrome terpasang) `--headless` via Playwright dengan flag `--use-gl=angle --use-angle=swiftshader --enable-unsafe-swiftshader --ignore-gpu-blocklist`. WebGL2 tersedia; tangkapan layar diperiksa manual (piksel nyata, bukan hanya "tanpa error").
- Style dasar lokal (tanpa jaringan) dan style OpenFreeMap `dark` asli (jaringan tersedia saat uji).

## Hasil

| Layer | mercator, interleaved | mercator, overlaid | globe, interleaved | globe, overlaid |
|---|---|---|---|---|
| `ScatterplotLayer` 10k | OK | OK | OK (posisi benar sampai tepi bola) | OK |
| `ColumnLayer` 3D 10k | OK (ekstrusi, pencahayaan benar) | OK | OK (zoom 3,2 dekat tepi bola dan zoom 9,5 miring) | OK |
| `HeatmapLayer` | **OK** | OK | **KOSONG** (tidak tergambar) | **KOSONG** |
| `HexagonLayer` | OK (kolom heksagon) | OK | **KOSONG / artefak** (beberapa piksel acak) | tidak diuji ulang |

Waktu inisialisasi overlay sampai `idle`: interleaved 0,3 s (mercator) / 1,1 s (globe, 14k titik); overlaid <2 ms (kanvas terpisah, gambar menyusul).

Catatan teknis (berlaku untuk semua keputusan di bawah):

1. `maplibre-gl@6` **tidak punya ekspor default**: gunakan `import * as maplibregl from 'maplibre-gl'`.
2. Opsi konstruktor `projection` **tidak berlaku**; globe diaktifkan dengan `map.setProjection({ type: 'globe' })` setelah event `load` (sejalan dengan D-25). `map.getProjection()` mengembalikan `{ type }`.
3. `antialias` kini lewat `canvasContextAttributes: { antialias: true }` (opsi `antialias` lama diabaikan; D-16 disesuaikan).
4. Worker MapLibre 6 berupa modul ES terpisah: wajib `setWorkerUrl(...)`. Di Vite: `import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'` (D-14 terbukti bekerja di dev dan di build produksi).
5. `setMissingStyleImageResolver` (bukan event `styleimagemissing`) dipakai untuk menjinakkan ikon sprite yang tidak ada di style OpenFreeMap (`circle-11`).
6. deck.gl: data biner (`{ length, attributes: { getPosition: { value, size: 2 }, ... } }`) bekerja untuk semua layer yang dipakai; atribut warna Uint8 perlu `normalized: true` agar tidak ada peringatan "Attribute ... is normalized".
7. `HeatmapLayer` mencetak peringatan luma.gl `Binding weightsTexture not set` dan `ColumnLayer`/`HexagonLayer` peringatan `layout for attribute "positions" not present in buffer layout`; keduanya **peringatan** (bukan error), gambar benar. Skrip E2E sebaiknya hanya menggagalkan `console.error`.
8. Klik: callback `onClick` per-layer deck.gl tidak menembak pada mode interleaved di uji headless ini (klik sintetis Playwright; hover bekerja; penyebab tidak diisolasi lebih jauh). Solusi yang terbukti: dengarkan `map.on('click')` lalu `overlay.pickObject({ x, y, radius })` (dipakai di `engine.ts`, bekerja di kedua mode).
9. Style lokal minimal (background gelap + graticule GeoJSON inline 5 derajat, tanpa sprite/glyph/URL) tergambar tanpa jaringan.
10. OpenFreeMap `dark` memuat dan terbaca (abu-abu gelap); gambar ikon `wood-pattern`/`circle-11` tidak ada (diatasi poin 5).

## Keputusan

- **D-11 (kolom 3D di globe atau mercator)**: intro memakai **globe MapLibre murni** (tanpa deck.gl). Begitu fly-to Indonesia selesai (`moveend`), proyeksi diganti ke **mercator** lalu overlay deck.gl dipasang (`interleaved: true`). Alasan: `ColumnLayer`/`ScatterplotLayer` sebenarnya benar di globe, tetapi `HeatmapLayer` dan `HexagonLayer` kosong; satu aturan sederhana ("deck.gl hanya di mercator") menghindari kombinasi yang tidak terverifikasi (picking, pulsa, rute). Tidak ada lonjakan visual yang mengganggu pada zoom ~4 (diperiksa lewat urutan tangkapan layar).
- **D-13 (heatmap)**: pakai **`HeatmapLayer` deck.gl** (data biner + bobot skor) karena di mercator bekerja dan menghindari konversi 20.000 titik ke GeoJSON setiap `moveend`. Heatmap native MapLibre tidak diperlukan. Bila suatu hari globe permanen dipilih, ganti ke layer `heatmap` native.
- **Mode overlay**: `interleaved: true` (satu konteks WebGL; urutan layer benar dengan basemap). Overlaid tidak lebih cepat (uji FPS di `PERF.md`).
- **D-17 (flag Playwright)**: flag SwiftShader di atas terbukti bekerja; selain itu E2E perlu `executablePath`/channel Chrome bila browser bawaan Playwright belum diunduh (`npx playwright install chromium`). `?intro=0&motion=0`: dihormati di env non-production (lihat keputusan di Index/`params.ts`).
- **D-23/D-20**: fallback basemap diuji nyata: dengan `tiles.openfreemap.org` diblokir, banner "Basemap tidak tersedia" muncul <= ~6 dtk dan style lokal dipakai; dengan `HUNTER_BASEMAP=local` dan seluruh host eksternal diblokir, peta termuat penuh (hanya Google Fonts dari layout yang gagal; itu bukan bagian peta).
