// Deck presentasi manajemen: POS Merchant Hunter (28 slide, transisi fade, tautan navigasi).
// Jalankan: DECK_JPG=<folder jpg> node tools/deck/build-presentation.mjs  (lihat README.md)
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const DEPS = process.env.DECK_DEPS || '/private/tmp/claude-501/deck-deps';
const require = createRequire(path.join(DEPS, 'package.json'));
const PptxGenJS = require('pptxgenjs');
const JSZip = require('jszip');

const JPG = process.env.DECK_JPG;
const OUT = process.env.DECK_OUT || 'POS-Merchant-Hunter_Presentasi-Manajemen.pptx';
const NAME = process.env.DECK_NAME || 'Muhamad Fauzian S';
const REPO = 'https://github.com/ziancuk/merchant-hunter';
const TOTAL = 28;

const C = { bg: 'F6F3EE', surface: 'FFFFFF', raised: 'EFEAE1', line: 'DAD3C6', text: '14213D', muted: '5A6478',
  hot: 'D9481C', warm: 'A66A0A', ok: '0F8B7D', focus: '0F7A6E', cold: '5B6B82', danger: 'B83227', onHot: 'FFFFFF', navy: '14213D' };
// pptxgenjs mutates shadow objects in place, so every shape needs a fresh one.
const SH = () => ({ type: 'outer', blur: 8, offset: 2, angle: 90, color: '1B2A4A', opacity: 0.10 });
const FH = 'Georgia';
const F = 'Arial';
const W = 13.333, MX = 0.6;

const dim = {
  '01-peta': [1600, 1000], '03-drawer': [1600, 1000], '04-kanban': [1600, 1000], '05-dashboard': [1600, 1000],
  '07-rute': [1600, 1000], '07-laporan': [1600, 1000], '10-heatmap': [1600, 1000],
  'ev-agents': [1800, 1141], 'ev-git': [1800, 1407], 'ev-prd': [1800, 1246], 'ev-prompt': [1800, 1575],
};

const pptx = new PptxGenJS();
pptx.layout = 'LAYOUT_WIDE';
pptx.title = 'POS Merchant Hunter - Presentasi Manajemen';
pptx.author = NAME;
let n = 0;

function slide(label, title, { notes = '', bare = false } = {}) {
  const s = pptx.addSlide();
  n += 1;
  s.background = { color: C.bg };
  s.addShape('ellipse', { x: 10.9, y: -1.9, w: 4.6, h: 4.6, fill: { type: 'none' }, line: { color: C.line, width: 0.75 } });
  s.addShape('ellipse', { x: 11.6, y: -1.2, w: 3.2, h: 3.2, fill: { type: 'none' }, line: { color: C.line, width: 0.75 } });
  if (!bare) {
    s.addText(label.toUpperCase(), { x: MX, y: 0.35, w: 9, h: 0.3, fontFace: F, fontSize: 11, color: C.hot, bold: true, charSpacing: 3, margin: 0 });
    s.addText(title, { x: MX, y: 0.66, w: 11.4, h: 0.75, fontFace: FH, fontSize: 28, bold: true, color: C.navy, margin: 0, valign: 'top' });
    s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 7.5, fill: { color: C.navy }, line: { color: C.navy, width: 0 } });
    s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 1.5, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
    s.addText('POS Merchant Hunter', { x: MX, y: 7.1, w: 4, h: 0.25, fontFace: F, fontSize: 9, color: C.muted, margin: 0, hyperlink: { slide: 1, tooltip: 'Ke sampul' } });
    s.addText('☰ Agenda', { x: W - MX - 3.2, y: 7.1, w: 1.2, h: 0.25, fontFace: F, fontSize: 9, color: C.focus, align: 'right', margin: 0, hyperlink: { slide: 2, tooltip: 'Ke agenda' } });
    s.addText(`${String(n).padStart(2, '0')} / ${TOTAL}`, { x: W - MX - 1.8, y: 7.1, w: 1.8, h: 0.25, fontFace: F, fontSize: 9, color: C.muted, align: 'right', margin: 0 });
  }
  if (notes) s.addNotes(notes);
  return s;
}

const card = (s, x, y, w, h, o = {}) => s.addShape('roundRect', { x, y, w, h, rectRadius: 0.08, fill: { color: o.fill || C.surface }, line: { color: o.line || C.line, width: 0.75 }, shadow: SH() });

function img(s, name, x, y, w, o = {}) {
  const [pw, ph] = dim[name];
  const h = w * ph / pw;
  s.addShape('roundRect', { x: x - 0.05, y: y - 0.05, w: w + 0.1, h: h + 0.1, rectRadius: 0.06, fill: { color: C.surface }, line: { color: o.line || C.line, width: 1 }, shadow: SH() });
  s.addImage({ path: path.join(JPG, name + '.jpg'), x, y, w, h });
  return h;
}

function bullets(s, items, x, y, w, h, o = {}) {
  s.addText(items.map((t) => ({ text: t, options: { bullet: { indent: 14 }, breakLine: true, paraSpaceAfter: o.gap ?? 7 } })),
    { x, y, w, h, fontFace: F, fontSize: o.size || 14, color: o.color || C.text, margin: 0, valign: 'top' });
}

function caption(s, text, x, y, w) {
  s.addText(text, { x, y, w, h: 0.3, fontFace: F, fontSize: 10.5, italic: true, color: C.muted, margin: 0, valign: 'top' });
}

function stat(s, x, y, w, big, small, color = C.focus) {
  card(s, x, y, w, 1.25);
  s.addText(big, { x: x + 0.15, y: y + 0.12, w: w - 0.3, h: 0.7, fontFace: FH, fontSize: 30, bold: true, color, margin: 0, valign: 'middle' });
  s.addText(small, { x: x + 0.15, y: y + 0.8, w: w - 0.3, h: 0.38, fontFace: F, fontSize: 10.5, color: C.muted, margin: 0, valign: 'top' });
}

