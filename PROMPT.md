# PROMPT.md — POS Merchant Hunter

> Cara pakai: di tiap sesi baru Claude Code, buka folder ini lalu katakan:
> "Baca PROMPT.md dan kerjakan Sesi N" (lihat bagian 10 untuk pembagian sesi).

Kamu adalah engineer senior full-stack sekaligus ORKESTRATOR tim subagent. Bangun aplikasi di bawah ini dari nol sampai jadi di folder kerja saat ini (berisi PROMPT.md ini; git repo sudah di-init).

# 1. KONTEKS

Aplikasi: "POS Merchant Hunter".
Final project training Claude Code (Hacktiv8) untuk PT Hitachi Channel Solutions Indonesia. Tema wajib: "Create something that will be adopted for PT Hitachi Channel Solutions Indonesia". Deadline: Kamis 29 Oktober 2026 pukul 23.59 WIB. Yang dikumpulkan: satu slide deck berisi (1) nama aplikasi + masalah Hitachi yang diselesaikan, (2) ringkasan PRD, (3) screenshot proses pengerjaan di Claude, (4) screenshot aplikasi yang sudah berjalan, (5) link GitHub repo yang bisa diakses trainer. Slide deck menjadi bahan pertimbangan adopsi teknologi di Hitachi, jadi tampilan, kelengkapan, dan dokumentasi sama pentingnya dengan kodenya.

Masalah nyata: Hitachi POS adalah aplikasi POS (kasir + back-office). Tim sales dan manajemen butuh cara sistematis menemukan calon merchant baru. Lokasi merchant/outlet Hitachi POS yang ada sudah diketahui. Aplikasi ini memetakan semua outlet di peta 3D interaktif, otomatis mencari bisnis potensial dalam radius maksimal 2 km dari tiap outlet, memberi skor prospek, dan memudahkan menghubungi mereka lewat WhatsApp.

Pengguna: hanya SATU role, "manajemen". Boleh banyak akun login, semuanya role yang sama, semuanya boleh melihat peta, prospek, pipeline, dan dashboard.

# 2. ATURAN KERAS

- Jangan memakai, menyalin, atau meniru kode/data apa pun dari proyek Hitachi POS yang asli. Semua data outlet adalah DUMMY dari seeder. Jangan menggunakan logo atau aset merek Hitachi; cukup teks.
- Jangan hardcode API key/secret. Gunakan .env atau pengaturan terenkripsi di database. Sediakan .env.example. Jangan commit .env, database lokal, atau cache.
- Field hasil simulasi (rating, jumlah review, nomor telepon palsu) wajib ditandai di database (kolom sumber/flag) dan di UI diberi badge "Simulasi" yang halus tapi jelas. Jangan pernah menampilkan data simulasi seolah nyata.
- Kepatuhan Google Places: untuk provider Google, simpan permanen hanya place_id; data lain diperlakukan sebagai cache berkedaluwarsa (lat/lng maks. 30 hari), di-refresh lewat place_id. Dokumentasikan di TRD sebagai batasan lisensi.
- Bahasa: dokumen di docs/ dan teks UI dalam Bahasa Indonesia; identifier kode, pesan commit, dan komentar kode dalam Bahasa Inggris.
- Jangan berhenti bertanya kecuali benar-benar terblokir. Keputusan kecil: pilih default terbaik, catat di docs/DECISIONS.md (keputusan, alasan, alternatif).
- Commit per fase/milestone dengan conventional commits. Jangan push ke remote (pemilik repo yang push).
- Hosting repo adalah GitHub, BUKAN GitLab. Jangan menambahkan remote GitLab, file CI GitLab (.gitlab-ci.yml), atau tautan GitLab di dokumen. Bila perlu CI, gunakan GitHub Actions (.github/workflows).
- Jangan klaim "sudah jalan" sebelum benar-benar dijalankan dan diverifikasi.
- Semua fitur di bagian 4 WAJIB dibangun. Gunakan URUTAN BUILD (bagian 9) agar tiap milestone selalu dalam kondisi bisa didemo.

