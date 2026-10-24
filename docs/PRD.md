# PRD — POS Merchant Hunter

Versi 1.0 · Oktober 2026 · Pemilik: tim pengembang final project Claude Code (Hacktiv8) untuk PT Hitachi Channel Solutions Indonesia.

## 1. Latar Belakang & Masalah

Hitachi POS adalah aplikasi POS (kasir + back-office) yang dipakai merchant di seluruh Indonesia. Tim sales dan manajemen ingin menambah jumlah merchant, tetapi pencarian calon merchant masih manual: menelusuri peta, daftar bisnis, atau rekomendasi lisan, tanpa tahu mana yang sudah dihubungi, siapa yang menangani, atau area mana yang belum tergarap.

Masalah konkret:

1. **Tidak ada pandangan spasial.** Sebaran outlet Hitachi POS yang ada tidak divisualisasikan bersama bisnis di sekitarnya, sehingga area "white space" (padat bisnis, belum ada outlet Hitachi) sulit dilihat.
2. **Pencarian prospek tidak sistematis.** Tiap sales mencari sendiri; tidak ada skor untuk memprioritaskan.
3. **Tidak ada jejak kontak.** Prospek yang sama bisa dihubungi dua orang, atau terlalu sering sehingga terasa spam.
4. **Tidak ada ukuran nilai.** Manajemen tidak punya angka: berapa prospek per outlet, berapa konversi, berapa biaya data.

**POS Merchant Hunter** memetakan semua outlet di peta 3D interaktif, otomatis mencari bisnis potensial dalam radius maksimal 2 km dari tiap outlet, memberi skor prospek 0–100 dengan alasan yang terbaca, mengelola pipeline kontak, dan memudahkan menghubungi lewat WhatsApp secara etis.

## 2. Tujuan & Metrik Keberhasilan

| Tujuan | Metrik | Target pilot (90 hari) |
|---|---|---|
| Menemukan prospek secara sistematis | Prospek aktif unik per outlet | ≥ 25 per outlet |
| Memangkas waktu riset sales | Waktu menyusun daftar kunjungan mingguan | turun ≥ 50% (baseline diukur di awal pilot) |
| Mencegah kontak ganda | Kasus prospek dihubungi 2 akun < N hari | 0 tanpa peringatan terlihat |
| Meningkatkan konversi | Tingkat konversi Dihubungi → Deal | ≥ 3% (asumsi awal, dapat diubah di kalkulator) |
| Biaya data terkendali | Biaya data per bulan | Rp0 (OSM) atau sesuai anggaran Google bila diaktifkan |
| Adopsi | Akun manajemen aktif mingguan | ≥ 80% akun terdaftar |

Metrik dibaca dari Dashboard (bagian fitur E) dan halaman Estimasi Dampak & Biaya.

## 3. Persona

**Manajemen (satu-satunya role).** Kepala cabang, manajer sales, dan analis bisnis Hitachi. Banyak akun, hak sama: melihat peta, prospek, pipeline, dashboard, dan pengaturan master. Tujuan: menemukan area peluang, memprioritaskan prospek panas, mengoordinasikan kontak tanpa tumpang tindih, dan melaporkan hasil ke pimpinan. Kebiasaan: memakai WhatsApp dan Google Maps sehari-hari; nyaman dengan peta, tidak selalu nyaman dengan spreadsheet.

## 4. User Stories

Prioritas: **M** = Must, **S** = Should, **C** = Could. Semua tetap dibangun (lihat bagian 5), prioritas menentukan urutan dan kedalaman.

