// Pembuat kerangka deck PPTX: POS Merchant Hunter. Lihat README.md di folder ini.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DEPS = process.env.DECK_DEPS || '/private/tmp/claude-501/deck-deps';
const require = createRequire(path.join(DEPS, 'package.json'));
const PptxGenJS = require('pptxgenjs');

const here = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(here, '../..');
const SHOTS = path.join(ROOT, 'docs/screenshots');
const SCRATCH = process.env.DECK_SCRATCH || '/private/tmp/claude-501/deck-scratch';
const OUT = process.env.DECK_OUT || path.join(ROOT, 'FinalProject_NamaLengkap.pptx');
fs.mkdirSync(SCRATCH, { recursive: true });

// Kecilkan salinan screenshot (maks 1600 px, JPEG) ke folder scratch via sips (macOS).
function shot(name) {
  const src = path.join(SHOTS, name);
  const dst = path.join(SCRATCH, name.replace(/\.png$/, '.jpg'));
  execFileSync('sips', ['-s', 'format', 'jpeg', '-s', 'formatOptions', '80', '-Z', '1600', src, '--out', dst], { stdio: 'ignore' });
  return dst;
}

// Token dari docs/DESIGN.md (tema gelap)
const C = { bg: '070D0C', surface: '0F1917', raised: '162623', line: '27413A', text: 'E8F0EB', muted: '9FB3AA',
  hot: 'FF6A2B', warm: 'F2B63A', medium: '4FC3A9', focus: '7DE3C4', cold: '8CA4B8', danger: 'FF6B6B', onHot: '1A0A02' };
// Font: aslinya Bricolage Grotesque / Instrument Sans / JetBrains Mono (tidak umum terpasang) -> fallback aman.
const F = { head: 'Arial', body: 'Arial', mono: 'Courier New' };

const pptx = new PptxGenJS();
pptx.layout = 'LAYOUT_WIDE'; // 13.333 x 7.5 in (16:9)
pptx.title = 'POS Merchant Hunter - Final Project Claude Code';
pptx.author = 'Nama Peserta';
const W = 13.333, H = 7.5, MX = 0.6;
let n = 0;

function base(label, title) {
  const s = pptx.addSlide();
  n += 1;
  s.background = { color: C.bg };
  // cincin radar samar di pojok
  s.addShape('ellipse', { x: 10.6, y: -1.6, w: 4.4, h: 4.4, fill: { type: 'none' }, line: { color: C.line, width: 0.75 } });
  s.addShape('ellipse', { x: 11.3, y: -0.9, w: 3.0, h: 3.0, fill: { type: 'none' }, line: { color: C.line, width: 0.75 } });
  s.addText(label.toUpperCase(), { x: MX, y: 0.35, w: 8, h: 0.3, fontFace: F.mono, fontSize: 11, color: C.hot, bold: true, charSpacing: 3, margin: 0 });
  s.addText(title, { x: MX, y: 0.66, w: 11.2, h: 0.7, fontFace: F.head, fontSize: 30, bold: true, color: C.text, margin: 0, valign: 'top' });
  s.addText('POS Merchant Hunter', { x: MX, y: 7.1, w: 5, h: 0.25, fontFace: F.mono, fontSize: 9, color: C.muted, margin: 0 });
  s.addText(String(n).padStart(2, '0') + ' / 12', { x: W - MX - 2, y: 7.1, w: 2, h: 0.25, fontFace: F.mono, fontSize: 9, color: C.muted, align: 'right', margin: 0 });
  return s;
}
function card(s, x, y, w, h, opt = {}) {
  s.addShape('roundRect', { x, y, w, h, rectRadius: 0.12, fill: { color: opt.fill || C.surface }, line: { color: opt.line || C.line, width: 1 } });
}
function hud(s, text, x, y, w, color = C.muted, size = 10) {
  s.addText(text.toUpperCase(), { x, y, w, h: 0.25, fontFace: F.mono, fontSize: size, color, bold: true, charSpacing: 2, margin: 0 });
}
function body(s, text, x, y, w, h, o = {}) {
  s.addText(text, { x, y, w, h, fontFace: F.body, fontSize: o.size || 14, color: o.color || C.text, bold: o.bold, align: o.align, valign: o.valign || 'top', margin: 0, paraSpaceAfter: o.gap ?? 4, ...o.extra });
}
function bullets(items, size = 14, color = C.text) {
  return items.map((t, i) => ({ text: t, options: { bullet: { code: '25A0', indent: 14 }, fontFace: F.body, fontSize: size, color, breakLine: i < items.length - 1, paraSpaceAfter: 5 } }));
}
function pic(s, file, x, y, w, h, cap) {
  s.addShape('roundRect', { x: x - 0.03, y: y - 0.03, w: w + 0.06, h: h + 0.06, rectRadius: 0.06, fill: { color: C.line }, line: { color: C.line, width: 0.5 } });
  s.addImage({ path: shot(file), x, y, w, h });
  if (cap) body(s, cap, x, y + h + 0.08, w, 0.4, { size: 11, color: C.muted });
}
function arrow(s, x1, y1, x2, y2, color = C.focus) {
  s.addShape('line', { x: Math.min(x1, x2), y: Math.min(y1, y2), w: Math.abs(x2 - x1), h: Math.abs(y2 - y1),
    flipH: x2 < x1, flipV: y2 < y1, line: { color, width: 2, endArrowType: 'triangle' } });
}
function box(s, x, y, w, h, title, sub, o = {}) {
  s.addShape('roundRect', { x, y, w, h, rectRadius: 0.1, fill: { color: o.fill || C.surface }, line: { color: o.line || C.focus, width: 1.25 } });
  s.addText([
    { text: title, options: { fontFace: F.head, fontSize: o.tsize || 13, bold: true, color: o.color || C.text, breakLine: !!sub } },
    ...(sub ? [{ text: sub, options: { fontFace: F.body, fontSize: o.ssize || 10.5, color: C.muted } }] : []),
  ], { x, y, w, h, align: 'center', valign: 'middle', margin: 0.06 });
}