// ---------- 1 Sampul ----------
{
  const s = slide('', '', { bare: true, notes: 'Pembuka: "Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan; dengan POS Merchant Hunter, jawabannya ada di peta."' });
  s.background = { color: C.navy };
  s.addShape('ellipse', { x: 8.2, y: -2.2, w: 7.5, h: 7.5, fill: { type: 'none' }, line: { color: '2C3E66', width: 1 } });
  s.addShape('ellipse', { x: 9.4, y: -1.0, w: 5.1, h: 5.1, fill: { type: 'none' }, line: { color: '2C3E66', width: 1 } });
  s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 7.5, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
  s.addText('FINAL PROJECT CLAUDE CODE · HACKTIV8', { x: 0.8, y: 0.7, w: 8, h: 0.3, fontFace: F, fontSize: 12, bold: true, color: 'F2A27F', charSpacing: 3, margin: 0 });
  s.addText([{ text: 'POS Merchant', options: { breakLine: true } }, { text: 'Hunter', options: { color: 'F2A27F' } }],
    { x: 0.8, y: 1.15, w: 6.2, h: 2.3, fontFace: FH, fontSize: 56, bold: true, color: 'FFFFFF', margin: 0, valign: 'top' });
  s.addShape('rect', { x: 0.8, y: 3.55, w: 0.9, h: 0.05, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
  s.addText('Peta 3D, skor prospek, pipeline, dan kontak WhatsApp yang tercatat untuk menemukan calon merchant Hitachi POS berikutnya.',
    { x: 0.8, y: 3.75, w: 5.6, h: 1.0, fontFace: F, fontSize: 16, color: 'D5DCEA', margin: 0, valign: 'top' });
  [['190', 'outlet dummy · 38 provinsi'], ['14,6 rb', 'prospek dalam radius 2 km'], ['0–100', 'skor dengan alasan terbaca']].forEach(([b, t], i) => {
    const x = 0.8 + i * 1.95;
    s.addShape('roundRect', { x, y: 5.0, w: 1.8, h: 1.05, rectRadius: 0.08, fill: { color: '1F3158' }, line: { color: '33477A', width: 0.75 } });
    s.addText(b, { x, y: 5.05, w: 1.8, h: 0.5, fontFace: FH, fontSize: 22, bold: true, color: 'FFFFFF', align: 'center', margin: 0 });
    s.addText(t, { x: x + 0.08, y: 5.55, w: 1.64, h: 0.45, fontFace: F, fontSize: 9.5, color: 'B9C4DA', align: 'center', margin: 0, valign: 'top' });
  });
  s.addText(NAME, { x: 0.8, y: 6.3, w: 6, h: 0.3, fontFace: F, fontSize: 14, bold: true, color: 'FFFFFF', margin: 0 });
  s.addText('untuk PT Hitachi Channel Solutions Indonesia · Oktober 2026', { x: 0.8, y: 6.65, w: 6.5, h: 0.3, fontFace: F, fontSize: 11, color: 'B9C4DA', margin: 0 });
  s.addShape('roundRect', { x: 6.7, y: 1.3, w: 6.2, h: 3.9, rectRadius: 0.06, fill: { color: 'FFFFFF' }, line: { color: 'FFFFFF', width: 0 }, shadow: { type: 'outer', blur: 14, offset: 4, angle: 90, color: '000000', opacity: 0.35 } });
  s.addImage({ path: path.join(JPG, '01-peta.jpg'), x: 6.8, y: 1.4, w: 6.0, h: 3.75 });
  s.addText('Tangkapan layar aplikasi berjalan (data demo)', { x: 6.7, y: 5.35, w: 6.2, h: 0.3, fontFace: F, fontSize: 10, italic: true, color: 'B9C4DA', margin: 0 });
}

// ---------- helper tambahan ----------
const dot = (s, x, y, d, col, txt, o = {}) => {
  s.addShape('ellipse', { x, y, w: d, h: d, fill: { color: col }, line: { color: o.line || col, width: 0 } });
  if (txt !== undefined) s.addText(String(txt), { x, y, w: d, h: d, fontFace: FH, fontSize: o.size || 14, bold: true, color: o.color || 'FFFFFF', align: 'center', valign: 'middle', margin: 0 });
};
// kartu langkah besar bernomor (gaya panduan): judul + satu kalimat
function steps(s, items, y, h, cols = items.length) {
  const gap = 0.25, w = (12.1 - gap * (cols - 1)) / cols;
  items.forEach(([t, d], i) => {
    const x = MX + i * (w + gap);
    card(s, x, y, w, h);
    dot(s, x + 0.3, y + 0.3, 0.7, C.hot, i + 1, { size: 22 });
    s.addText(t, { x: x + 0.3, y: y + 1.15, w: w - 0.6, h: 0.5, fontFace: FH, fontSize: 20, bold: true, color: C.navy, margin: 0 });
    s.addText(d, { x: x + 0.3, y: y + 1.75, w: w - 0.6, h: h - 1.95, fontFace: F, fontSize: 14, color: C.muted, margin: 0, valign: 'top' });
  });
}
const note = (s, text, y = 6.35) => {
  card(s, MX, y, 12.1, 0.55, { fill: C.raised, line: C.raised });
  s.addText(text, { x: MX + 0.3, y, w: 11.5, h: 0.55, fontFace: F, fontSize: 12.5, italic: true, color: C.navy, margin: 0, valign: 'middle' });
};
const sub = (s, t, x, y, w, col = C.focus) => s.addText(t, { x, y, w, h: 0.4, fontFace: FH, fontSize: 17, bold: true, color: col, margin: 0 });

// ---------- 2 Agenda ----------
{
  const s = slide('Agenda', 'Isi presentasi (klik untuk melompat)', { notes: 'Tiap kartu adalah tautan ke bagiannya.' });
  const items = [
    ['01', 'Masalah dan solusi', 'Mengapa tim sales butuh peta, bukan tebakan', 3],
    ['02', 'Ringkasan PRD', 'Tujuan, metrik pilot, batas v1, risiko', 6],
    ['03', 'Cara kami membangun', 'Prompt, dokumen, subagen Claude Code, review, git', 9],
    ['04', 'Aplikasi, langkah demi langkah', 'Peta, skor, kontak sopan, pipeline, rute, laporan', 15],
    ['05', 'Arsitektur dan mutu', 'Cara kerja, hasil tes, hal yang belum teruji', 22],
    ['06', 'Rencana adopsi', 'Pilot 8 minggu, biaya, keputusan yang diminta', 24],
    ['07', 'Tanya jawab dan repo', 'Pertanyaan umum, tautan GitHub, folder docs/', 27],
  ];
  items.forEach(([no, t, d, target], i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = MX + col * 6.1, y = 1.7 + row * 1.32;
    card(s, x, y, 5.9, 1.12);
    s.addText(no, { x: x + 0.2, y: y + 0.12, w: 0.9, h: 0.9, fontFace: FH, fontSize: 30, bold: true, color: C.hot, margin: 0, valign: 'middle' });
    s.addText(t, { x: x + 1.15, y: y + 0.17, w: 4.6, h: 0.4, fontFace: F, fontSize: 17, bold: true, color: C.navy, margin: 0, hyperlink: { slide: target } });
    s.addText(d, { x: x + 1.15, y: y + 0.58, w: 4.6, h: 0.45, fontFace: F, fontSize: 11.5, color: C.muted, margin: 0, valign: 'top' });
  });
}

// ---------- 3 Masalah ----------
{
  const s = slide('01 · Masalah', 'Mencari merchant baru masih seperti menebak', { notes: 'Empat masalah konkret dari PRD §1.' });
  const ps = [
    ['Tidak ada gambaran peta', 'Outlet kita tidak pernah dilihat bersama bisnis di sekitarnya. Area yang belum tergarap sulit terlihat.', C.hot],
    ['Cari sendiri-sendiri', 'Tiap sales mencari dengan caranya sendiri. Tidak ada nilai yang menunjukkan mana yang paling layak dihubungi dulu.', C.warm],
    ['Kontak dobel', 'Rekan lain mungkin sudah menghubungi orang yang sama, dan tidak ada catatan bersama.', C.ok],
    ['Hasil tidak terukur', 'Manajemen tidak punya angka: berapa calon per outlet, berapa yang jadi, berapa biayanya.', C.cold],
  ];
  ps.forEach(([t, d, col], i) => {
    const x = MX + i * 3.08;
    card(s, x, 1.75, 2.9, 3.4);
    s.addShape('rect', { x, y: 1.75, w: 2.9, h: 0.08, fill: { color: col }, line: { color: col, width: 0 } });
    dot(s, x + 0.25, 2.1, 0.6, col, i + 1, { size: 20 });
    s.addText(t, { x: x + 0.25, y: 2.9, w: 2.45, h: 0.75, fontFace: FH, fontSize: 17, bold: true, color: C.navy, margin: 0, valign: 'top' });
    s.addText(d, { x: x + 0.25, y: 3.7, w: 2.45, h: 1.4, fontFace: F, fontSize: 12.5, color: C.muted, margin: 0, valign: 'top' });
  });
  card(s, MX, 5.45, 12.1, 1.15, { fill: C.navy, line: C.navy });
  s.addText('“Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan. Dengan POS Merchant Hunter, jawabannya ada di peta.”',
    { x: MX + 0.4, y: 5.5, w: 11.3, h: 1.05, fontFace: FH, fontSize: 17, italic: true, color: 'FFFFFF', margin: 0, valign: 'middle' });
}

// ---------- 4 Solusi ----------
{
  const s = slide('01 · Solusi', 'Solusinya: satu aplikasi, tiga langkah', { notes: 'Temukan, Nilai, Hubungi. Semua tercatat dan terlihat bersama oleh tim.' });
  steps(s, [['Temukan', 'Peta menampilkan semua outlet dan bisnis di sekitarnya, paling jauh 2 km. Pencarian berjalan otomatis tiap malam.'],
    ['Nilai', 'Setiap calon diberi skor 0 sampai 100 beserta alasannya, jadi sales tahu siapa yang dihubungi lebih dulu.'],
    ['Hubungi', 'Satu klik membuka WhatsApp dengan pesan yang sudah terisi. Hasilnya dicatat otomatis di pipeline.']], 1.7, 3.9);
  note(s, 'Semuanya ada di satu tempat dan bisa dilihat bersama oleh tim: peta, skor, pipeline, rute kunjungan, dasbor, dan laporan.', 5.95);
}

// ---------- 5 Sehari ----------
{
  const s = slide('01 · Solusi', 'Satu hari bersama aplikasi', { notes: 'Scheduler jalan 01:00 WIB; pengguna tidak menunggu API pihak ketiga saat membuka halaman.' });
  const d = [['Malam', '01.00 WIB', 'Sistem mencari bisnis baru di sekitar outlet secara otomatis.', C.navy], ['Pagi', 'Buka peta', 'Tim melihat calon paling menarik dan area yang belum tergarap.', C.focus],
    ['Siang', 'Hubungi', 'Kirim lewat WhatsApp. Aplikasi mencatat siapa dan kapan.', C.hot], ['Sore', 'Laporan', 'Atasan melihat dasbor dan hasil tim hari itu.', C.warm]];
  d.forEach(([t, k, x, col], i) => {
    const px = MX + i * 3.08;
    card(s, px, 1.75, 2.9, 3.9);
    s.addShape('roundRect', { x: px, y: 1.75, w: 2.9, h: 1.1, rectRadius: 0.08, fill: { color: col }, line: { color: col, width: 0 } });
    s.addText(t, { x: px + 0.25, y: 1.85, w: 2.4, h: 0.55, fontFace: FH, fontSize: 24, bold: true, color: 'FFFFFF', margin: 0 });
    s.addText(k, { x: px + 0.25, y: 2.35, w: 2.4, h: 0.35, fontFace: F, fontSize: 12, color: 'FFFFFF', margin: 0 });
    s.addText(x, { x: px + 0.25, y: 3.1, w: 2.45, h: 2.3, fontFace: F, fontSize: 15, color: C.navy, margin: 0, valign: 'top' });
    if (i < 3) s.addText('›', { x: px + 2.82, y: 2.05, w: 0.35, h: 0.5, fontFace: F, fontSize: 26, bold: true, color: C.hot, align: 'center', margin: 0 });
  });
  note(s, 'Pengguna tidak pernah menunggu pihak ketiga: pencarian sudah dikerjakan malam sebelumnya.', 6.0);
}

// ---------- 6 PRD ringkas ----------
{
  const s = slide('02 · Ringkasan PRD', 'PRD dalam satu halaman', { notes: 'Sumber: docs/PRD.md. 29 user story dipetakan ke fitur A–I.' });
  const cols = [['Untuk siapa', C.hot, ['Satu peran: “manajemen” (kepala cabang, manajer sales, analis)', 'Banyak akun, hak sama', 'Semua aktivitas tercatat: siapa, kapan, apa']],
    ['Apa yang dibangun', C.focus, ['Peta 3D outlet dan prospek (radius 2 km)', 'Skor 0–100 dengan alasan', 'Pipeline, WhatsApp, rute kunjungan', 'Dasbor, laporan CSV/Excel, estimasi biaya', 'Pencarian otomatis tiap malam']],
    ['Cara kerja data', C.warm, ['OpenStreetMap (gratis), simulasi (demo), Google (opsional)', '190 outlet dummy di 38 provinsi', 'Data simulasi selalu berlabel “Simulasi”', 'Outlet asli masuk lewat impor CSV']]];
  cols.forEach(([t, col, b], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.7, 3.9, 4.7);
    s.addShape('rect', { x, y: 1.7, w: 3.9, h: 0.08, fill: { color: col }, line: { color: col, width: 0 } });
    s.addText(t, { x: x + 0.3, y: 1.95, w: 3.4, h: 0.5, fontFace: FH, fontSize: 19, bold: true, color: C.navy, margin: 0 });
    bullets(s, b, x + 0.3, 2.65, 3.4, 3.6, { size: 13.5, gap: 9 });
  });
}

// ---------- 7 Metrik ----------
{
  const s = slide('02 · Ringkasan PRD', 'Seperti apa “berhasil” di pilot 90 hari', { notes: 'Sumber: PRD §2. Konversi 3% adalah asumsi awal yang bisa diubah di kalkulator.' });
  const m = [['≥ 25', 'calon aktif per outlet', C.hot], ['−50%', 'waktu riset sales', C.focus], ['0', 'kontak ganda tanpa peringatan', C.warm], ['≥ 3%', 'Dihubungi → Deal (asumsi awal)', C.cold], ['Rp0', 'biaya data dengan OpenStreetMap', C.ok], ['≥ 80%', 'akun aktif tiap minggu', C.navy]];
  m.forEach(([b, t, col], i) => {
    const x = MX + (i % 3) * 4.1, y = 1.75 + Math.floor(i / 3) * 2.35;
    card(s, x, y, 3.9, 2.15);
    s.addText(b, { x: x + 0.3, y: y + 0.2, w: 3.3, h: 1.0, fontFace: FH, fontSize: 46, bold: true, color: col, margin: 0, valign: 'middle' });
    s.addText(t, { x: x + 0.3, y: y + 1.25, w: 3.3, h: 0.7, fontFace: F, fontSize: 14, color: C.muted, margin: 0, valign: 'top' });
  });
  note(s, 'Semua angka dibaca langsung dari Dasbor dan halaman Estimasi Dampak & Biaya. Sumber: docs/PRD.md §2.', 6.5);
}

// ---------- 8 Batas & risiko ----------
{
  const s = slide('02 · Ringkasan PRD', 'Batas versi 1 dan risikonya', { notes: 'Sumber: PRD §5 dan §8.' });
  card(s, MX, 1.7, 3.9, 4.8);
  sub(s, 'Termasuk v1', MX + 0.3, 1.85, 3.4, C.ok);
  bullets(s, ['Login banyak akun, satu peran', 'Peta 3D dan intro globe', 'Skor, kartu prospek, WhatsApp', 'Pipeline Kanban dan tabel', 'Rute kunjungan', 'Dasbor, laporan, estimasi biaya', 'Master data dan pencarian malam'], MX + 0.3, 2.45, 3.4, 4, { size: 13, gap: 6 });
  card(s, MX + 4.1, 1.7, 3.9, 4.8);
  sub(s, 'Belum termasuk v1', MX + 4.4, 1.85, 3.4, C.cold);
  bullets(s, ['Terhubung langsung ke database Hitachi POS (v1: impor CSV)', 'Kirim WhatsApp otomatis atau massal', 'Banyak peran dan hak akses', 'Aplikasi mobile native', 'Prediksi berbasis ML'], MX + 4.4, 2.45, 3.4, 4, { size: 13, gap: 8 });
  card(s, MX + 8.2, 1.7, 3.9, 4.8, { line: C.hot });
  sub(s, 'Risiko dan jawabannya', MX + 8.5, 1.85, 3.4, C.hot);
  bullets(s, ['Lisensi Google → hanya place_id disimpan, Google nonaktif bawaan', 'Batas layanan gratis → antrean, cache, data simulasi cadangan', 'Data OSM tipis → ditampilkan apa adanya', 'Spam WhatsApp → jeda kontak, opt-out, tanpa kirim otomatis', 'UU PDP → data bisnis publik, tinjau legal dulu'], MX + 8.5, 2.45, 3.4, 4, { size: 12, gap: 7 });
}

// ---------- 9 Cara membangun ----------
{
  const s = slide('03 · Cara kami membangun', 'Spec-first, diorkestrasi Claude Code', { notes: 'Inti: satu PROMPT.md, empat sesi, subagen paralel, review independen sebelum dan sesudah kode.' });
  const st = [['PROMPT.md', 'Satu spesifikasi lengkap: konteks, aturan keras, stack, fitur, urutan build'], ['Dokumen dulu', 'PRD, TRD, skema DB, kontrak API, rencana, tes (tag v0.1-docs)'], ['Review dokumen', 'Subagen read-only menemukan 19 masalah sebelum kode ditulis'],
    ['Build paralel', 'Agen penulis di git worktree terpisah; orkestrator menggabung dan menguji'], ['QA dan keamanan', 'Review kode, uji performa, QA browser, audit keamanan, refactoring'], ['Rilis', 'README, panduan, tag v1.0.0, pemindaian secret']];
  st.forEach(([t, d], i) => {
    const col = i % 3, row = Math.floor(i / 3);
    const x = MX + col * 4.1, y = 1.75 + row * 2.4;
    card(s, x, y, 3.8, 2.1, { line: i === 0 ? C.hot : C.line });
    dot(s, x + 0.25, y + 0.25, 0.55, C.hot, i + 1, { size: 17 });
    s.addText(t, { x: x + 1.0, y: y + 0.25, w: 2.7, h: 0.55, fontFace: FH, fontSize: 17, bold: true, color: C.navy, margin: 0, valign: 'middle' });
    s.addText(d, { x: x + 0.25, y: y + 0.95, w: 3.35, h: 1.1, fontFace: F, fontSize: 12.5, color: C.muted, margin: 0, valign: 'top' });
    if (col < 2) s.addText('›', { x: x + 3.78, y: y + 0.7, w: 0.32, h: 0.5, fontFace: F, fontSize: 24, bold: true, color: C.hot, align: 'center', margin: 0 });
  });
  s.addText('Lima slide berikut menampilkan bukti asli dari repo.', { x: MX, y: 6.65, w: 12, h: 0.3, fontFace: F, fontSize: 12, italic: true, color: C.muted, margin: 0 });
}

// ---------- 10 Bukti prompt ----------
{
  const s = slide('03 · Bukti proses 1/5', 'Prompt: spesifikasi master yang kami tulis', { notes: 'Gambar adalah cuplikan berkas PROMPT.md yang sebenarnya (bagian 1 dan 2). Bila ingin menambah tangkapan layar sesi Claude Code, tempel di slide ini.' });
  const h = img(s, 'ev-prompt', MX, 1.6, 5.2);
  caption(s, 'Cuplikan asli PROMPT.md (baris 1–25).', MX, 1.7 + h + 0.12, 6);
  const x = 7.2;
  sub(s, 'Apa yang kami tulis di prompt', x, 1.6, 5.5);
  bullets(s, ['Peran: engineer senior sekaligus orkestrator tim subagen', 'Konteks: tema wajib, tenggat, lima hal yang dikumpulkan', 'Aturan keras: data dummy, tanpa secret, badge “Simulasi”, kepatuhan Google Places', 'Stack, 9 kelompok fitur, urutan build, strategi tes, alur git', '“Jangan klaim sudah jalan sebelum diverifikasi”'], x, 2.15, 5.5, 3.2, { size: 13.5, gap: 9 });
  card(s, x, 5.45, 5.5, 1.05, { fill: C.raised, line: C.raised });
  s.addText('Tiap sesi baru cukup berkata: “Baca PROMPT.md dan kerjakan Sesi N”.', { x: x + 0.25, y: 5.5, w: 5.0, h: 0.95, fontFace: FH, fontSize: 14, italic: true, color: C.navy, margin: 0, valign: 'middle' });
}

// ---------- 11 Bukti PRD ----------
{
  const s = slide('03 · Bukti proses 2/5', 'Dari prompt ke PRD dan dokumen pendukung', { notes: 'PRD, TRD, DB-SCHEMA, ARCHITECTURE, CONTRACTS, IMPLEMENTATION-PLAN, TEST-PLAN, GIT-WORKFLOW dibuat di Sesi 1.' });
  const h = img(s, 'ev-prd', MX, 1.6, 6.9);
  caption(s, 'Cuplikan asli docs/PRD.md §1–§2.', MX, 1.7 + h + 0.12, 6.9);
  const x = 7.9;
  sub(s, 'Dokumen yang dihasilkan di Sesi 1', x, 1.6, 4.8);
  [['PRD', 'masalah, metrik, user story, adopsi'], ['TRD', 'keputusan teknis, lisensi data'], ['DB-SCHEMA', 'skema beku, 12 migrasi'], ['CONTRACTS', 'kontrak API dan tipe'], ['ARCHITECTURE', 'lapisan dan alur data'], ['IMPLEMENTATION-PLAN', 'milestone, agen, kepemilikan berkas'], ['TEST-PLAN · GIT-WORKFLOW', 'strategi tes, branch, PR']].forEach(([a, b], i) => {
    const y = 2.15 + i * 0.62;
    card(s, x, y, 4.8, 0.52);
    s.addText(a, { x: x + 0.15, y, w: 2.2, h: 0.52, fontFace: F, fontSize: 11, bold: true, color: C.warm, margin: 0, valign: 'middle' });
    s.addText(b, { x: x + 2.3, y, w: 2.45, h: 0.52, fontFace: F, fontSize: 10.5, color: C.muted, margin: 0, valign: 'middle' });
  });
  s.addText('Total docs/: 15 berkas, ±2.100 baris.', { x, y: 6.55, w: 4.8, h: 0.3, fontFace: F, fontSize: 11, italic: true, color: C.muted, margin: 0 });
}

// ---------- 12 Orkestrasi ----------
{
  const s = slide('03 · Bukti proses 3/5', 'Subagen: siapa mengerjakan apa', { notes: 'Tabel asli docs/AGENTS-LOG.md. Empat sesi: dokumen, backend + design system, frontend + E2E, refactor + QA + rilis.' });
  const h = img(s, 'ev-agents', MX, 1.6, 7.4);
  caption(s, 'Cuplikan asli docs/AGENTS-LOG.md (Sesi 1).', MX, 1.7 + h + 0.12, 7.4);
  stat(s, 8.4, 1.6, 2.05, '4', 'sesi kerja');
  stat(s, 10.6, 1.6, 2.1, '20+', 'subagen tercatat');
  sub(s, 'Pola kerja', 8.4, 3.1, 4.3);
  bullets(s, ['Riset dan review: read-only, hasilnya laporan', 'Penulis kode: maksimal 3 paralel, masing-masing di worktree git sendiri dan menulis tesnya', 'Orkestrator menggabung, memperbaiki integrasi, menjalankan tes penuh', 'Setiap masalah dicatat, tidak disembunyikan'], 8.4, 3.6, 4.3, 3.0, { size: 13, gap: 8 });
}

// ---------- 13 Review independen ----------
{
  const s = slide('03 · Bukti proses 4/5', 'Review independen sebelum dan sesudah kode', { notes: 'Sumber: AGENTS-LOG.md, TEST-REPORT.md §6, qa-browser-notes.md.' });
  const items = [
    ['19', 'masalah di dokumen', '2 kritis, 11 mayor, 6 minor. Contoh kritis: “white space” mustahil dihitung; nomor simulasi bisa di-WhatsApp. Diperbaiki sebelum kode ditulis.', C.hot],
    ['1 · 9 · 14', 'review backend (bug · mayor · minor)', 'Satu bug error 500, indeks database, cache pengaturan, balapan batas kontak, ekspor CSV lambat.', C.warm],
    ['1 · 14', 'QA di browser (mayor · minor)', 'Tanpa bug kritis. Panel filter ponsel, label Inggris, “0 hari lalu”: diperbaiki dan diberi tes.', C.ok],
    ['0 · 2 · 8', 'audit keamanan (kritis · mayor · minor)', 'CSP kini aktif. Pemindaian secret di seluruh riwayat git: 0 temuan.', C.cold],
  ];
  items.forEach(([big, t, d, col], i) => {
    const x = MX + (i % 2) * 6.1, y = 1.7 + Math.floor(i / 2) * 2.4;
    card(s, x, y, 5.9, 2.2);
    s.addText(big, { x: x + 0.25, y: y + 0.15, w: 2.4, h: 0.8, fontFace: FH, fontSize: 28, bold: true, color: col, margin: 0, valign: 'middle' });
    s.addText(t, { x: x + 2.7, y: y + 0.15, w: 3.1, h: 0.8, fontFace: F, fontSize: 13, bold: true, color: C.navy, margin: 0, valign: 'middle' });
    s.addText(d, { x: x + 0.25, y: y + 1.05, w: 5.4, h: 1.1, fontFace: F, fontSize: 12.5, color: C.muted, margin: 0, valign: 'top' });
  });
  note(s, 'Hasil refactoring terukur: ekspor CSV 0,80 → 0,08 detik (10× lebih cepat); bundel awal 525 → 284 kB (−46%).', 6.5);
}

// ---------- 14 Git ----------
{
  const s = slide('03 · Bukti proses 5/5', 'Riwayat git: cabang, merge, dan tag rilis', { notes: 'Output asli git log --graph. Cabang worktree-agent-* adalah agen paralel Claude Code.' });
  const h = img(s, 'ev-git', MX, 1.6, 6.4);
  caption(s, 'Output asli `git log --graph --oneline`.', MX, 1.7 + h + 0.12, 6.4);
  const x = 7.5;
  sub(s, 'Rilis bertanda', x, 1.6, 5.2);
  [['v0.1-docs', '24 Okt', 'PRD, TRD, arsitektur, kontrak'], ['v0.2-backend', '24 Okt', 'provider, scheduler, domain, design system'], ['v0.3-app', '25 Okt', 'peta, pipeline, dasbor, E2E'], ['v1.0.0', '25 Okt', 'refactor, QA, README, slide']].forEach(([a, d, b], i) => {
    const y = 2.2 + i * 0.85;
    dot(s, x, y + 0.08, 0.28, C.hot);
    if (i < 3) s.addShape('line', { x: x + 0.14, y: y + 0.38, w: 0, h: 0.6, line: { color: C.line, width: 1.5 } });
    s.addText([{ text: a + '  ', options: { bold: true, color: C.navy } }, { text: d, options: { color: C.muted } }], { x: x + 0.5, y, w: 4.6, h: 0.3, fontFace: F, fontSize: 14, margin: 0 });
    s.addText(b, { x: x + 0.5, y: y + 0.32, w: 4.6, h: 0.3, fontFace: F, fontSize: 11.5, color: C.muted, margin: 0 });
  });
  note(s, '102 commit per 27 Okt 2026. Setelah v1.0.0: peta terang, halaman Laporan, dua bahasa ID/EN.', 5.75);
}

// ---------- 15 Peta ----------
{
  const s = slide('04 · Aplikasi', 'Peta: semua calon dalam satu pandangan', { notes: 'Demo: lewati intro, hover outlet untuk cincin 2 km, filter provinsi dan skor.' });
  const h = img(s, '01-peta', MX, 1.6, 8.0);
  caption(s, 'Tangkapan layar aplikasi. Data pada gambar adalah data contoh.', MX, 1.7 + h + 0.12, 8);
  const x = 9.0;
  sub(s, 'Cara membaca peta', x, 1.6, 3.7);
  [['Titik putih', 'Outlet Hitachi POS yang sudah ada.', 'FFFFFF', C.navy], ['Merah', 'Bidik Utama: calon paling menarik.', 'E8350E', null], ['Kuning', 'Menjanjikan: layak dicoba.', 'F59E0B', null], ['Hijau tosca', 'Berpotensi: peluang sedang.', '0E9F8E', null], ['Abu-abu', 'Pantau: prioritas rendah.', '6B7F91', null]].forEach(([t, d, col, ring], i) => {
    const y = 2.15 + i * 0.72;
    dot(s, x, y + 0.04, 0.34, col, undefined, { line: ring || col });
    if (ring) s.addShape('ellipse', { x: x + 0.1, y: y + 0.14, w: 0.14, h: 0.14, fill: { color: ring }, line: { color: ring, width: 0 } });
    s.addText(t, { x: x + 0.5, y, w: 3.2, h: 0.3, fontFace: F, fontSize: 13, bold: true, color: C.navy, margin: 0 });
    s.addText(d, { x: x + 0.5, y: y + 0.3, w: 3.2, h: 0.3, fontFace: F, fontSize: 11, color: C.muted, margin: 0 });
  });
  s.addText('Mode: Prospek · Heatmap · White Space · Cakupan 2 km. Ctrl/Cmd+K membuka pencarian cepat.', { x, y: 5.85, w: 3.75, h: 0.8, fontFace: F, fontSize: 11.5, italic: true, color: C.muted, margin: 0, valign: 'top' });
}

// ---------- 16 Kartu prospek ----------
{
  const s = slide('04 · Aplikasi', 'Klik satu titik, lihat kartunya', { notes: 'Kartu prospek: skor, rincian alasan, kontak dan tombol WhatsApp.' });
  const w = 7.6, x0 = MX, y0 = 1.6, h = img(s, '03-drawer', x0, y0, w);
  [[0.706, 0.2, 1], [0.764, 0.3, 2], [0.764, 0.88, 3]].forEach(([fx, fy, n]) => dot(s, x0 + fx * w - 0.2, y0 + fy * h - 0.2, 0.4, C.hot, n, { size: 14, line: 'FFFFFF' }));
  caption(s, 'Contoh kartu prospek (data simulasi, berlabel “Simulasi”).', MX, y0 + h + 0.12, w);
  [['Skor', 'Angka 0 sampai 100. Makin tinggi, makin layak dihubungi.'], ['Alasan skor', 'Enam faktor yang membuat skor naik atau turun, lengkap dengan angkanya.'], ['Kontak', 'Telepon, alamat, rating. Geser ke bawah untuk tombol WhatsApp.']].forEach(([t, d], i) => {
    const y = 1.7 + i * 1.65;
    card(s, 8.6, y, 4.1, 1.45);
    dot(s, 8.85, y + 0.25, 0.5, C.hot, i + 1, { size: 16 });
    s.addText(t, { x: 9.55, y: y + 0.22, w: 3.0, h: 0.5, fontFace: FH, fontSize: 17, bold: true, color: C.navy, margin: 0, valign: 'middle' });
    s.addText(d, { x: 8.85, y: y + 0.82, w: 3.7, h: 0.6, fontFace: F, fontSize: 12, color: C.muted, margin: 0, valign: 'top' });
  });
}

// ---------- 17 Skor ----------
{
  const s = slide('04 · Aplikasi', 'Cara membaca skor', { notes: 'Skor berbasis aturan (bukan ML). Bobot kategori bisa diubah di Master Kategori. Ambang Bidik Utama 75 dari config/hunter.php.' });
  [['Bidik Utama', '75 – 100', 'Hubungi lebih dulu', 'E8350E'], ['Menjanjikan', '55 – 74', 'Layak dicoba', 'F59E0B'], ['Berpotensi', '35 – 54', 'Jika ada waktu', '0E9F8E'], ['Pantau', '0 – 34', 'Prioritas rendah', '6B7F91']].forEach(([t, r, d, col], i) => {
    const x = MX + i * 3.08;
    card(s, x, 1.7, 2.9, 1.9);
    s.addShape('rect', { x, y: 1.7, w: 0.12, h: 1.9, fill: { color: col }, line: { color: col, width: 0 } });
    s.addText(t, { x: x + 0.35, y: 1.8, w: 2.4, h: 0.45, fontFace: FH, fontSize: 18, bold: true, color: C.navy, margin: 0 });
    s.addText(r, { x: x + 0.35, y: 2.25, w: 2.4, h: 0.6, fontFace: FH, fontSize: 26, bold: true, color: col, margin: 0 });
    s.addText(d, { x: x + 0.35, y: 2.95, w: 2.4, h: 0.4, fontFace: F, fontSize: 12.5, color: C.muted, margin: 0 });
  });
  sub(s, 'Skor dihitung dari 6 hal (total 100 poin)', MX, 3.95, 9);
  [['Kecocokan jenis usaha', 25], ['Rating dan ulasan', 20], ['Dekat outlet kita', 15], ['Area yang ramai', 15], ['Baru buka', 15], ['Kontak lengkap', 10]].forEach(([t, p], i) => {
    const x = MX + (i % 3) * 4.1, y = 4.55 + Math.floor(i / 3) * 1.0;
    card(s, x, y, 3.9, 0.85);
    s.addText(t, { x: x + 0.25, y, w: 2.7, h: 0.85, fontFace: F, fontSize: 13.5, bold: true, color: C.navy, margin: 0, valign: 'middle' });
    s.addText(`${p} poin`, { x: x + 2.6, y, w: 1.15, h: 0.85, fontFace: FH, fontSize: 15, bold: true, color: C.hot, align: 'right', margin: 0, valign: 'middle' });
  });
}

// ---------- 18 Sopan ----------
{
  const s = slide('04 · Aplikasi', 'Menghubungi dengan sopan', { notes: 'Sumber: PRD §9(c). Hanya tautan wa.me; manusia yang menekan kirim.' });
  steps(s, [['Pesan sudah terisi', 'Template per kategori, bisa diedit sebelum dikirim. Tombol aktif hanya bila ada nomor.'], ['Cegah kontak dobel', 'Muncul bila rekan sudah menghubungi dalam 7 hari terakhir: siapa dan kapan.'], ['Jangan hubungi lagi', 'Tandai calon yang menolak. Tombol WhatsApp-nya hilang permanen.']], 1.7, 3.3);
  card(s, MX, 5.25, 3.9, 1.35, { fill: C.navy, line: C.navy });
  s.addText('14 hari', { x: MX + 0.3, y: 5.3, w: 3.4, h: 0.7, fontFace: FH, fontSize: 32, bold: true, color: 'F2A27F', margin: 0, valign: 'middle' });
  s.addText('jeda minimal antara dua kontak ke calon yang sama (bisa diatur admin)', { x: MX + 0.3, y: 5.95, w: 3.4, h: 0.6, fontFace: F, fontSize: 11.5, color: 'FFFFFF', margin: 0, valign: 'top' });
  card(s, MX + 4.1, 5.25, 8.0, 1.35);
  bullets(s, ['Hanya data bisnis yang dipublikasikan sumbernya; sumber ditampilkan', 'Tidak ada pengiriman otomatis atau massal: manusia yang menekan kirim di WhatsApp', 'Pesan menyebut pengirim dan memberi jalan keluar “balas STOP”'], MX + 4.4, 5.35, 7.5, 1.2, { size: 12.5, gap: 4 });
}

// ---------- 19 Pipeline ----------
{
  const s = slide('04 · Aplikasi', 'Pantau perjalanan tiap calon', { notes: 'Status pipeline pada screenshot diatur pada salinan database demo agar kolom terisi, bukan hasil sinkronisasi nyata.' });
  const h = img(s, '04-kanban', MX, 1.6, 8.0);
  caption(s, 'Baru → Dihubungi → Demo → Deal / Ditolak. Status pada data demo diatur manual untuk ilustrasi.', MX, 1.7 + h + 0.12, 8);
  const x = 9.0;
  sub(s, 'Seret kartu ke kolom baru', x, 1.6, 3.75);
  bullets(s, ['Aplikasi mencatat siapa yang memindahkan dan kapan', 'Tersedia tampilan tabel dan aksi massal', 'Alasan penolakan terstandar', 'Peringatan bila calon sudah dihubungi rekan', 'Calon “jangan hubungi lagi” tidak muncul di rekomendasi'], x, 2.15, 3.75, 4.3, { size: 13, gap: 9 });
}

// ---------- 20 Rute ----------
{
  const s = slide('04 · Aplikasi', 'Susun rute kunjungan', { notes: 'Rute: nearest-neighbor + 2-opt, tautan Google Maps multi-titik tanpa API key.' });
  const h = img(s, '07-rute', MX, 1.6, 7.3);
  caption(s, 'Perencana rute dengan urutan, jarak total, dan tombol Google Maps.', MX, 1.7 + h + 0.12, 7.3);
  [['Pilih calon', 'Centang 2 sampai 30 calon di peta atau di tabel.'], ['Susun rute', 'Aplikasi mengurutkan agar perjalanan paling pendek.'], ['Buka di Maps', 'Satu tombol membuka Google Maps, lalu ikuti petunjuk arah.']].forEach(([t, d], i) => {
    const y = 1.7 + i * 1.65;
    card(s, 8.3, y, 4.4, 1.45);
    dot(s, 8.55, y + 0.25, 0.5, C.hot, i + 1, { size: 16 });
    s.addText(t, { x: 9.25, y: y + 0.22, w: 3.3, h: 0.5, fontFace: FH, fontSize: 17, bold: true, color: C.navy, margin: 0, valign: 'middle' });
    s.addText(d, { x: 8.55, y: y + 0.82, w: 4.0, h: 0.6, fontFace: F, fontSize: 12, color: C.muted, margin: 0, valign: 'top' });
  });
}

// ---------- 21 Dasbor & laporan ----------
{
  const s = slide('04 · Aplikasi', 'Angka untuk manajemen: dasbor dan laporan', { notes: 'Dasbor: KPI, funnel, penetrasi provinsi. Laporan: tabel lengkap dengan unduh CSV/Excel. Estimasi Dampak & Biaya: asumsi bisa diedit.' });
  const h = img(s, '05-dashboard', MX, 1.6, 5.6);
  img(s, '07-laporan', 6.85, 1.6, 5.6);
  s.addText('Dasbor: KPI, funnel, penetrasi per provinsi, kesehatan pencarian malam (data demo).', { x: MX, y: 1.7 + h + 0.1, w: 5.6, h: 0.5, fontFace: F, fontSize: 12.5, color: C.navy, margin: 0, valign: 'top' });
  s.addText('Laporan: tabel lengkap, urut dan saring, unduh CSV atau Excel sesuai filter.', { x: 6.85, y: 1.7 + h + 0.1, w: 5.6, h: 0.5, fontFace: F, fontSize: 12.5, color: C.navy, margin: 0, valign: 'top' });
  note(s, 'Halaman Estimasi Dampak & Biaya membandingkan Google Places berbayar dengan OpenStreetMap Rp0. Semua asumsi bisa diedit.', 6.0);
}

// ---------- 22 Arsitektur ----------
{
  const s = slide('05 · Arsitektur', 'Arsitektur sederhana, bisa jalan offline', { notes: 'Ringkasan ARCHITECTURE.md dan TRD. Detail teknis ada di docs/.' });
  const box = (x, y, w, h, t, d, col) => {
    card(s, x, y, w, h, { line: col });
    s.addText(t, { x: x + 0.2, y: y + 0.12, w: w - 0.4, h: 0.4, fontFace: FH, fontSize: 15, bold: true, color: col, margin: 0 });
    s.addText(d, { x: x + 0.2, y: y + 0.58, w: w - 0.4, h: h - 0.68, fontFace: F, fontSize: 12, color: C.muted, margin: 0, valign: 'top' });
  };
  box(MX, 1.8, 3.6, 2.0, 'Browser (Vue 3 + Inertia)', 'MapLibre GL dan deck.gl untuk peta 3D, Tailwind, TypeScript. Bahasa Indonesia dan English.', C.focus);
  box(MX + 4.25, 1.8, 3.6, 2.0, 'Laravel 12 (PHP 8.2)', 'Controller → Action → Service. Login, rate limit, CSP. Satu peran, banyak akun.', C.hot);
  box(MX + 8.5, 1.8, 3.6, 2.0, 'Database', 'SQLite bawaan, kompatibel MySQL dan PostgreSQL. Jarak dihitung dengan bounding box dan haversine.', C.warm);
  box(MX, 4.25, 3.6, 2.2, 'Keamanan dan kepatuhan', 'API key terenkripsi, CSRF, CSP aktif, tanpa secret di git, badge Simulasi, hanya place_id Google yang disimpan.', C.danger);
  box(MX + 4.25, 4.25, 3.6, 2.2, 'Pencarian malam 01.00 WIB', 'Per outlet: cari, hilangkan duplikat, deteksi baru buka dan tutup, ulangi bila gagal, catat riwayat.', C.ok);
  box(MX + 8.5, 4.25, 3.6, 2.2, 'Sumber data', 'OpenStreetMap Overpass (Rp0), MockProvider (simulasi, offline), Google Places (opsional, nonaktif bawaan), Nominatim untuk alamat.', C.cold);
  [[MX + 3.6, 2.6], [MX + 7.85, 2.6]].forEach(([x, y]) => s.addText('⇄', { x, y, w: 0.65, h: 0.5, fontFace: F, fontSize: 22, color: C.hot, align: 'center', margin: 0 }));
  s.addText('↓', { x: MX + 5.85, y: 3.8, w: 0.4, h: 0.45, fontFace: F, fontSize: 20, color: C.hot, align: 'center', margin: 0 });
  s.addText('⇄', { x: MX + 7.85, y: 5.05, w: 0.65, h: 0.5, fontFace: F, fontSize: 22, color: C.hot, align: 'center', margin: 0 });
}

// ---------- 23 Mutu ----------
{
  const s = slide('05 · Mutu', 'Hasil tes dan hal yang belum teruji', { notes: 'Angka dari TEST-REPORT.md (25 Okt 2026, dijalankan ulang di commit akhir sesi 4). Setelah itu ditambah fitur i18n/Laporan; jalankan ulang composer ci sebelum klaim angka terbaru.' });
  stat(s, MX, 1.7, 2.8, '455', 'tes backend (Pest)', C.focus);
  stat(s, MX + 3.1, 1.7, 2.8, '299', 'tes frontend (Vitest)', C.ok);
  stat(s, MX + 6.2, 1.7, 2.8, '46', 'tes E2E, aksesibilitas, keamanan', C.warm);
  stat(s, MX + 9.3, 1.7, 2.8, '0', 'secret terdeteksi di riwayat git', C.hot);
  card(s, MX, 3.2, 5.95, 3.45);
  sub(s, 'Sudah diverifikasi', MX + 0.25, 3.3, 5.4, C.ok);
  bullets(s, ['Peta memuat 14,6 ribu titik dalam ±3 detik', 'Aksesibilitas (axe) di 12 halaman: tanpa pelanggaran serius', 'Maksimal 7 query per request, tanpa N+1', 'Lint, format, dan build produksi hijau', 'Setup dari clone bersih mengikuti README'], MX + 0.25, 3.85, 5.45, 2.8, { size: 13, gap: 7 });
  card(s, MX + 6.15, 3.2, 5.95, 3.45, { line: C.warm });
  sub(s, 'Belum teruji (kami akui)', MX + 6.4, 3.3, 5.4, C.warm);
  bullets(s, ['Kecepatan peta di GPU nyata (diukur di rasterisasi CPU)', 'Pencarian nyata ke Overpass dan Google', 'MySQL dan PostgreSQL (baru SQLite)', 'Safari, Firefox, pembaca layar', 'Cakupan baris backend dan job CI GitHub Actions'], MX + 6.4, 3.85, 5.45, 2.8, { size: 13, gap: 7 });
}

// ---------- 24 Pilot ----------
{
  const s = slide('06 · Rencana adopsi', 'Pilot 8 minggu di 1–2 provinsi', { notes: 'Sumber: PRD §9(e).' });
  [['Minggu 0', 'Siapkan', 'Impor CSV outlet asli, cek geocoding, sepakati kebijakan retensi dengan legal.'], ['Minggu 1–6', 'Jalankan', 'Pencarian malam berjalan, sales memakai harian, metrik ditinjau tiap minggu.'], ['Minggu 7–8', 'Putuskan', 'Evaluasi terhadap metrik, lalu keputusan perluasan.']].forEach(([w, t, d], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.7, 3.9, 2.5, { line: C.hot });
    s.addText(w, { x: x + 0.3, y: 1.85, w: 3.3, h: 0.35, fontFace: F, fontSize: 12, bold: true, color: C.hot, charSpacing: 2, margin: 0 });
    s.addText(t, { x: x + 0.3, y: 2.2, w: 3.3, h: 0.55, fontFace: FH, fontSize: 24, bold: true, color: C.navy, margin: 0 });
    s.addText(d, { x: x + 0.3, y: 2.85, w: 3.3, h: 1.3, fontFace: F, fontSize: 13, color: C.muted, margin: 0, valign: 'top' });
    if (i < 2) s.addText('›', { x: x + 3.88, y: 2.6, w: 0.32, h: 0.5, fontFace: F, fontSize: 26, bold: true, color: C.hot, align: 'center', margin: 0 });
  });
  card(s, MX, 4.5, 5.95, 2.1);
  sub(s, 'Skala pilot', MX + 0.25, 4.6, 5.4);
  bullets(s, ['10–20 outlet, 4–6 akun manajemen', '1 server kecil (2 vCPU, 4 GB), HTTPS internal', 'Akses lewat VPN atau SSO kantor'], MX + 0.25, 5.1, 5.4, 1.5, { size: 13, gap: 6 });
  card(s, MX + 6.15, 4.5, 5.95, 2.1);
  sub(s, 'Data outlet', MX + 6.4, 4.6, 5.4);
  bullets(s, ['Tahap pilot: ekspor CSV dari Hitachi POS lalu impor', 'Tidak pernah mengakses database POS langsung', 'Hanya atribut minimum: nama, alamat, koordinat'], MX + 6.4, 5.1, 5.4, 1.5, { size: 13, gap: 6 });
}