# 3. STACK (SUDAH DIPUTUSKAN)

- Backend: Laravel versi stabil terbaru yang kompatibel dengan PHP terpasang (cek `php -v`; target Laravel 11 atau 12).
- Frontend: Inertia.js + Vue 3 (Composition API, script setup, TypeScript bila memungkinkan) + Vite + Tailwind CSS.
- Peta: lakukan spike singkat lalu pilih yang paling stabil, catat di DECISIONS.md:
  A) MapLibre GL JS (versi terbaru yang mendukung globe projection) + deck.gl (kolom 3D, hexagon, heatmap, arc), jalur utama.
  B) Three.js untuk intro globe, lalu transisi ke MapLibre + deck.gl.
  Basemap harus tanpa API key berbayar (mis. OpenFreeMap atau CARTO dark; verifikasi ketersediaan dan syarat atribusi sebelum dipakai; tampilkan atribusinya).
- Database: SQLite default (mudah dijalankan), kompatibel MySQL/PostgreSQL. Jarak dengan bounding box + haversine, tanpa PostGIS.
- Queue/Scheduler: Laravel scheduler + queue (driver database), job dengan retry dan backoff.
- Test: Pest atau PHPUnit (backend), Vitest (logika frontend penting). Lint: Laravel Pint, ESLint.

# 4. FITUR LENGKAP (SEMUA DIBANGUN)

A. Autentikasi
- Login/logout, banyak akun, satu role "manajemen". Seeder akun demo (kredensial dummy hanya di README). Semua halaman di balik auth.

B. Peta 3D (halaman utama)
- Intro sinematik: globe berputar pelan lalu terbang ke Indonesia, transisi mulus ke peta. Tombol "lewati intro"; ingat preferensi di localStorage.
- Tampilkan semua outlet Hitachi (marker bercahaya yang jelas beda dari prospek) dan ribuan titik prospek (kolom/titik 3D, warna menurut skor atau status).
- Cincin radius 2 km di sekitar outlet yang dipilih/di-hover.
- Klik prospek membuka drawer kartu: nama bisnis, kategori, alamat, telepon, rating, jumlah review, terakhir diperbarui, skor + rincian alasan, jarak ke outlet Hitachi terdekat, jumlah outlet Hitachi dalam 500 m ("efek tetangga"), status pipeline, riwayat aktivitas, badge "Baru buka"/"Simulasi"/"Tutup".
- Alamat kosong (sering terjadi pada data OSM): tampilkan alamat dari reverse geocoding yang di-cache (Nominatim, patuhi rate limit dan dilakukan di scheduler, bukan saat request pengguna); bila tetap kosong, tampilkan koordinat + teks "Alamat belum tersedia" dengan desain yang rapi.
- Tombol WhatsApp aktif hanya bila ada nomor: tautan wa.me dengan nomor dinormalisasi ke format 62..., pesan terisi dari template per kategori yang bisa diedit sebelum dikirim. Opsional "Buat pesan dengan AI" via Anthropic API (model terbaru yang tersedia) bila ANTHROPIC_API_KEY diisi; bila kosong, tombol disembunyikan. Klik WhatsApp otomatis mencatat aktivitas "dihubungi" dan mengubah status.
- Filter panel (kaca/glassmorphism, ringkas): provinsi, kota, kategori, rentang skor, rating minimum, punya telepon, status pipeline, jarak maksimum, "baru buka saja".
- Mode peta: Prospek (titik), Heatmap kepadatan, White Space (padat bisnis tapi belum ada outlet Hitachi), Cakupan outlet (radius).
- Command palette (Ctrl/Cmd+K): cari outlet/prospek/provinsi, lompat ke lokasi, aksi cepat.
- Performa: mulus dengan minimal 10.000 titik (layer GPU/instancing, pemuatan per viewport atau clustering sisi server, debounce). Tidak boleh ada N+1.

