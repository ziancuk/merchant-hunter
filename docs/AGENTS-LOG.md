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