// ---------- 25 Biaya & nilai ----------
{
  const s = slide('06 · Rencana adopsi', 'Biaya data dan nilai yang diukur', { notes: 'Estimasi Google memakai asumsi sync mingguan; angka pasti dihitung di kalkulator in-app dengan tarif yang diisi sendiri, bukan klaim harga resmi.' });
  card(s, MX, 1.7, 5.95, 4.9, { line: C.ok });
  sub(s, 'Biaya data', MX + 0.3, 1.85, 5.4, C.ok);
  s.addText('Rp0', { x: MX + 0.3, y: 2.35, w: 5.3, h: 1.0, fontFace: FH, fontSize: 54, bold: true, color: C.ok, margin: 0, valign: 'middle' });
  s.addText('dengan OpenStreetMap dan data simulasi', { x: MX + 0.3, y: 3.35, w: 5.3, h: 0.35, fontFace: F, fontSize: 13, color: C.muted, margin: 0 });
  bullets(s, ['Google Places opsional dan nonaktif bawaan', 'Bila dinyalakan penuh: ±4.900 panggilan pencarian dan ±950 detail per bulan (190 outlet, sync mingguan)', 'Hanya di kota pilot: biaya turun ±90%', 'Tarif diisi sendiri di kalkulator, bukan klaim harga resmi'], MX + 0.3, 3.95, 5.35, 2.6, { size: 12.5, gap: 7 });
  card(s, MX + 6.15, 1.7, 5.95, 4.9);
  sub(s, 'Nilai yang diukur', MX + 6.45, 1.85, 5.4);
  [['Calon per outlet', 'ditemukan, aktif, dan panas'], ['Jam riset sales', 'baseline survei vs sesudah pilot'], ['Konversi per tahap', 'Dihubungi → Demo → Deal'], ['Penetrasi provinsi', 'deal dibagi calon aktif'], ['Biaya per calon', 'bila Google dipakai']].forEach(([t, d], i) => {
    const y = 2.5 + i * 0.8;
    dot(s, MX + 6.45, y + 0.05, 0.4, C.focus, i + 1, { size: 13 });
    s.addText(t, { x: MX + 7.05, y, w: 4.8, h: 0.35, fontFace: F, fontSize: 14, bold: true, color: C.navy, margin: 0 });
    s.addText(d, { x: MX + 7.05, y: y + 0.33, w: 4.8, h: 0.3, fontFace: F, fontSize: 11.5, color: C.muted, margin: 0 });
  });
}

