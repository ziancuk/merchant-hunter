# tools/deck — pembuat deck PPTX Final Project

Menghasilkan `FinalProject_NamaLengkap.pptx` (12 slide, 16:9, Bahasa Indonesia) di root repo.
Isi bersumber dari `docs/SLIDE-NOTES.md`, `PRD.md`, `ARCHITECTURE.md`, `AGENTS-LOG.md`, `REFACTORING.md`, `TEST-REPORT.md`; gambar dari `docs/screenshots/`.

## Menjalankan ulang

Dependensi dipasang DI LUAR repo agar `package.json`/`composer.json` tidak berubah:

```sh
mkdir -p /private/tmp/claude-501/deck-deps && cd /private/tmp/claude-501/deck-deps && npm init -y && npm i pptxgenjs
node tools/deck/build-deck.mjs        # dari root repo
```

Variabel opsional: `DECK_DEPS` (lokasi node_modules pptxgenjs), `DECK_SCRATCH` (salinan JPEG screenshot ≤1600 px, dibuat dengan `sips`/macOS), `DECK_OUT` (berkas keluaran).

## Font

Identitas aplikasi memakai Bricolage Grotesque, Instrument Sans, dan JetBrains Mono (docs/DESIGN.md). Font itu tidak umum terpasang di PowerPoint, jadi deck memakai fallback aman: Arial (judul dan teks) dan Courier New (label HUD/angka). Untuk memakai font asli, pasang font di komputer penyaji lalu ubah objek `F` di `build-deck.mjs`.

## Yang diisi pemilik manual

Nama peserta (slide 1 dan 12), tiga kotak screenshot proses Claude (slide 7), `USERNAME` pada link GitHub (slide 12), dan nama berkas PPTX.

## Deck presentasi manajemen (28 slide)

`build-presentation.mjs` menghasilkan `POS-Merchant-Hunter_Presentasi-Manajemen.pptx` (transisi fade, agenda dan footer bertaut, speaker notes). Gambar berasal dari JPEG di folder `DECK_JPG`: 7 screenshot aplikasi (`01-peta`, `03-drawer`, `04-kanban`, `05-dashboard`, `07-rute`, `07-laporan`, `10-heatmap`) dan 4 cuplikan berkas asli (`ev-prompt`, `ev-prd`, `ev-agents`, `ev-git`). Variabel: `DECK_NAME` (nama peserta), `DECK_OUT`, `DECK_DEPS`. Ekspor PDF dilakukan manual dari PowerPoint (File → Export → PDF).