C. Pipeline prospek
- Status: Baru → Dihubungi → Demo → Deal / Ditolak. Tampilan Kanban (drag & drop) dan tabel, dengan bulk action.
- "Ditangani oleh" otomatis dari akun yang beraksi; catatan aktivitas lengkap (siapa, kapan, apa).
- Peringatan cegah dobel kontak: bila prospek sudah dihubungi akun lain dalam N hari, tampilkan siapa dan kapan sebelum mengirim lagi.
- Catatan teks per prospek; alasan penolakan terstandar.
- Etika outreach: batas frekuensi kontak per prospek, dan status/opsi "Jangan hubungi lagi" (opt-out) yang menyembunyikan tombol WhatsApp dan mengecualikannya dari rekomendasi.

D. Perencana rute kunjungan
- Pilih beberapa prospek; sistem menyusun urutan kunjungan efisien (nearest-neighbor + perbaikan 2-opt), menampilkan garis rute di peta dengan estimasi jarak, dan tombol membuka rute multi-waypoint di Google Maps lewat URL (tanpa API key).

E. Dashboard manajemen (bergaya sama dengan peta, BUKAN template admin biasa)
- KPI: total outlet, prospek aktif, prospek baru 7 hari, tingkat konversi, deal bulan ini.
- Funnel pipeline, cakupan/penetrasi per provinsi (choropleth atau visual unik), kategori teratas, area white space teratas, aktivitas per akun, kesehatan scheduler.
- Ringkasan harian (daily digest) dalam aplikasi: prospek baru buka, prospek panas, yang perlu follow-up. Opsional kirim email bila MAIL dikonfigurasi.
- Ekspor CSV (prospek terfilter, dashboard).
- Halaman "Estimasi Dampak & Biaya": kalkulator sederhana (jumlah outlet × frekuensi sync → perkiraan jumlah panggilan dan biaya bila Google aktif, versus Rp0 untuk OSM), dan proyeksi nilai (prospek ditemukan per outlet, asumsi konversi yang bisa diubah). Semua asumsi harus eksplisit dan bisa diedit.

F. Master & pengaturan
- Master Data Source: daftar penyedia dengan status aktif/nonaktif, prioritas, API key terenkripsi, "Uji koneksi", info kuota/perkiraan biaya, waktu run terakhir. Penyedia: OSMProvider (Overpass), MockProvider (simulasi deterministik), GoogleProvider (aktif hanya bila key diisi dan diaktifkan).
- Master Outlet (CRUD + impor CSV) dengan geocoding by nama + kota lewat provider aktif, skor kecocokan, dan antrean verifikasi manual untuk hasil meragukan. Simpan place_id.
- Master Kategori target (bobot skor, template pesan WhatsApp per kategori).
- Riwayat Scheduler: tiap run, durasi, jumlah prospek baru/diperbarui/ditutup, error.

G. Scheduler malam
- Command `hunter:sync` dijadwalkan tiap malam (mis. 01:00 WIB): per outlet aktif, ambil bisnis dalam radius 2 km lewat provider aktif sesuai prioritas; dedupe (provider + place_id; kecualikan bisnis yang sudah merchant Hitachi via jarak + fuzzy name); upsert prospek; deteksi "baru buka" (muncul setelah baseline pertama); tandai "tutup" bila tidak muncul beberapa run berturut-turut atau sumber menyatakan tutup permanen.
- Hormati batas Overpass: antrean, jeda antar request, retry dengan backoff, cache respons. Jangan pernah memanggil API eksternal dari request pengguna.
- Command pendukung: `hunter:geocode-outlets`, `hunter:score`, `hunter:seed-prospects --offline`, `hunter:demo-reset`.

H. Skor prospek (rumus didokumentasikan di TRD; rincian alasan tampil di UI)
- Gabungan: kecocokan kategori (bobot master), rating + jumlah review, kedekatan ke outlet Hitachi (efek tetangga), kepadatan area, kelengkapan kontak, sinyal "baru buka". Skor 0-100 dengan pita warna.