// ---------- 26 Keputusan ----------
{
  const s = slide('06 · Langkah berikutnya', 'Permintaan kami dan peta jalannya', { notes: 'Empat keputusan konkret untuk memulai pilot.' });
  card(s, MX, 1.7, 6.0, 4.95, { line: C.hot });
  sub(s, 'Keputusan yang diminta', MX + 0.3, 1.85, 5.5, C.hot);
  ['Setujui pilot 1–2 provinsi selama 6–8 minggu', 'Tunjuk PIC data untuk ekspor CSV outlet asli dari Hitachi POS', 'Jadwalkan tinjauan legal (UU PDP dan retensi data)', 'Tunjuk 2 sales sebagai pengguna awal dan sediakan server kecil'].forEach((t, i) => {
    const y = 2.55 + i * 1.0;
    dot(s, MX + 0.3, y, 0.55, C.hot, i + 1, { size: 16 });
    s.addText(t, { x: MX + 1.05, y: y - 0.1, w: 4.8, h: 0.75, fontFace: F, fontSize: 14, color: C.navy, margin: 0, valign: 'middle' });
  });
  card(s, MX + 6.2, 1.7, 5.9, 4.95);
  sub(s, 'Peta jalan', MX + 6.5, 1.85, 5.4);
  [['Pilot', 'Data outlet asli lewat CSV, ukur metrik'], ['Pasca-pilot', 'Endpoint sinkronisasi terautentikasi dari Hitachi POS'], ['Lanjutan', 'Peran berbeda, SSO, perluasan provinsi'], ['Opsional', 'Google Places terbatas di wilayah pilot untuk melengkapi rating dan telepon']].forEach(([t, d], i) => {
    const y = 2.55 + i * 1.0;
    s.addText(t, { x: MX + 6.5, y, w: 1.5, h: 0.75, fontFace: F, fontSize: 14, bold: true, color: C.warm, margin: 0, valign: 'top' });
    s.addText(d, { x: MX + 8.0, y, w: 3.95, h: 0.85, fontFace: F, fontSize: 13, color: C.navy, margin: 0, valign: 'top' });
  });
}