| ID | Sebagai manajemen, saya ingin… | Prio |
|---|---|---|
| US-01 | login dengan akun saya dan semua halaman terlindungi | M |
| US-02 | melihat semua outlet Hitachi di peta 3D dengan marker bercahaya | M |
| US-03 | melihat ribuan prospek sebagai titik/kolom 3D berwarna menurut skor | M |
| US-04 | intro globe sinematik yang bisa dilewati dan diingat | S |
| US-05 | klik prospek dan melihat kartu lengkap + rincian skor | M |
| US-06 | melihat jarak ke outlet terdekat dan jumlah outlet dalam 500 m | M |
| US-07 | alamat selalu tampil rapi (reverse geocoding ter-cache atau koordinat) | M |
| US-08 | menghubungi prospek lewat WhatsApp dengan pesan template per kategori yang bisa diedit | M |
| US-09 | pesan WhatsApp dibuat AI bila kunci API tersedia | C |
| US-10 | menyaring prospek (provinsi, kota, kategori, skor, rating, telepon, status, jarak, baru buka) | M |
| US-11 | berpindah mode peta: Prospek, Heatmap, White Space, Cakupan outlet | M |
| US-12 | command palette Ctrl/Cmd+K | S |
| US-13 | pipeline Baru → Dihubungi → Demo → Deal/Ditolak dalam Kanban dan tabel | M |
| US-14 | aksi massal pada tabel prospek | S |
| US-15 | catatan aktivitas (siapa, kapan, apa) dan "ditangani oleh" otomatis | M |
| US-16 | peringatan bila prospek sudah dihubungi akun lain dalam N hari | M |
| US-17 | catatan teks dan alasan penolakan terstandar | S |
| US-18 | batas frekuensi kontak dan opsi "Jangan hubungi lagi" | M |
| US-19 | menyusun rute kunjungan efisien dari beberapa prospek dan membukanya di Google Maps | S |
| US-20 | dashboard KPI, funnel, penetrasi provinsi, kategori teratas, white space, aktivitas akun, kesehatan scheduler | M |
| US-21 | ringkasan harian dalam aplikasi (opsional email) | S |
| US-22 | ekspor CSV prospek terfilter dan dashboard | S |
| US-23 | kalkulator Estimasi Dampak & Biaya dengan asumsi yang bisa diedit | M |
| US-24 | mengelola Data Source (aktif/nonaktif, prioritas, kunci terenkripsi, uji koneksi, kuota) | M |
| US-25 | mengelola outlet (CRUD, impor CSV, geocoding, antrean verifikasi manual) | M |
| US-26 | mengelola kategori target (bobot, template pesan) | M |
| US-27 | melihat riwayat run scheduler | M |
| US-28 | sistem mencari prospek otomatis tiap malam tanpa menyentuh API saat saya membuka halaman | M |
| US-29 | prospek hasil simulasi selalu ditandai "Simulasi" | M |

## 5. Ruang Lingkup

**Dalam lingkup:** seluruh fitur A–I pada spesifikasi proyek: autentikasi satu role banyak akun; peta 3D; pipeline; perencana rute; dashboard; master & pengaturan; scheduler malam; skor prospek; data demo (190 outlet dummy).

**Di luar lingkup (v1):**
- Integrasi langsung ke basis data/API Hitachi POS asli (hanya impor CSV; endpoint sinkronisasi dijadwalkan pasca-pilot).
- Pengiriman WhatsApp otomatis/massal (v1 hanya tautan wa.me yang diklik manusia).
- Beberapa role/hak akses berbeda.
- Aplikasi mobile native; navigasi turn-by-turn sendiri.
- Penyimpanan permanen data Google Places selain place_id.
- Prediksi ML; skor berbasis aturan.

## 6. Alur Pengguna Utama

1. **Masuk** → login → intro globe (bisa dilewati) → peta penuh layar dengan HUD angka.
2. **Menemukan:** pilih mode White Space → area padat bisnis tanpa outlet menyala → fly-to → filter kategori/skor.
3. **Memutuskan:** klik prospek → drawer: skor + alasan, jarak, tetangga Hitachi, riwayat aktivitas.
4. **Menghubungi:** tombol WhatsApp → peringatan bila sudah dihubungi orang lain → edit pesan template/AI → buka wa.me → aktivitas "dihubungi" tercatat, status berubah.
5. **Mengelola:** Kanban: drag ke Demo/Deal/Ditolak (alasan penolakan wajib); catatan; "Jangan hubungi lagi".
6. **Berkunjung:** pilih beberapa prospek → rute efisien → buka di Google Maps.
7. **Melapor:** dashboard KPI, penetrasi provinsi, ekspor CSV, estimasi dampak & biaya.
8. **Mengelola master:** Data Source, Outlet, Kategori; memeriksa riwayat scheduler.

## 7. Kebutuhan Non-Fungsional

