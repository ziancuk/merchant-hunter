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