I. Data demo
- Seeder 38 provinsi × 5 outlet dummy = 190 outlet, di koordinat nyata kota-kota provinsi tersebut (nama merchant fiktif tapi realistis, bukan merek asli) agar pencarian OSM menemukan bisnis sungguhan di sekitarnya.
- `hunter:seed-prospects --offline` memakai MockProvider penuh (tanpa internet) agar demo selalu bisa jalan walau Overpass lambat/mati. Run online: nama, kategori, koordinat, alamat, telepon/website dari OSM bila ada; rating, jumlah review, dan telepon kosong diisi MockProvider dengan flag "Simulasi".

# 5. DESAIN & UX (WAJIB; BUKAN TEMPLATE DASHBOARD BIASA)

- Konsep: "mission control / radar" untuk tim sales. Dark-first dengan opsi light, palet berkarakter (hindari biru-ungu default dan kartu putih generik), satu warna aksen kuat untuk prospek panas, tipografi berkarakter (display + mono untuk angka dari Google Fonts).
- Peta mengisi seluruh layar; UI mengambang di atasnya (panel kaca, drawer geser, HUD angka di sudut), bukan sidebar + header + grid kartu standar.
- Animasi bermakna: intro globe, fly-to saat memilih provinsi/outlet, denyut pada prospek "baru buka", transisi drawer, hitung naik pada KPI. Hormati prefers-reduced-motion.
- Interaksi kaya: hover tooltip, klik untuk fokus, drag Kanban, shortcut keyboard, command palette, state loading/empty/error yang dirancang.
- Responsif sampai tablet, layak di ponsel; aksesibilitas dasar (kontras, fokus keyboard, label ARIA).
- Rancang sistem desain dulu di docs/DESIGN.md (token warna, tipografi, spasi, komponen) dan konsisten memakainya. Bila skill/alat desain tersedia (mis. frontend-design), gunakan.

# 6. ALUR FASE (BERURUTAN; TIAP FASE ADA KELUARAN DAN COMMIT)

Fase 0 — Persiapan: cek lingkungan (php, composer, node, npm, git), buat docs/DECISIONS.md dan docs/AGENTS-LOG.md, buat task list yang melacak seluruh fase.

Fase 1 — PRD (docs/PRD.md): latar belakang & masalah, tujuan & metrik keberhasilan, persona (manajemen), user stories berprioritas (Must/Should/Could), ruang lingkup & di luar lingkup, alur pengguna utama, kebutuhan non-fungsional, risiko & asumsi (lisensi Google Places, batas Overpass, kualitas data OSM Indonesia, risiko spam WhatsApp dan UU PDP), RENCANA ADOPSI HITACHI, dan rencana rilis.
  Rencana adopsi wajib memuat: (a) cara data outlet asli masuk (impor CSV dari Hitachi POS, atau endpoint/API sinkronisasi di masa depan); (b) estimasi biaya Google API per bulan beserta asumsinya; (c) kepatuhan & etika outreach (data bisnis publik, batas frekuensi, opt-out, persetujuan); (d) metrik nilai bisnis yang terukur (prospek per outlet, waktu hemat tim sales, konversi); (e) rencana pilot (mis. 1-2 wilayah dulu) dan kebutuhan infrastruktur/hosting; (f) risiko adopsi dan mitigasinya.

Fase 2 — TRD (docs/TRD.md): alasan pemilihan stack, model data lengkap (ERD mermaid) dengan indeks, kontrak endpoint/props Inertia, desain provider (interface DataProvider, adapter, registry, fallback), algoritma dedupe, rumus skor, desain scheduler/queue, strategi performa 10.000+ titik, keamanan (auth, enkripsi key, rate limit, validasi, CSRF), strategi testing, batasan lisensi.

Fase 3 — Arsitektur (docs/ARCHITECTURE.md): diagram mermaid konteks sistem, layer (Controller → Service/Action → Repository/Model, Provider adapters), alur data scheduler, alur permintaan peta, struktur folder frontend, konvensi kode. Prinsip: controller tipis, logika di Service/Action, DTO untuk data provider, Form Request untuk validasi, API Resource untuk output.

Fase 4 — Rencana implementasi (docs/IMPLEMENTATION-PLAN.md): milestone kecil yang bisa diuji, urut dependensi, kriteria selesai tiap milestone, mengikuti URUTAN BUILD (bagian 9). Bekukan dua kontrak sebelum implementasi: skema database + migrasi, dan docs/CONTRACTS.md (props Inertia/endpoint JSON dengan contoh payload).