| Area | Kebutuhan |
|---|---|
| Performa | Peta mulus (≥ 45 fps pada mesin kantor umum; ambang CI di Chromium SwiftShader ≥ 40 fps) dengan ≥ 10.000 titik; respons endpoint peta < 500 ms (lokal, SQLite); tidak ada N+1 |
| Keandalan | Demo selalu jalan offline (MockProvider); scheduler idempoten; retry dengan backoff |
| Keamanan | Semua halaman di balik auth; CSRF; kunci API terenkripsi (Crypt/APP_KEY); validasi Form Request; rate limit login; tanpa secret di git |
| Privasi & kepatuhan | Hanya data bisnis publik; opt-out; batas frekuensi; UU PDP (lihat risiko); kepatuhan lisensi Google Places |
| Portabilitas | SQLite default, kompatibel MySQL/PostgreSQL; tanpa PostGIS |
| Aksesibilitas | Kontras cukup, fokus keyboard, label ARIA, `prefers-reduced-motion`; axe tanpa pelanggaran serius/kritis |
| Responsif | Tablet penuh, ponsel layak |
| Bahasa | UI & dokumen Bahasa Indonesia |
| Observabilitas | Riwayat run scheduler, log error, kesehatan scheduler di dashboard |
| Kualitas | Cakupan ≥ 80% baris pada app/Services dan logika domain; tes deterministik |

## 8. Risiko & Asumsi

| # | Risiko / asumsi | Dampak | Mitigasi |
|---|---|---|---|
| R1 | **Lisensi Google Places:** hanya place_id boleh disimpan permanen; data lain cache berkedaluwarsa (lat/lng maks. 30 hari) | Pelanggaran ToS | Kolom `fetched_at`/`expires_at`; refresh via place_id; provider Google nonaktif default; dicatat di TRD |
| R2 | **Batas Overpass** (rate limit, timeout, 429/504) | Sync gagal | Antrean, jeda, backoff, cache respons, mirror; MockProvider cadangan |
| R3 | **Kualitas data OSM Indonesia:** telepon/alamat sering kosong, tanpa tanggal buka | Prospek kurang lengkap, "baru buka" kurang akurat | Reverse geocoding terjadwal; "baru buka" = muncul setelah baseline; kelengkapan kontak masuk skor; opsi Google |
| R4 | **Spam WhatsApp & reputasi nomor** | Nomor diblokir, citra buruk | Batas frekuensi, peringatan dobel kontak, opt-out, tanpa pengiriman otomatis |
| R5 | **UU PDP (UU 27/2022):** nomor telepon pribadi pemilik usaha | Risiko hukum | Hanya data bisnis yang dipublikasikan sumber; tampilkan sumber; opt-out dan penghapusan; kebijakan retensi; **dikonsultasikan ke legal Hitachi sebelum pilot** |
| R6 | Basemap gratis tanpa SLA | Peta kosong | Atribusi benar, style cadangan, opsi self-host tile |
| R7 | Data outlet dummy ≠ nyata | Hasil demo tak mencerminkan | Badge Simulasi; rencana impor CSV outlet asli |
| R8 | WebGL lemah di perangkat kantor | Peta patah | Mode ringan (kurangi layer 3D), uji performa terukur |
| A1 | Asumsi | Pengguna memakai browser modern (Chrome/Edge/Safari terbaru) dengan WebGL2 | |
| A2 | Asumsi | Tersedia koneksi internet di server untuk sync malam; demo tidak butuh | |

## 9. Rencana Adopsi Hitachi

### (a) Masuknya data outlet asli
1. **Tahap pilot:** ekspor CSV dari Hitachi POS (kolom: kode outlet, nama merchant, alamat, kota, provinsi, lat, lng opsional) → halaman Master Outlet › Impor CSV. Tanpa koordinat → geocoding by nama + kota, skor kecocokan; hasil meragukan masuk antrean verifikasi manual.
2. **Tahap lanjutan:** endpoint sinkronisasi terautentikasi (token) yang dipanggil job ekspor Hitachi POS secara berkala (kontrak disiapkan, belum dibangun di v1). Aplikasi tidak pernah mengakses database Hitachi POS langsung.
3. Data outlet disimpan terpisah dari data operasional POS; hanya atribut minimum (nama, alamat, koordinat).

### (b) Estimasi biaya Google API per bulan
Asumsi (dapat diedit di halaman Estimasi): 190 outlet; sync Google 1× per minggu (≈ 4,3 kali/bulan); per outlet 1 panggilan *Nearby Search* per kelompok kategori (≈ 6 kelompok), tanpa paginasi; ditambah *Place Details* untuk prospek baru saja (≈ 20% dari hasil).
- Places API (New) `searchNearby` tanpa paginasi (maks. 20 hasil/panggilan): ≈ 190 × 6 × 1 × 4,3 ≈ **4.900/bulan**.
- Place Details ≈ 20% × (190 × 25 prospek baru-perbarui) ≈ **950/bulan**.
- Dengan tarif SKU Pro Google Places yang berlaku (cek tabel harga terbaru Google sebelum pilot; di model harga 2025 ada kuota gratis bulanan per SKU), biaya kasar berada di kisaran **puluhan hingga beberapa ratus dolar AS/bulan** bila semua dipakai penuh; dengan hanya memakai Google untuk kota pilot (mis. 1–2 wilayah, 20 outlet), turun ~90%.
- **OSM + MockProvider: Rp0.** Rekomendasi: OSM default, Google hanya untuk pengayaan terbatas (rating/telepon) di wilayah pilot.
Angka pasti dihitung di kalkulator in-app dengan tarif yang bisa diisi pemilik; dokumen ini tidak mengklaim harga resmi.

