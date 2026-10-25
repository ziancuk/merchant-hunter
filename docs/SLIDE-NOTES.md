# SLIDE-NOTES — bahan slide siap pakai

Deadline: **Kamis 29 Oktober 2026, 23.59 WIB**. Pengumpulan lewat Google Form: <https://forms.gle/L51yb1FRY1dk5aTS9>. Kerangka deck: `FinalProject_NamaLengkap.pptx` (nama placeholder; ganti sendiri).

Lima isi yang diminta: (1) nama aplikasi + masalah Hitachi, (2) ringkasan PRD, (3) screenshot proses di Claude, (4) screenshot aplikasi berjalan, (5) link GitHub.

## 1. Nama aplikasi dan masalah

**POS Merchant Hunter** — "mission control" untuk menemukan calon merchant Hitachi POS.

Masalah (PRD §1): tim sales mencari calon merchant secara manual, tanpa pandangan spasial, tanpa skor prioritas, tanpa jejak siapa menghubungi siapa, dan tanpa angka nilai bagi manajemen.

Kalimat pembuka slide: *"Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan; dengan POS Merchant Hunter, jawabannya ada di peta."*

## 2. Ringkasan PRD (satu halaman)

- **Pengguna:** satu peran, "manajemen" (kepala cabang, manajer sales, analis), banyak akun.
- **Solusi:** peta 3D semua outlet; pencarian otomatis bisnis ≤ 2 km dari tiap outlet tiap malam (OpenStreetMap, Google opsional); skor 0–100 dengan alasan terbaca; pipeline Baru → Dihubungi → Demo → Deal/Ditolak; WhatsApp dengan template; rute kunjungan; dashboard; kalkulator dampak dan biaya.
- **Metrik sukses pilot 90 hari:** ≥ 25 prospek aktif per outlet; waktu riset sales turun ≥ 50%; 0 kontak ganda tanpa peringatan; konversi Dihubungi→Deal ≥ 3% (asumsi, bisa diedit); biaya data Rp0 (OSM) atau sesuai anggaran; ≥ 80% akun aktif mingguan.
- **Di luar lingkup v1:** pengiriman WhatsApp otomatis, integrasi langsung ke database Hitachi POS, banyak peran.
- **Risiko utama:** lisensi Google Places, batas Overpass, kualitas data OSM Indonesia, spam WhatsApp dan UU PDP — masing-masing punya mitigasi di PRD §8–9.

## 3. Poin nilai adopsi (untuk slide "Rencana Adopsi & Biaya")

1. **Data outlet asli:** impor CSV dari Hitachi POS (pilot), lalu endpoint sinkronisasi terautentikasi (tahap lanjutan). Tidak pernah mengakses database POS langsung.
2. **Biaya:** OSM + Mock = **Rp0**. Google dinonaktifkan secara bawaan; estimasi ±4.900 panggilan `searchNearby` + ±950 Place Details per bulan untuk 190 outlet sync mingguan — dihitung di halaman *Estimasi Dampak & Biaya* dengan tarif yang diisi sendiri (PRD tidak mengklaim harga resmi). Menyalakan Google hanya di kota pilot menurunkan biaya ±90%.
3. **Etika dan kepatuhan:** data bisnis publik, batas 1 kontak/14 hari per prospek, peringatan kontak ganda 7 hari, opt-out permanen, tanpa kirim otomatis, badge "Simulasi" pada semua data palsu.
4. **Pilot:** 1–2 provinsi, 10–20 outlet, 4–6 akun, 6–8 minggu; 1 VM kecil (2 vCPU, 4 GB), cron + worker antrean, HTTPS internal.
5. **Nilai terukur:** prospek per outlet, waktu hemat sales, konversi per tahap, penetrasi per provinsi.
6. **Mitigasi risiko adopsi:** libatkan sales sejak awal, tinjauan legal sebelum pilot, kuota Google per bulan, cache + mirror + opsi self-host untuk layanan gratis.

## 4. Fakta untuk slide hasil (semua sudah terverifikasi, lihat [TEST-REPORT.md](TEST-REPORT.md))

- 190 outlet dummy di 38 provinsi; ±14,6 ribu prospek simulasi; peta tetap memuat dalam ±2–4 detik pada data itu.
- Pencarian, kartogram penetrasi, white space, heatmap, rute, command palette, tema terang/gelap, responsif tablet/ponsel.
- Mutu: tes otomatis berlapis (Pest, Vitest, Playwright E2E + axe), audit keamanan dan pemindaian secret tanpa temuan kritis, refactoring terukur (ekspor CSV 10× lebih cepat, bundel awal ‑46%). Angka pasti ada di TEST-REPORT dan REFACTORING.
- Keterbatasan jujur: FPS di GPU nyata belum terukur; sync nyata ke Overpass/Google belum diuji dalam sesi ini; CI GitHub belum pernah dijalankan.

## 5. Urutan demo 3 menit