Fase 5 — Implementasi (lihat bagian 8 untuk pembagian subagent). Test ditulis bersamaan dengan kode: skor, dedupe, normalisasi nomor WhatsApp, haversine, rute, provider (HTTP di-fake), scheduler, otorisasi, opt-out/batas frekuensi. Setelah tiap milestone: test + lint + build harus hijau.

Fase 6 — Refactoring (docs/REFACTORING.md): review menyeluruh setelah semua fitur jadi: duplikasi, controller gemuk, N+1 dan indeks kurang, komponen Vue terlalu besar (pecah, ekstrak composable), penamaan, kode mati, kebocoran memori di layer peta, performa dengan 10.000+ titik (ukur, jangan menebak). Catat sebelum/sesudah dan alasan. Semua test tetap hijau.

Fase 7 — Verifikasi nyata: jalankan `migrate:fresh --seed`, `hunter:seed-prospects --offline`, `npm run build`, serve. Pakai browser yang tersedia untuk menelusuri alur: login → intro → peta → filter → klik prospek → WhatsApp → pipeline → rute → dashboard → estimasi dampak & biaya → master data source → riwayat scheduler. Periksa console bebas error. Simpan screenshot di docs/screenshots/ (peta penuh, drawer prospek, Kanban, dashboard, white space, rute, master data source, estimasi biaya). Laporkan jujur apa yang berhasil, gagal, atau belum terverifikasi.

Fase 8 — Slide deck & paket pengumpulan:
- README.md: deskripsi, fitur, prasyarat, setup dari nol (perintah persis), akun demo, menjalankan scheduler, mengaktifkan Google provider, batasan dan lisensi data.
- docs/SLIDE-NOTES.md: bahan slide siap pakai (nama app + masalah, ringkasan PRD satu halaman, poin nilai adopsi, urutan demo 3 menit, daftar screenshot proses yang perlu diambil manual dari layar Claude beserta saran caption).
- Buat KERANGKA SLIDE DECK: gunakan skill/alat pembuat PPTX bila tersedia (atau python-pptx / pptxgenjs) untuk membuat file FinalProject_NamaLengkap.pptx (nama placeholder, diganti sendiri oleh pemilik) dengan slide: Judul, Masalah Hitachi, Solusi, Ringkasan PRD, Fitur Utama, Arsitektur (diagram), Proses Pengerjaan di Claude (placeholder screenshot + ringkasan dari AGENTS-LOG), Hasil Aplikasi (memakai screenshot dari docs/screenshots/), Rencana Adopsi & Biaya, Penutup + link GitHub (placeholder). Desain slide mengikuti identitas visual aplikasi. Slide proses diberi kotak placeholder jelas karena screenshot Claude diambil manual oleh pemilik.
- Repo siap di-push: .gitignore benar, tanpa secret, tanpa database lokal. Tulis di README langkah membuat repo GitHub, push, dan membuatnya public atau menambahkan trainer sebagai collaborator.

# 7. DEFINISI SELESAI