### (c) Kepatuhan & etika outreach
- Data bisnis publik saja; sumber ditampilkan; data simulasi diberi badge.
- Batas frekuensi kontak per prospek (default: 1× per 14 hari) dan peringatan bila akun lain menghubungi dalam N hari (default 7).
- Opt-out "Jangan hubungi lagi" permanen: menyembunyikan WhatsApp, mengecualikan dari rekomendasi dan ringkasan harian.
- Pesan harus identifikasi pengirim dan beri jalan keluar ("balas STOP"); template dibuat sopan, bukan agresif.
- Tidak ada pengiriman otomatis; manusia menekan kirim di WhatsApp.
- Retensi: prospek `ditolak`/opt-out dapat dianonimkan; kebijakan final oleh legal.

### (d) Metrik nilai bisnis
- Prospek per outlet (ditemukan, aktif, panas).
- Waktu hemat tim sales: baseline survei mingguan vs jam riset setelah pilot.
- Konversi per tahap funnel; deal per bulan; cakupan/penetrasi per provinsi.
- Biaya per prospek (bila Google aktif).
Kalkulator in-app memproyeksikan: prospek/outlet × konversi × (nilai deal yang bisa diedit) dengan semua asumsi eksplisit.

### (e) Rencana pilot
- **Wilayah:** 1–2 provinsi (mis. DKI Jakarta/Banten dan Jawa Timur), ±10–20 outlet, 4–6 akun manajemen, 6–8 minggu.
- **Minggu 0:** impor outlet asli, verifikasi geocoding, kebijakan retensi dengan legal.
- **Minggu 1–6:** sync malam, sales memakai harian, mingguan tinjau metrik.
- **Minggu 7–8:** evaluasi vs metrik; keputusan perluasan.
- **Infrastruktur:** 1 VM kecil (2 vCPU, 4 GB) dengan PHP 8.2+, Nginx, SQLite atau MySQL/PostgreSQL, cron untuk `schedule:run`, worker queue (supervisor), HTTPS internal, cadangan harian; akses lewat SSO/VPN kantor bila tersedia.

### (f) Risiko adopsi & mitigasi
| Risiko | Mitigasi |
|---|---|
| Sales tidak mau memakai | Libatkan 2 sales sejak awal; antarmuka cepat; sesi pelatihan 30 menit |
| Data tidak akurat | Badge sumber; tombol "laporkan data salah" via catatan; Google di wilayah pilot |
| Kekhawatiran legal/PDP | Tinjauan legal sebelum pilot; opt-out; hanya data publik |
| Biaya Google membengkak | Nonaktif default, kuota per bulan di Data Source, kalkulator |
| Ketergantungan layanan gratis (Overpass/tile) | Cache, mirror, opsi self-host |
| Keamanan hosting | VPN, HTTPS, kunci terenkripsi, audit log aktivitas |

## 10. Rencana Rilis

Catatan: data nyata OSM Indonesia tipis (telepon/alamat sering kosong) dan "baru buka" hanyalah heuristik lemah (muncul setelah baseline); keduanya ditampilkan jujur di UI. Aplikasi baru dapat didemo penuh (peta) setelah Sesi 3; setelah Sesi 2 demo berupa CLI + API (risiko jadwal, tenggat 29 Oktober 2026).

| Rilis | Isi | Tag |
|---|---|---|
| v0.1 | Dokumen: PRD, TRD, arsitektur, kontrak, rencana | `v0.1-docs` |
| v0.2 | Backend: provider, scheduler, domain, design system | `v0.2-backend` |
| v0.3 | Aplikasi: peta, pipeline, dashboard, master, E2E, CI | `v0.3-app` |
| v1.0.0 | Refactoring, verifikasi, README, slide deck | `v1.0.0` |
| Pilot | 1–2 wilayah, data outlet asli | pasca v1.0.0 |