// ---------- 27 Tanya jawab ----------
{
  const s = slide('07 · Tanya jawab', 'Pertanyaan yang mungkin muncul', { notes: 'Jawaban bersumber dari PRD, TEST-REPORT, dan README.' });
  [['Apakah data di demo asli?', 'Tidak. Outlet adalah 190 data dummy dan rating/telepon berlabel “Simulasi”. Pilot memakai outlet asli lewat CSV dan data bisnis publik OSM.'],
    ['Apakah WhatsApp terkirim otomatis?', 'Tidak. Aplikasi hanya membuka WhatsApp dengan pesan terisi; manusia yang menekan kirim, dan kontak dicatat.'],
    ['Berapa biaya data?', 'Rp0 dengan OpenStreetMap. Google bersifat opsional, nonaktif bawaan, dan biayanya bisa dihitung di kalkulator.'],
    ['Apakah menyentuh database Hitachi POS?', 'Tidak. Hanya impor CSV berisi nama, alamat, koordinat. Tidak ada kode atau aset dari proyek Hitachi POS asli.'],
    ['Apa yang belum teruji?', 'Kecepatan di GPU nyata, pencarian nyata ke Overpass/Google, dan MySQL/PostgreSQL. Daftarnya ada di TEST-REPORT.'],
    ['Berapa lama sampai pilot?', 'Aplikasi sudah siap didemo. Tinggal data outlet asli dan tinjauan legal sebelum minggu 0.']].forEach(([q, a], i) => {
    const x = MX + (i % 2) * 6.1, y = 1.65 + Math.floor(i / 2) * 1.65;
    card(s, x, y, 5.9, 1.5);
    s.addText(q, { x: x + 0.25, y: y + 0.1, w: 5.4, h: 0.4, fontFace: FH, fontSize: 14.5, bold: true, color: C.hot, margin: 0, valign: 'middle' });
    s.addText(a, { x: x + 0.25, y: y + 0.55, w: 5.4, h: 0.9, fontFace: F, fontSize: 11.5, color: C.navy, margin: 0, valign: 'top' });
  });
}