- Semua fitur di bagian 4 berfungsi (bukan UI kosong).
- Semua dokumen di docs/ ada dan konsisten dengan kode.
- Test, lint, build lulus.
- Aplikasi bisa dijalankan dari nol hanya dengan mengikuti README, dibuktikan dengan percobaan di folder bersih.
- Tampilan memenuhi bagian 5 dan sudah diverifikasi lewat browser dengan screenshot.
- Kerangka slide deck ada.
- Ringkasan akhir ke pemilik: apa yang selesai, apa yang belum/tidak terverifikasi, dan langkah manual tersisa (push ke GitHub, isi Google API key bila ada, ambil screenshot proses, isi nama, kumpulkan lewat Google Form https://forms.gle/L51yb1FRY1dk5aTS9).

# 8. CARA KERJA DENGAN SUBAGENT (WAJIB)

Kamu ORKESTRATOR: merencanakan, membagi pekerjaan, mendelegasikan ke subagent, mengintegrasikan, memverifikasi. Jangan mengerjakan semua sendirian bila bisa dipecah dengan aman.

Prinsip:
- Subagent tidak mewarisi konteks; tiap brief mandiri: tujuan, file/folder yang boleh disentuh, kontrak yang dipatuhi (rujuk docs/), kriteria selesai.
- Dua subagent penulis kode TIDAK BOLEH menyentuh file yang sama. Pecah per folder/modul. Subagent penulis yang paralel berjalan di worktree terpisah (isolation: worktree); kamu yang merge dan menyelesaikan konflik.
- Paralel hanya bila tidak saling membutuhkan hasil. Bila B butuh hasil A, berurutan. Pekerjaan membaca/meneliti/me-review boleh paralel tanpa worktree.
- Jangan langsung percaya laporan subagent: cek diff dan jalankan test/build sebelum menandai selesai.
- Batasi maksimal 3 subagent penulis kode berjalan bersamaan (hemat kuota).
- Task list menampilkan: tahap, nama subagent, tugas, status.
- Catat di docs/AGENTS-LOG.md: tabel (fase, subagent, tugas, file disentuh, hasil, masalah). Dipakai sebagai bahan slide "Proses".

Pembagian per fase:

Fase 0-1: orkestrator menulis PRD sendiri. Paralel read-only (agent Explore/general-purpose, hanya melapor): (1) "Spike-Peta": verifikasi MapLibre globe + deck.gl bersama Vue, rekomendasikan versi dan integrasi; (2) "Riset-Tile": 2 sumber basemap gratis tanpa key yang aktif, syarat atribusi dan batas pakai; (3) "Riset-Overpass": query bisnis radius 2 km per kategori, batas rate, ukuran respons, saran caching; (4) "Riset-Alamat": strategi reverse geocoding gratis (Nominatim dkk) dan batas penggunaannya. Hasil dirangkum ke DECISIONS.md.

Fase 2-4: orkestrator menulis TRD, ARCHITECTURE, IMPLEMENTATION-PLAN dan membekukan skema DB + CONTRACTS.md. Subagent "Reviewer-Dokumen" (read-only) membaca PRD+TRD+Arsitektur dan melaporkan inkonsistensi, celah, risiko; perbaiki sebelum lanjut.

Fase 5:
- 5.0 (orkestrator, berurutan): scaffold Laravel+Inertia+Vue+Tailwind, migrasi dan model sesuai kontrak, auth, layout kosong, commit.
- 5.1 (paralel, worktree masing-masing, tiap agent menulis test bagiannya):
  • "BE-Provider-Scheduler": app/Services/Providers/*, command hunter:*, job & scheduler, dedupe, baru buka/tutup, reverse geocoding terjadwal, test HTTP fake.
  • "BE-Domain": seeder 190 outlet, rumus skor, endpoint peta (viewport/cluster), pipeline, aktivitas, opt-out/batas frekuensi, perencana rute, ekspor, kalkulator biaya/dampak, master CRUD, Form Request, Resource, test.
  • "FE-Design-System": docs/DESIGN.md, token Tailwind, font, komponen dasar (panel kaca, tombol, badge, drawer, command palette, skeleton/empty/error). Hanya menyentuh resources/js/Components/ui dan konfigurasi tema.
- 5.2 (paralel setelah 5.1 digabung; tetap maks. 3 agent):
  • "FE-Peta": halaman peta, intro globe, layer outlet/prospek/heatmap/white space/rute, cincin radius, filter, drawer kartu, performa 10.000+ titik. Hanya menyentuh resources/js/Pages/Map* dan resources/js/map/*.
  • "FE-Pipeline": Kanban, tabel, bulk action, aktivitas, peringatan dobel kontak, opt-out, WhatsApp composer + template + opsi AI.
  • "FE-Dashboard-Master": dashboard, estimasi dampak & biaya, Master Data Source/Outlet(+impor CSV+antrean verifikasi)/Kategori, Riwayat Scheduler, ringkasan harian.
- 5.3 (orkestrator): gabungkan semua cabang, selesaikan konflik, test + lint + build hijau, commit.

Fase 6: paralel read-only dulu: "Review-Backend", "Review-Frontend", "Review-Performa" (benar-benar mengukur dengan 10.000+ titik), masing-masing menghasilkan temuan berperingkat (bukti file:baris, risiko, saran). Orkestrator memilah, lalu mendelegasikan perbaikan per area (backend/frontend) secara berurutan atau di worktree tanpa tumpang tindih. Tulis di REFACTORING.md. Test tetap hijau.

Fase 7: "QA-Browser" menjalankan aplikasi, menelusuri seluruh alur, memeriksa console, mengambil screenshot ke docs/screenshots/, melapor jujur. "Reviewer-Keamanan" (read-only) memeriksa auth, enkripsi key, validasi input, mass assignment, kebocoran secret di git. Perbaiki temuan, ulangi QA di area yang berubah.

Fase 8: orkestrator menulis README dan SLIDE-NOTES; subagent "Pembuat-Slide" membuat kerangka PPTX; subagent "Cek-Setup-Bersih" membuktikan setup dari folder bersih berhasil mengikuti README.

Komunikasi: setiap memulai/menyelesaikan subagent, tulis satu baris singkat ke pemilik (siapa, mengerjakan apa, hasil). Di akhir, ringkas dari AGENTS-LOG.md.

# 9. URUTAN BUILD (BUKAN PEMOTONGAN SCOPE; SEMUA TETAP WAJIB)

Bangun dan rapikan dalam urutan ini supaya di titik mana pun aplikasi tetap bisa didemo:
1. Inti: login, seeder outlet, provider + scheduler, prospek, peta 3D dengan intro, drawer kartu, WhatsApp, skor.
2. Operasional: filter, pipeline (Kanban/tabel/aktivitas/opt-out), master data source, riwayat scheduler.
3. Manajemen: dashboard, estimasi dampak & biaya, white space, heatmap, ekspor.
4. Nilai tambah: perencana rute, command palette, ringkasan harian, pesan AI opsional.
Setelah tiap tahap: test + lint + build hijau, commit, perbarui AGENTS-LOG.

# 10. PROTOKOL SESI (PEKERJAAN DIPECAH KE BEBERAPA SESI CLAUDE CODE)

Pekerjaan terlalu besar untuk satu percakapan, jadi dibagi 4 sesi. Dokumen di docs/, git log, dan file ini adalah satu-satunya memori antar sesi.

Aturan di setiap sesi:
1. Awal sesi: baca PROMPT.md ini, lalu docs/DECISIONS.md, docs/AGENTS-LOG.md, dokumen docs/ lain yang relevan, dan `git log --oneline -20`. Jalankan `git status`; jika ada perubahan tak ter-commit dari sesi sebelumnya, laporkan dulu sebelum mulai.
2. Kerjakan HANYA fase milik sesi yang diminta. Jangan lanjut ke sesi berikutnya meski masih ada waktu/konteks.
3. Akhir sesi: test + lint + build hijau (bila sudah ada kode), commit, perbarui docs/AGENTS-LOG.md, lalu berhenti dengan ringkasan: apa yang selesai, apa yang belum/tidak terverifikasi, dan apa yang perlu diputuskan pemilik. Tunggu pemilik.

Pembagian sesi:
- Sesi 1: Fase 0-4 (persiapan, riset paralel, PRD, TRD, Arsitektur, Rencana implementasi, kontrak beku). Tanpa menulis kode aplikasi. Berhenti setelah semua dokumen di-commit.
- Sesi 2: Fase 5.0 dan 5.1 (scaffold + backend + design system).
- Sesi 3: Fase 5.2 dan 5.3 (peta, pipeline, dashboard/master, penggabungan).
- Sesi 4: Fase 6, 7, dan 8 (refactoring, verifikasi browser + screenshot, README, SLIDE-NOTES, kerangka PPTX, cek setup bersih).

Perintah yang akan diberikan pemilik di tiap sesi berbentuk: "Baca PROMPT.md dan kerjakan Sesi N."