// ---------- 1. Judul ----------
{
  const s = pptx.addSlide(); n += 1;
  s.background = { color: C.bg };
  [6.4, 5.0, 3.6, 2.2].forEach((d, i) => s.addShape('ellipse', { x: 10.2 - d / 2 + 0.2, y: 3.6 - d / 2, w: d, h: d, fill: { type: 'none' }, line: { color: i === 3 ? C.hot : C.line, width: i === 3 ? 1.5 : 1 } }));
  s.addShape('ellipse', { x: 10.4 - 0.12, y: 3.6 - 0.12, w: 0.24, h: 0.24, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
  hud(s, 'Final Project Claude Code · Hacktiv8', MX, 0.8, 8, C.hot, 12);
  s.addText('POS Merchant\nHunter', { x: MX, y: 1.4, w: 8.5, h: 2.4, fontFace: F.head, fontSize: 66, bold: true, color: C.text, margin: 0, valign: 'top', lineSpacingMultiple: 0.95 });
  body(s, '“Mission control” untuk menemukan calon merchant Hitachi POS berikutnya.', MX, 3.85, 7.6, 0.9, { size: 22, color: C.focus });
  body(s, 'Final Project Claude Code · Hacktiv8 · untuk PT Hitachi Channel Solutions Indonesia', MX, 4.95, 8, 0.6, { size: 14, color: C.muted });
  s.addShape('roundRect', { x: MX, y: 5.95, w: 5.2, h: 0.75, rectRadius: 0.1, fill: { type: 'none' }, line: { color: C.hot, width: 1.5, dashType: 'dash' } });
  s.addText([{ text: 'NAMA PESERTA', options: { fontFace: F.mono, fontSize: 16, bold: true, color: C.hot, breakLine: true } },
    { text: '(ganti dengan nama lengkap Anda)', options: { fontFace: F.body, fontSize: 11, color: C.muted } }],
    { x: MX, y: 5.95, w: 5.2, h: 0.75, align: 'center', valign: 'middle', margin: 0 });
  s.addNotes('[0:00-0:20] Pembuka. Kalimat pembuka: "Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan; dengan POS Merchant Hunter, jawabannya ada di peta." Perkenalkan diri, sebutkan ini final project training Claude Code Hacktiv8 untuk PT Hitachi Channel Solutions Indonesia. Catatan: ganti "Nama Peserta" sebelum dikumpulkan.');
}

// ---------- 2. Masalah ----------
{
  const s = base('02 · Masalah', 'Mencari merchant masih tebak-tebakan');
  body(s, 'Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan.', MX, 1.5, 11.5, 0.5, { size: 18, color: C.focus });
  const items = [['01', 'Manual', 'Tim sales mencari calon merchant satu per satu.'],
    ['02', 'Tanpa peta', 'Tidak ada pandangan spasial atas outlet dan sekitarnya.'],
    ['03', 'Tanpa skor', 'Tidak ada prioritas: prospek panas tak terlihat.'],
    ['04', 'Tanpa jejak', 'Tidak tahu siapa sudah menghubungi siapa; risiko kontak ganda.'],
    ['05', 'Tanpa angka', 'Manajemen tidak punya ukuran nilai dan perkembangan.']];
  const cw = 2.2, gap = 0.2;
  items.forEach(([k, t, d], i) => {
    const x = MX + i * (cw + gap);
    card(s, x, 2.5, cw, 3.3, { line: i === 2 ? C.hot : C.line });
    s.addText(k, { x: x + 0.2, y: 2.7, w: 1, h: 0.4, fontFace: F.mono, fontSize: 20, bold: true, color: C.hot, margin: 0 });
    body(s, t, x + 0.2, 3.3, cw - 0.4, 0.5, { size: 17, bold: true });
    body(s, d, x + 0.2, 3.95, cw - 0.4, 1.6, { size: 13, color: C.muted });
  });
  body(s, 'Akibatnya: waktu riset sales besar, peluang terlewat, dan nilai pekerjaan sulit dibuktikan.', MX, 6.15, 12, 0.5, { size: 14, color: C.text });
}

// ---------- 3. Solusi ----------
{
  const s = base('03 · Solusi', 'Jawabannya ada di peta');
  s.addNotes('[0:20-0:50] Demo: login lalu intro globe terbang ke Indonesia (tekan lewati intro sekali agar diingat). Peta mode Prospek: ribuan titik; marker outlet berbeda dari prospek; hover outlet memunculkan cincin 2 km. Filter provinsi + skor >= 75. Data di slide ini seluruhnya dummy/simulasi (190 outlet, ±14,6 ribu prospek) dan diberi badge "Simulasi".');
  s.addText(bullets([
    'Peta 3D semua outlet + ribuan prospek berwarna menurut skor',
    'Pencarian otomatis bisnis ≤ 2 km dari tiap outlet, tiap malam (OpenStreetMap; Google opsional)',
    'Skor 0–100 dengan alasan yang terbaca',
    'Pipeline Baru → Dihubungi → Demo → Deal/Ditolak',
    'WhatsApp bertemplate, rute kunjungan, dasbor manajemen',
  ], 17), { x: MX, y: 1.6, w: 4.9, h: 4.6, valign: 'top', margin: 0 });
  pic(s, '01-peta-penuh.png', 5.75, 1.55, 6.95, 4.34, 'Peta 3D: outlet Hitachi dan ribuan prospek berwarna menurut skor.');
  body(s, 'Data pada screenshot adalah data dummy (badge “Simulasi”).', MX, 6.3, 12, 0.35, { size: 11, color: C.muted });
}

// ---------- 4. Ringkasan PRD ----------
{
  const s = base('04 · Ringkasan PRD', 'Satu peran, satu tujuan, ukuran jelas');
  const qx = [MX, 6.75], qy = [1.55, 4.3], qw = 5.98, qh = 2.55;
  const q = [
    ['Persona', ['Satu peran: “manajemen” (kepala cabang, manajer sales, analis)', 'Banyak akun, satu tampilan bersama']],
    ['Metrik sukses pilot 90 hari', ['≥ 25 prospek aktif per outlet', 'Waktu riset sales turun ≥ 50%', '0 kontak ganda tanpa peringatan', '≥ 80% akun aktif mingguan; biaya data Rp0 (OSM)']],
    ['Ruang lingkup', ['Dalam: peta, skor, pipeline, WhatsApp, rute, dasbor, biaya', 'Di luar v1: kirim WA otomatis, integrasi langsung DB Hitachi POS, banyak peran']],
    ['Risiko utama', ['Lisensi Google Places dan batas Overpass', 'Kualitas data OSM Indonesia', 'Spam WhatsApp dan UU PDP', 'Masing-masing punya mitigasi di PRD']],
  ];
  q.forEach(([t, items], i) => {
    const x = qx[i % 2], y = qy[Math.floor(i / 2)];
    card(s, x, y, qw, qh, { line: i === 1 ? C.hot : C.line });
    hud(s, t, x + 0.25, y + 0.2, qw - 0.5, i === 1 ? C.hot : C.focus, 11);
    s.addText(bullets(items, 15), { x: x + 0.25, y: y + 0.6, w: qw - 0.5, h: qh - 0.75, valign: 'top', margin: 0 });
  });
}

// ---------- 5. Fitur ----------
{
  const s = base('05 · Fitur utama', 'Sembilan fitur, satu alur kerja sales');
  s.addNotes('[0:50-1:20] Klik prospek panas: drawer menampilkan rincian skor, jarak ke outlet, efek tetangga, badge Simulasi. Tekan WhatsApp: pesan terisi template dan aktivitas tercatat (tidak ada kirim otomatis).\n[1:20-1:40] Mode White Space + Heatmap: area padat bisnis tanpa outlet Hitachi.\n[1:40-2:00] Pipeline Kanban: seret kartu Dihubungi ke Demo; peringatan kontak ganda dari akun lain; opt-out.\n[2:00-2:15] Perencana rute: pilih 4-5 prospek, urutan, jarak, buka di Google Maps.');
  const f = [['Peta 3D + intro globe', 'MapLibre + deck.gl, 14,6 ribu titik'], ['Skor 0–100', 'Alasan terbaca, jarak, efek tetangga'], ['Pipeline Kanban', 'Seret kartu, catatan aktivitas'],
    ['WhatsApp bertemplate', 'Pesan terisi, tanpa kirim otomatis'], ['White Space & Heatmap', 'Padat bisnis, belum ada outlet'], ['Rute kunjungan', 'Nearest-neighbor + 2-opt'],
    ['Dasbor manajemen', 'KPI, funnel, penetrasi provinsi'], ['Estimasi dampak & biaya', 'Asumsi eksplisit, Google vs OSM'], ['Master Data Source', 'API key terenkripsi, uji koneksi']];
  const cw = 3.9, ch = 1.55, gx = 0.2, gy = 0.2;
  f.forEach(([t, d], i) => {
    const x = MX + (i % 3) * (cw + gx), y = 1.6 + Math.floor(i / 3) * (ch + gy);
    card(s, x, y, cw, ch, { line: i === 1 ? C.hot : C.line });
    s.addShape('ellipse', { x: x + 0.25, y: y + 0.3, w: 0.7, h: 0.7, fill: { color: i === 1 ? C.hot : C.raised }, line: { color: i === 1 ? C.hot : C.focus, width: 1.25 } });
    s.addText(String(i + 1).padStart(2, '0'), { x: x + 0.25, y: y + 0.3, w: 0.7, h: 0.7, align: 'center', valign: 'middle', fontFace: F.mono, fontSize: 14, bold: true, color: i === 1 ? C.onHot : C.focus, margin: 0 });
    body(s, t, x + 1.15, y + 0.3, cw - 1.3, 0.4, { size: 13.5, bold: true });
    body(s, d, x + 1.15, y + 0.78, cw - 1.3, 0.6, { size: 11.5, color: C.muted });
  });
}

// ---------- 6. Arsitektur ----------
{
  const s = base('06 · Arsitektur', 'Browser, Laravel, SQLite, dan pekerja malam');
  // Baris 1
  box(s, 0.6, 1.85, 2.7, 1.5, 'Browser', 'Inertia + Vue 3\nMapLibre + deck.gl');
  s.addShape('roundRect', { x: 3.95, y: 1.6, w: 6.3, h: 2.0, rectRadius: 0.12, fill: { color: C.bg }, line: { color: C.focus, width: 1.5, dashType: 'dash' } });
  hud(s, 'Laravel 12', 4.1, 1.66, 4, C.focus, 10);
  box(s, 4.15, 2.05, 1.75, 1.2, 'Controller', 'validasi, tipis', { tsize: 12.5 });
  box(s, 6.2, 2.05, 1.75, 1.2, 'Action / Service', 'logika bisnis', { tsize: 12.5, line: C.hot });
  box(s, 8.25, 2.05, 1.8, 1.2, 'Repository / Model', 'query, Eloquent', { tsize: 12.5 });
  arrow(s, 5.9, 2.65, 6.2, 2.65); arrow(s, 7.95, 2.65, 8.25, 2.65);
  arrow(s, 3.3, 2.6, 3.95, 2.6);
  body(s, 'HTTPS\nsesi', 3.3, 2.0, 0.65, 0.5, { size: 9.5, color: C.muted, align: 'center' });
  arrow(s, 10.25, 2.6, 10.95, 2.6);
  // SQLite silinder
  s.addShape('can', { x: 10.95, y: 1.85, w: 1.8, h: 1.5, fill: { color: C.surface }, line: { color: C.medium, width: 1.5 } });
  s.addText([{ text: 'SQLite', options: { fontFace: F.head, fontSize: 14, bold: true, color: C.text, breakLine: true } }, { text: 'data + antrean', options: { fontFace: F.body, fontSize: 10.5, color: C.muted } }], { x: 10.95, y: 2.1, w: 1.8, h: 1.3, align: 'center', valign: 'middle', margin: 0 });
  body(s, 'Tile peta: OpenFreeMap (langsung dari browser)', 0.6, 3.5, 3.2, 0.5, { size: 10.5, color: C.muted });
  // Baris 2
  box(s, 10.95, 4.6, 1.8, 1.5, 'Scheduler + Queue', 'cron schedule:run\nhunter:sync', { tsize: 12.5 });
  arrow(s, 11.85, 4.6, 11.85, 3.4, C.medium);
  body(s, 'job & hasil', 11.95, 3.85, 1.2, 0.3, { size: 9.5, color: C.muted });
  box(s, 6.9, 4.6, 3.1, 1.5, 'Provider adapters', 'OSM · Mock · Google (opsional)\nlewat ProviderRegistry', { line: C.hot, tsize: 13 });
  arrow(s, 10.95, 5.35, 10.0, 5.35, C.hot);
  box(s, 0.6, 4.6, 5.0, 1.5, 'Sumber eksternal', 'Overpass API / mirror · Nominatim (geocoding)\nGoogle Places (opsional, nonaktif bawaan)', { tsize: 13 });
  arrow(s, 6.9, 5.35, 5.6, 5.35, C.hot);
  body(s, 'Panggilan data eksternal hanya dari queue/scheduler, tidak dari request pengguna.', 0.6, 6.4, 12, 0.4, { size: 12.5, color: C.focus });
}

// ---------- 7. Proses ----------
{
  const s = base('07 · Proses pengerjaan di Claude', 'Empat sesi, subagen paralel, tes hijau tiap milestone');
  const ses = [['Sesi 1', 'Riset + dokumen', '4 subagen riset paralel; review menemukan 19 temuan'],
    ['Sesi 2', 'Backend + desain', '3 agen penulis di worktree terpisah'],
    ['Sesi 3', 'Fitur + E2E', 'Peta, pipeline, dasbor + suite Playwright'],
    ['Sesi 4', 'Mutu', 'Refactoring, QA browser, audit keamanan']];
  const cw = 2.9, gap = 0.14;
  ses.forEach(([a, b, c], i) => {
    const x = MX + i * (cw + gap);
    card(s, x, 1.5, cw, 1.45);
    hud(s, a, x + 0.2, 1.62, 2.5, C.hot, 10);
    body(s, b, x + 0.2, 1.92, cw - 0.4, 0.35, { size: 15, bold: true });
    body(s, c, x + 0.2, 2.3, cw - 0.4, 0.6, { size: 11, color: C.muted });
  });
  const caps = ['Empat subagen riset read-only bekerja paralel.', 'Tiga agen penulis di worktree terpisah; orkestrator merge dan memverifikasi.', 'Setiap milestone ditutup dengan tes, lint, dan build hijau.'];
  const bw = 3.9, bg = 0.2;
  caps.forEach((c, i) => {
    const x = MX + i * (bw + bg);
    s.addShape('rect', { x, y: 3.2, w: bw, h: 2.95, fill: { color: C.surface }, line: { color: C.hot, width: 2, dashType: 'dash' } });
    s.addText([{ text: 'TEMPEL SCREENSHOT', options: { fontFace: F.mono, fontSize: 15, bold: true, color: C.hot, breakLine: true } },
      { text: 'PROSES CLAUDE DI SINI', options: { fontFace: F.mono, fontSize: 15, bold: true, color: C.hot, breakLine: true } },
      { text: 'Placeholder ' + (i + 1) + ' dari 3', options: { fontFace: F.body, fontSize: 11, color: C.muted } }],
      { x, y: 3.2, w: bw, h: 2.95, align: 'center', valign: 'middle', margin: 0 });
    body(s, c, x, 6.25, bw, 0.55, { size: 11.5, color: C.text });
  });
  s.addNotes('Kotak putus-putus diisi manual oleh pemilik: ambil screenshot dari layar Claude Code sendiri. Saran dari docs/SLIDE-NOTES.md bagian 7: (1) prompt awal, (2) riset paralel Sesi 1, (3) laporan Reviewer-Dokumen 19 temuan, (4) tiga agen worktree, (5) merge dan tes hijau, (6) temuan QA-Browser, (7) tabel refactoring, (8) AGENTS-LOG.md, (9) git log --graph. Ceritakan: subagen riset/review/penulis berjalan paralel di worktree; orkestrator merge --no-ff dan memverifikasi; tiap milestone ditutup dengan tes hijau.');
}

// ---------- 8. Hasil ----------
function gallery(s, items) {
  const cw = 3.72, ch = 2.325, gx = 0.47;
  items.forEach(([f, c], i) => pic(s, f, MX + (i % 3) * (cw + gx), 1.5 + Math.floor(i / 3) * 2.8, cw, ch, c));
}
{
  const s = base('08 · Hasil aplikasi', 'Aplikasi berjalan (data dummy)');
  s.addNotes('[2:15-2:40] Dasbor + Estimasi Dampak & Biaya: KPI, funnel, penetrasi provinsi; ubah asumsi Google vs OSM Rp0. Semua screenshot dari seed dummy; status pipeline demo (18 Dihubungi, 10 Demo, 5 Deal, 6 Ditolak) diatur lewat aksi aplikasi pada database demo terpisah.');
  gallery(s, [
    ['01-peta-penuh.png', 'Peta 3D: outlet dan prospek menurut skor.'],
    ['03-drawer-prospek.png', 'Kartu prospek: skor 84 dan rincian alasan.'],
    ['04-kanban.png', 'Kanban drag & drop dengan catatan aktivitas.'],
    ['05-dashboard.png', 'Dasbor manajemen: KPI, funnel, penetrasi provinsi.'],
    ['06-white-space.png', 'White Space: padat bisnis, tanpa outlet Hitachi.'],
    ['07-rute.png', 'Rute kunjungan efisien (nearest-neighbor + 2-opt).'],
  ]);
}

// ---------- 9. Hasil lanjutan ----------
{
  const s = base('09 · Hasil aplikasi (lanjutan)', 'Konfigurasi, biaya, tema terang, ponsel');
  const cw = 4.0, ch = 2.4;
  [['08-master-data-source.png', 'Master Data Source: key terenkripsi, uji koneksi.'], ['09-estimasi-biaya.png', 'Estimasi dampak & biaya dengan asumsi eksplisit.'],
    ['13-tema-terang.png', 'Tema terang untuk ruangan terang.'], ['15-riwayat-scheduler.png', 'Riwayat scheduler (baris “Demo:” hanya contoh).']]
    .forEach(([f, c], i) => pic(s, f, MX + (i % 2) * (cw + 0.25), 1.45 + Math.floor(i / 2) * 2.85, cw, ch, c));
  const ph = 5.25, pw = ph * 390 / 844;
  pic(s, '14-ponsel.png', 10.0 + (2.7 - pw) / 2, 1.45, pw, ph, 'Responsif di ponsel.');
  s.addNotes('[2:40-3:00] Master Data Source: API key terenkripsi, tidak pernah tampil. Riwayat Scheduler menunjukkan kesehatan scheduler (baris contoh bertanda "Demo:" disisipkan manual). Tema terang dan tampilan ponsel menunjukkan UI responsif. Tutup dengan link GitHub.');
}

// ---------- 10. Mutu ----------
{
  const s = base('10 · Mutu & keamanan', 'Diuji berlapis, dengan keterbatasan yang jujur');
  s.addNotes('Semua angka dari docs/TEST-REPORT.md (dijalankan ulang pada commit akhir): Pest 455 lulus (7.732 assertion), Vitest 299 lulus, Playwright E2E 46 lulus termasuk axe di 12 halaman. Audit keamanan: 0 kritis, 2 mayor dan 8 minor diperbaiki; pemindaian secret 0 temuan. Refactoring terukur: ekspor CSV 0,80 ke 0,08 dtk, bundel app 525 ke 284 kB. Keterbatasan: FPS pada GPU nyata belum terukur (SwiftShader 2-4 fps), cakupan backend belum terukur, sync nyata ke Overpass/Google belum diuji, CI GitHub belum pernah dijalankan.');
  const t = [['455', 'tes Pest', 'backend'], ['299', 'tes Vitest', 'frontend'], ['46', 'tes E2E', 'Playwright + axe'], ['0', 'temuan kritis', 'audit keamanan']];
  const cw = 2.9, gap = 0.14;
  t.forEach(([v, a, b], i) => {
    const x = MX + i * (cw + gap);
    card(s, x, 1.5, cw, 1.7, { line: i === 3 ? C.hot : C.line });
    s.addText(v, { x: x + 0.2, y: 1.55, w: cw - 0.4, h: 0.95, fontFace: F.mono, fontSize: 48, bold: true, color: i === 3 ? C.hot : C.focus, margin: 0, valign: 'middle' });
    body(s, a, x + 0.2, 2.5, cw - 0.4, 0.3, { size: 14, bold: true });
    body(s, b, x + 0.2, 2.82, cw - 0.4, 0.3, { size: 11, color: C.muted });
  });
  card(s, MX, 3.4, 6.0, 3.4);
  hud(s, 'Refactoring terukur', MX + 0.25, 3.58, 5, C.focus, 11);
  [['Ekspor CSV 14,6 ribu baris', '0,80 → 0,08 dtk'], ['Kolom Kanban (EXPLAIN)', '12,0 → 0,30 ms'], ['Chunk JS app', '525 → 284 kB']].forEach(([a, b], i) => {
    const y = 4.1 + i * 0.85;
    body(s, a, MX + 0.25, y, 3.0, 0.5, { size: 13, color: C.muted, valign: 'middle' });
    s.addText(b, { x: MX + 3.1, y, w: 2.7, h: 0.5, fontFace: F.mono, fontSize: 15, bold: true, color: C.text, align: 'right', margin: 0, valign: 'middle' });
  });
  card(s, 6.8, 3.4, 5.93, 3.4, { line: C.warm });
  hud(s, 'Keterbatasan jujur', 7.05, 3.58, 5, C.warm, 11);
  s.addText(bullets(['FPS pada GPU nyata belum terukur (SwiftShader 2–4 fps)', 'Sync nyata ke Overpass/Google belum diuji', 'Cakupan baris backend belum terukur', 'CI GitHub belum pernah dijalankan'], 15), { x: 7.05, y: 4.05, w: 5.4, h: 2.6, valign: 'top', margin: 0 });
}

// ---------- 11. Adopsi ----------
{
  const s = base('11 · Rencana adopsi & biaya', 'Dari demo ke pilot 6–8 minggu');
  const a = [['Data outlet asli', 'Impor CSV dari Hitachi POS; endpoint sinkron kemudian. Tidak akses DB POS langsung.'],
    ['Biaya', 'OSM + Mock = Rp0. Google nonaktif bawaan; menyalakan hanya di kota pilot hemat ±90%.'],
    ['Pilot', '1–2 provinsi, 10–20 outlet, 4–6 akun, 6–8 minggu; 1 VM kecil.'],
    ['Etika outreach', 'Maks 1 kontak/14 hari, peringatan kontak ganda, opt-out, tanpa kirim otomatis.'],
    ['Nilai terukur', 'Prospek per outlet, waktu hemat sales, konversi per tahap, penetrasi provinsi.'],
    ['Risiko + mitigasi', 'Libatkan sales sejak awal; tinjauan legal; kuota Google; cache + mirror OSM.']];
  const cw = 3.9, ch = 2.2, gx = 0.2, gy = 0.2;
  a.forEach(([t, d], i) => {
    const x = MX + (i % 3) * (cw + gx), y = 1.55 + Math.floor(i / 3) * (ch + gy);
    card(s, x, y, cw, ch, { line: i === 1 ? C.hot : C.line });
    hud(s, String(i + 1).padStart(2, '0'), x + 0.25, y + 0.2, 1, C.hot, 11);
    body(s, t, x + 0.25, y + 0.55, cw - 0.5, 0.4, { size: 17, bold: true });
    body(s, d, x + 0.25, y + 1.05, cw - 0.5, 1.2, { size: 12.5, color: C.muted });
  });
  body(s, 'Estimasi Google: ±4.900 panggilan searchNearby + ±950 Place Details per bulan (190 outlet, sync mingguan); tarif diisi sendiri di halaman Estimasi.', MX, 6.5, 12.1, 0.5, { size: 11, color: C.muted });
}

// ---------- 12. Penutup ----------
{
  const s = pptx.addSlide(); n += 1;
  s.background = { color: C.bg };
  [6.4, 5.0, 3.6, 2.2].forEach((d, i) => s.addShape('ellipse', { x: 10.2 - d / 2 + 0.2, y: 3.6 - d / 2, w: d, h: d, fill: { type: 'none' }, line: { color: i === 3 ? C.hot : C.line, width: i === 3 ? 1.5 : 1 } }));
  hud(s, '12 · Penutup', MX, 0.8, 8, C.hot, 12);
  s.addText('Terima kasih', { x: MX, y: 1.5, w: 9, h: 1.3, fontFace: F.head, fontSize: 60, bold: true, color: C.text, margin: 0 });
  body(s, 'Dari tebakan menjadi peta: prospek panas terlihat, kontak tercatat, nilai terukur.', MX, 3.0, 8, 1.0, { size: 20, color: C.focus });
  s.addShape('roundRect', { x: MX, y: 4.5, w: 8.2, h: 0.9, rectRadius: 0.1, fill: { color: C.surface }, line: { color: C.hot, width: 1.5 } });
  s.addText([{ text: 'LINK GITHUB (placeholder)', options: { fontFace: F.mono, fontSize: 10, bold: true, color: C.hot, breakLine: true } },
    { text: 'https://github.com/USERNAME/pos-merchant-hunter', options: { fontFace: F.mono, fontSize: 16, color: C.text } }],
    { x: MX + 0.25, y: 4.5, w: 7.8, h: 0.9, valign: 'middle', margin: 0 });
  body(s, 'Final Project Claude Code · Hacktiv8 · untuk PT Hitachi Channel Solutions Indonesia\nNama Peserta (placeholder)', MX, 5.8, 9, 0.8, { size: 12, color: C.muted });
  s.addNotes('Penutup: ringkas nilai (peta, skor, pipeline, rute, dasbor), sebutkan rencana pilot dan biaya Rp0 dengan OSM, lalu tampilkan link GitHub. Ganti USERNAME dengan akun GitHub sebelum dikumpulkan. Tidak ada logo/merek Hitachi di deck ini (hanya teks).');
}

await pptx.writeFile({ fileName: OUT });
console.log('Ditulis:', OUT);