// ---------- 28 Penutup ----------
{
  const s = slide('07 · Repo dan dokumentasi', 'Semua kode dan dokumen ada di GitHub', { notes: 'Repo public. Seluruh dokumen ada di folder docs/. Panduan pengguna tersedia sebagai deck terpisah.' });
  card(s, MX, 1.7, 12.1, 1.35, { line: C.hot, fill: C.navy });
  s.addText('REPOSITORI GITHUB', { x: MX + 0.3, y: 1.8, w: 5, h: 0.3, fontFace: F, fontSize: 11, bold: true, color: 'F2A27F', charSpacing: 3, margin: 0 });
  s.addText(REPO, { x: MX + 0.3, y: 2.15, w: 11.4, h: 0.55, fontFace: FH, fontSize: 26, bold: true, color: 'FFFFFF', margin: 0, hyperlink: { url: REPO, tooltip: 'Buka repo' } });
  s.addText('Setup dari nol: README.md · docs/RUN-GUIDE.md  |  tag rilis: v1.0.0', { x: MX + 0.3, y: 2.7, w: 11.4, h: 0.3, fontFace: F, fontSize: 12, color: 'D5DCEA', margin: 0 });
  s.addText('Seluruh dokumentasi ada di folder docs/', { x: MX, y: 3.3, w: 8, h: 0.4, fontFace: FH, fontSize: 17, bold: true, color: C.focus, margin: 0, hyperlink: { url: REPO + '/tree/main/docs' } });
  [['PRD.md · TRD.md', 'produk dan keputusan teknis'], ['ARCHITECTURE.md · DB-SCHEMA.md · CONTRACTS.md', 'arsitektur, skema, kontrak API'], ['IMPLEMENTATION-PLAN.md · GIT-WORKFLOW.md', 'rencana dan alur git'], ['TEST-PLAN.md · TEST-REPORT.md · qa-browser-notes.md', 'strategi dan hasil tes, catatan QA'],
    ['DECISIONS.md · REFACTORING.md', 'keputusan (D-xx) dan hasil refactoring'], ['AGENTS-LOG.md · pr/', 'log subagen dan catatan PR'], ['DESIGN.md · RUN-GUIDE.md · SLIDE-NOTES.md', 'desain, panduan jalan, bahan slide'], ['screenshots/', 'tangkapan layar aplikasi']].forEach(([a, b], i) => {
    const x = MX + (i % 2) * 6.1, y = 3.85 + Math.floor(i / 2) * 0.72;
    card(s, x, y, 5.9, 0.62);
    s.addText(a, { x: x + 0.15, y, w: 3.35, h: 0.62, fontFace: F, fontSize: 10.5, bold: true, color: C.warm, margin: 0, valign: 'middle' });
    s.addText(b, { x: x + 3.55, y, w: 2.3, h: 0.62, fontFace: F, fontSize: 10, color: C.muted, margin: 0, valign: 'middle' });
  });
  s.addText(`Terima kasih · ${NAME}`, { x: MX, y: 6.75, w: 8, h: 0.3, fontFace: FH, fontSize: 14, bold: true, color: C.navy, margin: 0 });
}

// ---------- tulis + transisi ----------
const buf = await pptx.write({ outputType: 'nodebuffer' });
const zip = await JSZip.loadAsync(buf);
for (const f of Object.keys(zip.files).filter((k) => /^ppt\/slides\/slide\d+\.xml$/.test(k))) {
  let x = await zip.file(f).async('string');
  if (x.includes('<p:transition')) continue;
  const tr = '<p:transition spd="med"><p:fade/></p:transition>';
  x = x.includes('</p:clrMapOvr>') ? x.replace('</p:clrMapOvr>', '</p:clrMapOvr>' + tr) : x.replace('</p:cSld>', '</p:cSld>' + tr);
  zip.file(f, x);
}
fs.writeFileSync(OUT, await zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' }));
console.log('ditulis', OUT, n, 'slide');