| Waktu | Layar | Yang dikatakan / dilakukan |
|---|---|---|
| 0:00–0:20 | Login → intro globe | "Globe terbang ke Indonesia." Tekan lewati intro sekali agar diingat. |
| 0:20–0:50 | Peta, mode Prospek | Ribuan titik; marker outlet beda dari prospek; hover outlet → cincin 2 km. Filter provinsi + skor ≥ 75. |
| 0:50–1:20 | Klik prospek panas (drawer) | Rincian skor, jarak ke outlet, efek tetangga, badge Simulasi. Tekan WhatsApp: pesan terisi template, aktivitas tercatat. |
| 1:20–1:40 | Mode White Space + Heatmap | Area padat bisnis tanpa outlet Hitachi. |
| 1:40–2:00 | Pipeline Kanban | Seret kartu Dihubungi → Demo; peringatan kontak ganda dari akun lain; opt-out. |
| 2:00–2:15 | Perencana rute | Pilih 4–5 prospek → urutan, jarak, buka di Google Maps. |
| 2:15–2:40 | Dashboard + Estimasi Dampak & Biaya | KPI, funnel, penetrasi provinsi; ubah asumsi Google vs OSM Rp0. |
| 2:40–3:00 | Master Data Source + Riwayat Scheduler | Key terenkripsi tidak pernah tampil; kesehatan scheduler; penutup + link GitHub. |

Cadangan bila internet mati: jalankan dengan `HUNTER_BASEMAP=local` (basemap lokal) — data tetap dari seed offline.

## 6. Screenshot aplikasi (sudah ada di `docs/screenshots/`)

| Berkas | Slide | Caption saran |
|---|---|---|
| `01-peta-penuh.png` | Hasil | "Peta 3D: outlet Hitachi dan ribuan prospek berwarna menurut skor." |
| `02-intro-globe.png` | Hasil | "Intro sinematik: globe terbang ke Indonesia." |
| `03-drawer-prospek.png` | Hasil / Fitur | "Kartu prospek: skor 84 beserta rincian alasan, jarak, efek tetangga." |
| `04-kanban.png` | Fitur | "Pipeline Kanban drag & drop dengan catatan aktivitas." |
| `05-dashboard.png` | Hasil | "Dasbor manajemen: KPI, funnel, penetrasi provinsi." |
| `06-white-space.png` | Fitur | "White Space: padat bisnis, belum ada outlet Hitachi." |
| `07-rute.png` | Fitur | "Rute kunjungan efisien (nearest-neighbor + 2-opt)." |
| `08-master-data-source.png` | Fitur | "Master Data Source: API key terenkripsi, uji koneksi, kuota." |
| `09-estimasi-biaya.png` | Adopsi | "Estimasi dampak & biaya dengan asumsi eksplisit." |
| `10`–`15` | Cadangan | heatmap, command palette, composer WhatsApp, tema terang, ponsel, riwayat scheduler |

## 7. Screenshot proses di Claude — ambil manual (tidak bisa diambil agen)

Ambil dari layar Claude Code milik Anda sendiri. Saran urutan dan caption:

1. **Prompt awal** — layar saat Anda mengetik "Baca PROMPT.md dan kerjakan Sesi 1". *Caption:* "Spesifikasi lengkap ditulis sekali di PROMPT.md; tiap sesi mengikuti protokol."
2. **Riset paralel (Sesi 1)** — daftar 4 subagen (Spike-Peta, Riset-Tile, Riset-Overpass, Riset-Alamat). *Caption:* "Empat subagen riset read-only bekerja paralel."
3. **Reviewer-Dokumen** — laporan 19 temuan (2 kritis). *Caption:* "Review independen menemukan 'white space mustahil' sebelum satu baris kode pun ditulis."
4. **Task list / Linked sessions** — tampilan tiga agen worktree (BE-Provider-Scheduler, BE-Domain, FE-Design-System). *Caption:* "Tiga agen penulis di worktree terpisah; orkestrator merge dan memverifikasi."
5. **Merge dan tes hijau** — keluaran `composer ci` (Pest 435, Vitest 289) dan `npm run e2e` (42 lulus). *Caption:* "Setiap milestone ditutup dengan tes, lint, dan build hijau."
6. **Temuan QA-Browser** — laporan bug dengan langkah reproduksi. *Caption:* "QA eksploratori di browser menemukan 1 bug mayor dan 14 minor; semuanya dicatat."
7. **Refactoring terukur** — tabel sebelum/sesudah di REFACTORING.md. *Caption:* "Ekspor CSV 0,80 → 0,08 dtk; indeks komposit; bundel awal 525 → 284 kB."
8. **AGENTS-LOG.md** — tabel fase/subagen/hasil. *Caption:* "Seluruh proses terdokumentasi: siapa mengerjakan apa dan masalahnya."
9. **Riwayat git** — `git log --oneline --graph` memperlihatkan merge `--no-ff` per milestone. *Caption:* "Alur branch → PR-note → merge; tag v0.1…v1.0.0."

Bahan ringkas untuk slide "Proses Pengerjaan": ringkasan tiap sesi ada di [AGENTS-LOG.md](AGENTS-LOG.md); catatan PR di `docs/pr/`.

## 8. Checklist pengumpulan

- [ ] Ganti `NamaLengkap` pada nama berkas PPTX dan teks "Nama Peserta" di slide judul.
- [ ] Tempel screenshot proses (bagian 7) pada kotak placeholder slide "Proses Pengerjaan".
- [ ] Push ke GitHub (langkah di README), repo public atau tambahkan trainer sebagai collaborator; tempel link di slide penutup.
- [ ] Pastikan tab Actions hijau (CI belum pernah dijalankan di GitHub).
- [ ] Kirim lewat Google Form sebelum Kamis 29 Oktober 2026 pukul 23.59 WIB.
