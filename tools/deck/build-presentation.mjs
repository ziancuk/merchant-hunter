// Deck presentasi manajemen: POS Merchant Hunter (23 slide, ringkas, huruf besar, animasi fade-in).
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
const TOTAL = 23;

const C = { bg: 'F6F3EE', surface: 'FFFFFF', raised: 'EFEAE1', line: 'DAD3C6', text: '14213D', muted: '4F5A70',
  hot: 'D9481C', warm: 'A66A0A', ok: '0F8B7D', focus: '0F7A6E', cold: '5B6B82', navy: '14213D' };
const F = 'Arial', FH = 'Georgia';
const W = 13.333, MX = 0.6;
// pptxgenjs mutates shadow objects in place, so every shape needs a fresh one.
const SH = () => ({ type: 'outer', blur: 8, offset: 2, angle: 90, color: '1B2A4A', opacity: 0.10 });

const dim = { '01-peta': [1600, 1000], '03-drawer': [1600, 1000], '04-kanban': [1600, 1000], '05-dashboard': [1600, 1000], '07-rute': [1600, 1000], '07-laporan': [1600, 1000],
  'ev2-prompt': [1500, 1340], 'ev2-prd': [1500, 900], 'ev2-agents': [1500, 1026], 'ev2-git': [1500, 1176] };

const pptx = new PptxGenJS();
pptx.layout = 'LAYOUT_WIDE';
pptx.title = 'POS Merchant Hunter - Presentasi Manajemen';
pptx.author = NAME;
let n = 0;
const animFrom = {}; // nomor slide -> indeks objek pertama yang dianimasikan

function slide(label, title, { notes = '', bare = false } = {}) {
  const s = pptx.addSlide();
  n += 1;
  s.background = { color: C.bg };
  if (!bare) {
    s.addShape('ellipse', { x: 10.9, y: -1.9, w: 4.6, h: 4.6, fill: { type: 'none' }, line: { color: C.line, width: 0.75 } });
    s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 7.5, fill: { color: C.navy }, line: { color: C.navy, width: 0 } });
    s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 1.5, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
    s.addText(label.toUpperCase(), { x: MX, y: 0.38, w: 9, h: 0.3, fontFace: F, fontSize: 13, color: C.hot, bold: true, charSpacing: 3, margin: 0 });
    s.addText(title, { x: MX, y: 0.7, w: 11.6, h: 0.85, fontFace: FH, fontSize: 36, bold: true, color: C.navy, margin: 0, valign: 'top' });
    s.addText('POS Merchant Hunter', { x: MX, y: 7.08, w: 4, h: 0.28, fontFace: F, fontSize: 11, color: C.muted, margin: 0 });
    s.addText(`${n} / ${TOTAL}`, { x: W - MX - 1.5, y: 7.08, w: 1.5, h: 0.28, fontFace: F, fontSize: 11, color: C.muted, align: 'right', margin: 0 });
  }
  animFrom[n] = s._slideObjects.length;
  if (notes) s.addNotes(notes);
  return s;
}

const card = (s, x, y, w, h, o = {}) => s.addShape('roundRect', { x, y, w, h, rectRadius: 0.08, fill: { color: o.fill || C.surface }, line: { color: o.line || C.line, width: 0.75 }, shadow: SH() });
const T = (s, text, x, y, w, h, size, o = {}) => s.addText(text, { x, y, w, h, fontFace: o.head ? FH : F, fontSize: size, bold: !!o.bold, italic: !!o.italic, color: o.color || C.text, align: o.align || 'left', valign: o.valign || 'top', margin: 0, ...(o.extra || {}) });
function dot(s, x, y, d, col, txt, size = 22) {
  s.addShape('ellipse', { x, y, w: d, h: d, fill: { color: col }, line: { color: col, width: 0 } });
  if (txt !== undefined) s.addText(String(txt), { x, y, w: d, h: d, fontFace: FH, fontSize: size, bold: true, color: 'FFFFFF', align: 'center', valign: 'middle', margin: 0 });
}
function img(s, name, x, y, w) {
  const [pw, ph] = dim[name];
  const h = w * ph / pw;
  s.addShape('roundRect', { x: x - 0.06, y: y - 0.06, w: w + 0.12, h: h + 0.12, rectRadius: 0.06, fill: { color: C.surface }, line: { color: C.line, width: 1 }, shadow: SH() });
  s.addImage({ path: path.join(JPG, name + '.jpg'), x, y, w, h });
  return h;
}
const caption = (s, text, x, y, w) => T(s, text, x, y, w, 0.4, 14, { italic: true, color: C.muted });
function bullets(s, items, x, y, w, h, size = 20, gap = 12) {
  s.addText(items.map((t) => ({ text: t, options: { bullet: { indent: 18 }, breakLine: true, paraSpaceAfter: gap } })), { x, y, w, h, fontFace: F, fontSize: size, color: C.text, margin: 0, valign: 'top' });
}
// kartu langkah bernomor (gaya panduan): angka, judul, satu kalimat
function steps(s, items, y, h, col = C.hot) {
  const gap = 0.3, cols = items.length, w = (12.1 - gap * (cols - 1)) / cols;
  items.forEach(([t, d], i) => {
    const x = MX + i * (w + gap);
    card(s, x, y, w, h);
    dot(s, x + 0.3, y + 0.3, 0.75, col, i + 1, 24);
    T(s, t, x + 0.3, y + 1.3, w - 0.6, 0.5, 26, { head: true, bold: true, color: C.navy });
    T(s, d, x + 0.3, y + 1.95, w - 0.6, h - 2.1, 20, { color: C.muted });
  });
}

// ---------- 1 Sampul ----------
{
  const s = slide('', '', { bare: true, notes: 'Pembuka: "Di mana merchant Hitachi POS berikutnya? Hari ini jawabannya ada di tebakan; dengan POS Merchant Hunter, jawabannya ada di peta."' });
  s.background = { color: C.navy };
  s.addShape('ellipse', { x: 8.2, y: -2.2, w: 7.5, h: 7.5, fill: { type: 'none' }, line: { color: '2C3E66', width: 1 } });
  s.addShape('ellipse', { x: 9.4, y: -1.0, w: 5.1, h: 5.1, fill: { type: 'none' }, line: { color: '2C3E66', width: 1 } });
  s.addShape('rect', { x: 0, y: 0, w: 0.18, h: 7.5, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
  animFrom[1] = s._slideObjects.length;
  T(s, 'FINAL PROJECT CLAUDE CODE · HACKTIV8', 0.8, 0.7, 8, 0.3, 13, { bold: true, color: 'F2A27F', extra: { charSpacing: 3 } });
  s.addText([{ text: 'POS Merchant', options: { breakLine: true } }, { text: 'Hunter', options: { color: 'F2A27F' } }], { x: 0.8, y: 1.15, w: 6.0, h: 2.3, fontFace: FH, fontSize: 58, bold: true, color: 'FFFFFF', margin: 0, valign: 'top' });
  s.addShape('rect', { x: 0.8, y: 3.6, w: 0.9, h: 0.06, fill: { color: C.hot }, line: { color: C.hot, width: 0 } });
  T(s, 'Temukan calon merchant Hitachi POS berikutnya, langsung di peta.', 0.8, 3.85, 5.6, 1.2, 22, { color: 'E4E9F4' });
  T(s, NAME, 0.8, 5.9, 6, 0.4, 20, { bold: true, color: 'FFFFFF' });
  T(s, 'untuk PT Hitachi Channel Solutions Indonesia · Oktober 2026', 0.8, 6.4, 6.2, 0.4, 14, { color: 'B9C4DA' });
  s.addShape('roundRect', { x: 6.9, y: 1.3, w: 6.0, h: 3.95, rectRadius: 0.06, fill: { color: 'FFFFFF' }, line: { color: 'FFFFFF', width: 0 }, shadow: { type: 'outer', blur: 14, offset: 4, angle: 90, color: '000000', opacity: 0.35 } });
  s.addImage({ path: path.join(JPG, '01-peta.jpg'), x: 7.0, y: 1.4, w: 5.8, h: 3.625 });
  T(s, 'Aplikasi berjalan (data contoh)', 6.9, 5.3, 6, 0.4, 14, { italic: true, color: 'B9C4DA' });
}

// ---------- 2 Masalah ----------
{
  const s = slide('Masalah', 'Masalah yang sering terjadi', { notes: 'PRD §1: tidak ada pandangan spasial, pencarian tidak sistematis, tidak ada jejak kontak, tidak ada ukuran nilai.' });
  [['Cari sendiri', 'Tiap sales mencari dengan caranya sendiri.', C.hot], ['Tak ada prioritas', 'Tidak ada nilai untuk memilih siapa dulu.', C.warm], ['Kontak dobel', 'Rekan lain mungkin sudah menghubungi.', C.ok]].forEach(([t, d, col], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.8, 3.9, 3.0);
    s.addShape('rect', { x, y: 1.8, w: 3.9, h: 0.1, fill: { color: col }, line: { color: col, width: 0 } });
    dot(s, x + 0.3, 2.15, 0.7, col, i + 1, 22);
    T(s, t, x + 0.3, 3.05, 3.4, 0.5, 26, { head: true, bold: true, color: C.navy });
    T(s, d, x + 0.3, 3.7, 3.4, 1.0, 20, { color: C.muted });
  });
  card(s, MX, 5.15, 12.1, 1.5, { fill: C.navy, line: C.navy });
  T(s, '“Di mana merchant Hitachi POS berikutnya? Dengan POS Merchant Hunter, jawabannya ada di peta.”', MX + 0.4, 5.2, 11.3, 1.4, 24, { head: true, italic: true, color: 'FFFFFF', valign: 'middle' });
}

// ---------- 3 Solusi ----------
{
  const s = slide('Solusi', 'Solusinya: satu aplikasi', { notes: 'Temukan, Nilai, Hubungi. Semuanya tercatat dan bisa dilihat bersama oleh tim.' });
  T(s, 'Aplikasi yang menemukan, menilai, lalu membantu menghubungi calon merchant.', MX, 1.9, 4.6, 2.6, 28, { head: true, color: C.navy });
  T(s, 'Semuanya di satu tempat, terlihat bersama oleh tim.', MX, 4.9, 4.4, 1.2, 18, { color: C.muted });
  [['Temukan', 'Peta menunjukkan bisnis di sekitar outlet kita, paling jauh 2 km.'], ['Nilai', 'Setiap calon diberi skor 0 sampai 100 beserta alasannya.'], ['Hubungi', 'Satu klik membuka WhatsApp. Hasilnya dicatat otomatis.']].forEach(([t, d], i) => {
    const y = 1.75 + i * 1.7;
    card(s, 5.7, y, 7.0, 1.5);
    dot(s, 5.95, y + 0.4, 0.7, C.hot, i + 1, 22);
    T(s, t, 6.9, y + 0.15, 5.6, 0.5, 26, { head: true, bold: true, color: C.navy });
    T(s, d, 6.9, y + 0.7, 5.6, 0.8, 18, { color: C.muted });
  });
}

// ---------- 4 Sehari ----------
{
  const s = slide('Solusi', 'Satu hari bersama aplikasi', { notes: 'Pencarian malam berjalan 01.00 WIB lewat scheduler, jadi pengguna tidak menunggu API pihak ketiga saat membuka halaman.' });
  [['Malam', 'Sistem mencari bisnis baru, otomatis.', C.navy], ['Pagi', 'Tim melihat calon paling menarik.', C.ok], ['Siang', 'Hubungi lewat WhatsApp. Tercatat.', C.hot], ['Sore', 'Atasan melihat laporan hari itu.', C.warm]].forEach(([t, d, col], i) => {
    const x = MX + i * 3.08;
    card(s, x, 1.8, 2.9, 4.4);
    s.addShape('roundRect', { x, y: 1.8, w: 2.9, h: 1.3, rectRadius: 0.08, fill: { color: col }, line: { color: col, width: 0 } });
    T(s, t, x + 0.3, 1.8, 2.4, 1.3, 32, { head: true, bold: true, color: 'FFFFFF', valign: 'middle' });
    T(s, d, x + 0.3, 3.4, 2.4, 2.6, 22, { color: C.navy });
  });
}

// ---------- 5 Target ----------
{
  const s = slide('Ringkasan PRD', 'Target pilot 90 hari', { notes: 'PRD §2. Konversi 3% adalah asumsi awal yang bisa diubah di kalkulator. Semua angka dibaca dari Dasbor.' });
  [['≥ 25', 'Calon aktif per outlet', 'Tiap outlet punya minimal 25 calon unik yang layak dihubungi.', C.hot],
    ['−50%', 'Waktu riset sales', 'Waktu menyusun daftar kunjungan mingguan turun separuh dari kondisi awal.', C.focus],
    ['0', 'Kontak ganda', 'Tidak ada calon yang dihubungi dua orang tanpa peringatan di layar.', C.warm],
    ['≥ 3%', 'Calon jadi Deal', 'Dari calon yang dihubungi, minimal 3 dari 100 jadi deal (asumsi awal).', C.cold],
    ['Rp0', 'Biaya data', 'Memakai OpenStreetMap yang gratis. Google hanya opsional.', C.ok],
    ['≥ 80%', 'Akun aktif', 'Minimal 8 dari 10 akun terdaftar dipakai setiap minggu.', C.navy]].forEach(([b, t, d, col], i) => {
    const x = MX + (i % 3) * 4.1, y = 1.7 + Math.floor(i / 3) * 2.4;
    card(s, x, y, 3.9, 2.25);
    T(s, b, x + 0.3, y + 0.12, 3.3, 0.85, 44, { head: true, bold: true, color: col, valign: 'middle' });
    T(s, t, x + 0.3, y + 1.0, 3.3, 0.4, 20, { bold: true, color: C.navy });
    T(s, d, x + 0.3, y + 1.45, 3.35, 0.75, 15, { color: C.muted });
  });
  T(s, 'Diukur selama pilot 90 hari dan dibaca langsung dari Dasbor.', MX, 6.55, 12, 0.35, 16, { italic: true, color: C.muted });
}

// ---------- 6 Lingkup ----------
{
  const s = slide('Ringkasan PRD', 'Apa saja di versi 1', { notes: 'PRD §5 dan §8. Risiko: lisensi Google Places (hanya place_id disimpan permanen), batas layanan gratis, data OSM tipis, spam WhatsApp, UU PDP (tinjau legal sebelum pilot).' });
  [['Termasuk v1', C.ok, ['Peta 3D dan skor', 'WhatsApp dan pipeline', 'Rute kunjungan', 'Dasbor dan laporan', 'Pencarian otomatis malam']],
    ['Belum termasuk', C.cold, ['Terhubung ke database POS', 'Kirim WhatsApp otomatis', 'Banyak peran akses', 'Aplikasi mobile native']],
    ['Risiko dijaga', C.hot, ['Lisensi data Google', 'Batas layanan gratis', 'Spam WhatsApp', 'Privasi (UU PDP)']]].forEach(([t, col, b], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.8, 3.9, 4.8);
    s.addShape('rect', { x, y: 1.8, w: 3.9, h: 0.1, fill: { color: col }, line: { color: col, width: 0 } });
    T(s, t, x + 0.3, 2.1, 3.4, 0.5, 26, { head: true, bold: true, color: C.navy });
    bullets(s, b, x + 0.3, 2.9, 3.4, 3.6, 20, 12);
  });
}

// ---------- 7 Cara membangun ----------
{
  const s = slide('Cara membangun', 'Diorkestrasi Claude Code', { notes: 'Alur: PROMPT.md → dokumen (tag v0.1-docs) → review dokumen → build paralel di git worktree → QA dan keamanan → rilis v1.0.0. Stack: Laravel 12, Vue 3 + Inertia, MapLibre + deck.gl, SQLite.' });
  const st = [['Prompt', 'Satu spesifikasi lengkap'], ['Dokumen', 'PRD, TRD, skema, kontrak'], ['Review', 'Subagen cek sebelum kode'], ['Build', 'Agen paralel, lalu digabung'], ['QA dan rilis', 'Tes, audit, tag v1.0.0']];
  const w = 2.2, gap = 0.275;
  st.forEach(([t, d], i) => {
    const x = MX + i * (w + gap);
    card(s, x, 2.0, w, 3.4, { line: i === 0 ? C.hot : C.line });
    dot(s, x + 0.3, 2.3, 0.65, C.hot, i + 1, 20);
    T(s, t, x + 0.25, 3.2, w - 0.4, 0.8, 24, { head: true, bold: true, color: C.navy });
    T(s, d, x + 0.25, 4.0, w - 0.4, 1.3, 18, { color: C.muted });
    if (i < 4) T(s, '›', x + w, 3.4, gap, 0.6, 30, { bold: true, color: C.hot, align: 'center' });
  });
  T(s, 'Empat slide berikut: bukti asli dari repo.', MX, 5.9, 12, 0.4, 18, { italic: true, color: C.muted });
}

// ---------- 8 Bukti prompt ----------
{
  const s = slide('Bukti 1 dari 4', 'Prompt yang kami tulis', { notes: 'Cuplikan asli PROMPT.md (baris 1, 3, 5, 15, 18, 25). Bila ingin menambah tangkapan layar sesi Claude Code, tempel di slide ini.' });
  const h = img(s, 'ev2-prompt', MX, 1.75, 5.5);
  caption(s, 'Cuplikan asli PROMPT.md', MX, 1.75 + h + 0.15, 5.5);
  bullets(s, ['Satu spesifikasi lengkap', 'Aturan keras tertulis: data dummy, tanpa secret', 'Tiap sesi: “Baca PROMPT.md dan kerjakan Sesi N”'], 6.9, 2.1, 5.8, 4, 24, 20);
}

// ---------- 9 Bukti PRD ----------
{
  const s = slide('Bukti 2 dari 4', 'Dari prompt ke PRD', { notes: 'Cuplikan asli docs/PRD.md §2. 15 berkas di docs/, ±2.100 baris: PRD, TRD, DB-SCHEMA, ARCHITECTURE, CONTRACTS, IMPLEMENTATION-PLAN, TEST-PLAN, GIT-WORKFLOW, dan lainnya.' });
  const h = img(s, 'ev2-prd', MX, 1.75, 7.3);
  caption(s, 'Cuplikan asli docs/PRD.md', MX, 1.75 + h + 0.15, 7.3);
  T(s, '15 dokumen di docs/', 8.4, 1.75, 4.4, 0.5, 26, { head: true, bold: true, color: C.navy });
  bullets(s, ['PRD dan TRD', 'Skema DB dan kontrak API', 'Rencana dan strategi tes', 'Laporan tes dan keputusan'], 8.4, 2.5, 4.4, 3.6, 20, 12);
}

// ---------- 10 Bukti subagen ----------
{
  const s = slide('Bukti 3 dari 4', 'Subagen bekerja paralel', { notes: 'Cuplikan asli docs/AGENTS-LOG.md (Sesi 1). Riset dan review read-only; penulis kode maksimal 3 paralel, masing-masing di git worktree sendiri. Log lengkap 4 sesi ada di docs/AGENTS-LOG.md.' });
  const h = img(s, 'ev2-agents', MX, 1.75, 7.3);
  caption(s, 'Cuplikan asli docs/AGENTS-LOG.md', MX, 1.75 + h + 0.15, 7.3);
  [['4', 'sesi kerja'], ['20+', 'subagen tercatat']].forEach(([b, t], i) => {
    const y = 1.75 + i * 2.1;
    card(s, 8.4, y, 4.3, 1.9);
    T(s, b, 8.7, y + 0.15, 3.7, 1.0, 52, { head: true, bold: true, color: i ? C.hot : C.focus, valign: 'middle' });
    T(s, t, 8.7, y + 1.2, 3.7, 0.5, 20, { color: C.muted });
  });
}

// ---------- 11 Bukti git ----------
{
  const s = slide('Bukti 4 dari 4', 'Riwayat git dan rilis', { notes: 'Output asli git log --first-parent. 102 commit per 27 Okt 2026, conventional commits; cabang worktree-agent-* adalah agen paralel Claude Code.' });
  const h = img(s, 'ev2-git', MX, 1.75, 6.3);
  caption(s, 'Output asli git log', MX, 1.75 + h + 0.15, 6.3);
  [['v0.1', 'Dokumen'], ['v0.2', 'Backend'], ['v0.3', 'Aplikasi'], ['v1.0.0', 'Rilis']].forEach(([a, b], i) => {
    const y = 1.85 + i * 1.15;
    dot(s, 7.6, y + 0.1, 0.4, C.hot);
    if (i < 3) s.addShape('line', { x: 7.8, y: y + 0.55, w: 0, h: 0.7, line: { color: C.line, width: 2 } });
    T(s, `${a}  ${b}`, 8.3, y, 4.4, 0.6, 26, { head: true, bold: true, color: C.navy, valign: 'middle' });
  });
}

// ---------- 12 Review ----------
{
  const s = slide('Kualitas proses', 'AI juga mengaudit AI', { notes: 'Sumber: AGENTS-LOG.md, TEST-REPORT.md §6, qa-browser-notes.md. Review dokumen: 2 kritis, 11 mayor, 6 minor (mis. white space mustahil dihitung; nomor simulasi bisa di-WhatsApp). QA browser: 1 mayor, 14 minor. Audit keamanan: 0 kritis, 2 mayor, 8 minor; scan secret seluruh riwayat git 0 temuan. Refactoring: ekspor CSV 0,80 → 0,08 dtk; bundel awal 525 → 284 kB.' });
  [['19', 'masalah dokumen ditemukan sebelum kode', C.hot], ['0', 'bug kritis di QA browser', C.ok], ['10×', 'ekspor CSV jadi lebih cepat', C.focus]].forEach(([b, t, col], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.9, 3.9, 4.2);
    T(s, b, x + 0.3, 2.2, 3.3, 1.5, 72, { head: true, bold: true, color: col, valign: 'middle' });
    T(s, t, x + 0.3, 4.0, 3.3, 1.8, 22, { color: C.navy });
  });
}

// ---------- 13 Peta ----------
{
  const s = slide('Aplikasi', 'Peta: lihat semua calon', { notes: 'Demo: lewati intro, hover outlet untuk cincin 2 km, filter provinsi dan skor. Mode: Prospek, Heatmap, White Space, Cakupan. Ctrl/Cmd+K membuka pencarian cepat.' });
  const h = img(s, '01-peta', MX, 1.75, 7.6);
  caption(s, 'Data pada gambar adalah data contoh.', MX, 1.75 + h + 0.15, 7.6);
  [['FFFFFF', 'Putih: outlet kita', true], ['E8350E', 'Merah: Bidik Utama', false], ['F59E0B', 'Kuning: Menjanjikan', false], ['0E9F8E', 'Hijau: Berpotensi', false], ['6B7F91', 'Abu-abu: Pantau', false]].forEach(([col, t, ring], i) => {
    const y = 2.0 + i * 0.9;
    dot(s, 8.7, y, 0.5, col);
    if (ring) s.addShape('ellipse', { x: 8.85, y: y + 0.15, w: 0.2, h: 0.2, fill: { color: C.navy }, line: { color: C.navy, width: 0 } });
    T(s, t, 9.45, y, 3.3, 0.5, 20, { bold: true, color: C.navy, valign: 'middle' });
  });
}

// ---------- 14 Kartu ----------
{
  const s = slide('Aplikasi', 'Klik titik, lihat kartunya', { notes: 'Kartu prospek: skor, rincian enam faktor, kontak dan tombol WhatsApp. Data telepon dan rating dari MockProvider selalu berlabel Simulasi.' });
  const w = 7.4, x0 = MX, y0 = 1.75, h = img(s, '03-drawer', x0, y0, w);
  [[0.706, 0.2, 1], [0.764, 0.3, 2], [0.764, 0.88, 3]].forEach(([fx, fy, k]) => dot(s, x0 + fx * w - 0.25, y0 + fy * h - 0.25, 0.5, C.hot, k, 18));
  caption(s, 'Contoh kartu prospek (data simulasi)', MX, y0 + h + 0.15, w);
  [['Skor', 'Makin tinggi, makin layak.'], ['Alasan', 'Enam hal penentu skor.'], ['Kontak', 'Telepon, alamat, WhatsApp.']].forEach(([t, d], i) => {
    const y = 1.75 + i * 1.7;
    card(s, 8.5, y, 4.2, 1.5);
    dot(s, 8.75, y + 0.2, 0.6, C.hot, i + 1, 20);
    T(s, t, 9.55, y + 0.2, 3.0, 0.6, 26, { head: true, bold: true, color: C.navy, valign: 'middle' });
    T(s, d, 8.75, y + 0.9, 3.8, 0.5, 18, { color: C.muted });
  });
}

// ---------- 15 Skor ----------
{
  const s = slide('Aplikasi', 'Cara membaca skor', { notes: 'Skor berbasis aturan (bukan ML), 100 poin: kecocokan jenis usaha 25, rating dan ulasan 20, dekat outlet 15, area ramai 15, baru buka 15, kontak lengkap 10. Bobot kategori bisa diubah di Master Kategori.' });
  [['Bidik Utama', '75 – 100', 'Hubungi lebih dulu', 'E8350E'], ['Menjanjikan', '55 – 74', 'Layak dicoba', 'F59E0B'], ['Berpotensi', '35 – 54', 'Jika ada waktu', '0E9F8E'], ['Pantau', '0 – 34', 'Prioritas rendah', '6B7F91']].forEach(([t, r, d, col], i) => {
    const x = MX + i * 3.08;
    card(s, x, 1.8, 2.9, 3.3);
    s.addShape('rect', { x, y: 1.8, w: 2.9, h: 0.14, fill: { color: col }, line: { color: col, width: 0 } });
    T(s, t, x + 0.3, 2.2, 2.4, 0.5, 24, { head: true, bold: true, color: C.navy });
    T(s, r, x + 0.3, 2.85, 2.4, 0.9, 34, { head: true, bold: true, color: col, valign: 'middle' });
    T(s, d, x + 0.3, 4.0, 2.4, 0.9, 18, { color: C.muted });
  });
  card(s, MX, 5.4, 12.1, 1.25, { fill: C.raised, line: C.raised });
  T(s, 'Dihitung dari 6 hal: jenis usaha, rating, jarak ke outlet, keramaian, baru buka, kontak lengkap.', MX + 0.4, 5.4, 11.3, 1.25, 20, { color: C.navy, valign: 'middle' });
}

// ---------- 16 Sopan ----------
{
  const s = slide('Aplikasi', 'Menghubungi dengan sopan', { notes: 'PRD §9(c). Hanya tautan wa.me; manusia yang menekan kirim. Pesan menyebut pengirim dan memberi jalan keluar. Jeda 14 hari dan peringatan 7 hari bisa diatur admin. Hanya data bisnis yang dipublikasikan sumbernya.' });
  steps(s, [['Pesan terisi', 'Template per kategori, bisa diedit dulu.'], ['Cegah dobel', 'Muncul bila rekan sudah menghubungi.'], ['Opt-out', 'Jangan hubungi lagi: WhatsApp hilang.']], 1.8, 3.4);
  card(s, MX, 5.5, 12.1, 1.2, { fill: C.navy, line: C.navy });
  T(s, 'Jeda minimal 14 hari antar kontak. Tidak ada kirim otomatis.', MX + 0.4, 5.5, 11.3, 1.2, 26, { head: true, color: 'FFFFFF', valign: 'middle' });
}

// ---------- 17 Pipeline ----------
{
  const s = slide('Aplikasi', 'Pantau perjalanan tiap calon', { notes: 'Status pipeline pada screenshot diatur pada salinan database demo agar kolom terisi, bukan hasil sinkronisasi nyata. Tersedia tampilan tabel, aksi massal, dan alasan penolakan terstandar.' });
  const h = img(s, '04-kanban', MX, 1.75, 8.0);
  caption(s, 'Status pada data demo diatur manual untuk ilustrasi.', MX, 1.75 + h + 0.15, 8);
  bullets(s, ['Seret kartu ke kolom berikutnya', 'Siapa dan kapan tercatat', 'Peringatan kontak dobel'], 9.0, 2.0, 3.8, 4.3, 22, 22);
}

// ---------- 18 Rute ----------
{
  const s = slide('Aplikasi', 'Susun rute kunjungan', { notes: 'Rute: nearest-neighbor + 2-opt; tautan Google Maps multi-titik tanpa API key. Pilih 2 sampai 30 calon.' });
  const h = img(s, '07-rute', MX, 1.75, 6.9);
  caption(s, 'Urutan, jarak total, tombol Google Maps', MX, 1.75 + h + 0.15, 6.9);
  [['Pilih calon', 'Di peta atau di tabel.'], ['Susun rute', 'Urutan paling pendek.'], ['Buka di Maps', 'Satu tombol, lalu ikuti arah.']].forEach(([t, d], i) => {
    const y = 1.75 + i * 1.7;
    card(s, 7.9, y, 4.8, 1.5);
    dot(s, 8.15, y + 0.2, 0.6, C.hot, i + 1, 20);
    T(s, t, 8.95, y + 0.2, 3.6, 0.6, 26, { head: true, bold: true, color: C.navy, valign: 'middle' });
    T(s, d, 8.15, y + 0.9, 4.4, 0.5, 18, { color: C.muted });
  });
}

// ---------- 19 Dasbor & laporan ----------
{
  const s = slide('Aplikasi', 'Dasbor dan laporan', { notes: 'Dasbor: KPI, funnel, penetrasi provinsi, kesehatan pencarian malam. Laporan: tabel lengkap dengan unduh CSV/Excel sesuai filter. Halaman Estimasi Dampak & Biaya membandingkan Google Places berbayar dengan OpenStreetMap Rp0; semua asumsi bisa diedit.' });
  const h = img(s, '05-dashboard', MX, 1.75, 5.8);
  img(s, '07-laporan', 6.9, 1.75, 5.8);
  T(s, 'Dasbor: angka penting dalam satu layar', MX, 1.75 + h + 0.2, 5.8, 0.8, 20, { bold: true, color: C.navy });
  T(s, 'Laporan: unduh CSV atau Excel', 6.9, 1.75 + h + 0.2, 5.8, 0.8, 20, { bold: true, color: C.navy });
  caption(s, 'Data pada gambar adalah data contoh.', MX, 6.35, 8);
}

// ---------- 20 Hasil tes ----------
{
  const s = slide('Mutu', 'Hasil tes dan hal yang belum teruji', { notes: 'TEST-REPORT.md (25 Okt 2026, dijalankan ulang di commit akhir sesi 4). Setelah itu ditambah fitur i18n/Laporan; jalankan composer ci sebelum klaim angka terbaru. Sudah: 14,6 rb titik ±3 dtk, aksesibilitas axe 12 halaman, 0 secret. Belum: FPS GPU nyata, sync nyata Overpass/Google, MySQL/PostgreSQL, Safari/Firefox.' });
  [['455', 'tes backend', C.focus], ['299', 'tes frontend', C.ok], ['46', 'tes E2E', C.warm], ['0', 'secret bocor', C.hot]].forEach(([b, t, col], i) => {
    const x = MX + i * 3.08;
    card(s, x, 1.8, 2.9, 1.9);
    T(s, b, x + 0.25, 1.9, 2.4, 1.0, 46, { head: true, bold: true, color: col, valign: 'middle' });
    T(s, t, x + 0.25, 2.95, 2.4, 0.6, 18, { color: C.muted });
  });
  card(s, MX, 4.0, 5.95, 2.7);
  T(s, 'Sudah diverifikasi', MX + 0.3, 4.1, 5.4, 0.5, 22, { head: true, bold: true, color: C.ok });
  bullets(s, ['Peta 14,6 ribu titik dalam ±3 detik', 'Aksesibilitas di 12 halaman', 'Build produksi hijau'], MX + 0.3, 4.7, 5.4, 2.0, 18, 8);
  card(s, MX + 6.15, 4.0, 5.95, 2.7, { line: C.warm });
  T(s, 'Belum teruji', MX + 6.45, 4.1, 5.4, 0.5, 22, { head: true, bold: true, color: C.warm });
  bullets(s, ['Kecepatan di GPU nyata', 'Pencarian nyata ke Overpass/Google', 'MySQL dan PostgreSQL'], MX + 6.45, 4.7, 5.4, 2.0, 18, 8);
}

// ---------- 21 Pilot ----------
{
  const s = slide('Rencana adopsi', 'Pilot 8 minggu', { notes: 'PRD §9(e). 1–2 provinsi, 10–20 outlet, 4–6 akun manajemen, 1 server kecil (2 vCPU, 4 GB) dengan HTTPS internal, akses lewat VPN/SSO. Data outlet via impor CSV; tidak mengakses database Hitachi POS langsung.' });
  [['Minggu 0', 'Siapkan', 'Impor outlet asli, cek hasil geocoding.'], ['Minggu 1–6', 'Jalankan', 'Dipakai harian, metrik ditinjau tiap minggu.'], ['Minggu 7–8', 'Putuskan', 'Evaluasi, lalu keputusan perluasan.']].forEach(([w, t, d], i) => {
    const x = MX + i * 4.1;
    card(s, x, 1.8, 3.9, 3.4, { line: C.hot });
    T(s, w, x + 0.3, 2.0, 3.3, 0.4, 16, { bold: true, color: C.hot, extra: { charSpacing: 2 } });
    T(s, t, x + 0.3, 2.45, 3.3, 0.7, 34, { head: true, bold: true, color: C.navy });
    T(s, d, x + 0.3, 3.4, 3.3, 1.7, 20, { color: C.muted });
    if (i < 2) T(s, '›', x + 3.85, 3.1, 0.4, 0.6, 30, { bold: true, color: C.hot, align: 'center' });
  });
  ['10–20 outlet', '4–6 akun', '1 server kecil'].forEach((t, i) => {
    const x = MX + i * 4.1;
    card(s, x, 5.5, 3.9, 1.15, { fill: C.raised, line: C.raised });
    T(s, t, x, 5.5, 3.9, 1.15, 24, { head: true, bold: true, color: C.navy, align: 'center', valign: 'middle' });
  });
}

// ---------- 22 Biaya & permintaan ----------
{
  const s = slide('Rencana adopsi', 'Biaya dan permintaan kami', { notes: 'Biaya data Rp0 dengan OpenStreetMap. Google opsional dan nonaktif bawaan; bila dinyalakan penuh ±4.900 panggilan pencarian + ±950 detail per bulan untuk 190 outlet (sync mingguan), turun ±90% bila hanya di kota pilot. Tarif diisi sendiri di kalkulator, bukan klaim harga resmi. Roadmap: pasca-pilot endpoint sinkronisasi dari Hitachi POS, lalu peran berbeda dan SSO.' });
  card(s, MX, 1.8, 4.7, 4.8, { line: C.ok });
  T(s, 'Biaya data', MX + 0.35, 2.0, 4.0, 0.5, 24, { head: true, bold: true, color: C.navy });
  T(s, 'Rp0', MX + 0.35, 2.7, 4.0, 1.6, 80, { head: true, bold: true, color: C.ok, valign: 'middle' });
  T(s, 'dengan OpenStreetMap. Google bersifat opsional.', MX + 0.35, 4.6, 4.0, 1.5, 20, { color: C.muted });
  T(s, 'Yang kami minta', 5.7, 1.8, 7, 0.5, 26, { head: true, bold: true, color: C.hot });
  ['Setujui pilot 1–2 provinsi', 'Data outlet asli (CSV)', 'Tinjauan legal (UU PDP)', 'Dua sales pengguna awal'].forEach((t, i) => {
    const y = 2.5 + i * 1.05;
    card(s, 5.7, y, 7.0, 0.9);
    dot(s, 5.9, y + 0.15, 0.6, C.hot, i + 1, 20);
    T(s, t, 6.75, y, 5.8, 0.9, 22, { color: C.navy, valign: 'middle' });
  });
}

// ---------- 23 Penutup ----------
{
  const s = slide('Repo dan dokumentasi', 'Semua ada di GitHub', { notes: 'Repo public. Setup dari nol: README.md dan docs/RUN-GUIDE.md. Tag rilis v1.0.0. Panduan pengguna tersedia sebagai deck terpisah.' });
  card(s, MX, 1.8, 12.1, 1.5, { fill: C.navy, line: C.navy });
  T(s, 'REPOSITORI', MX + 0.4, 1.9, 5, 0.3, 13, { bold: true, color: 'F2A27F', extra: { charSpacing: 3 } });
  T(s, REPO, MX + 0.4, 2.3, 11.3, 0.8, 30, { head: true, bold: true, color: 'FFFFFF', extra: { hyperlink: { url: REPO, tooltip: 'Buka repo' } } });
  T(s, 'Dokumentasi ada di folder docs/', MX, 3.65, 12, 0.5, 26, { head: true, bold: true, color: C.focus, extra: { hyperlink: { url: REPO + '/tree/main/docs', tooltip: 'Buka docs/' } } });
  [['PRD.md · TRD.md', 'produk dan teknis'], ['ARCHITECTURE.md · DB-SCHEMA.md', 'arsitektur dan skema'], ['TEST-PLAN.md · TEST-REPORT.md', 'rencana dan hasil tes'], ['AGENTS-LOG.md · DECISIONS.md', 'log subagen, keputusan'], ['RUN-GUIDE.md · README.md', 'cara menjalankan'], ['screenshots/ · pr/', 'gambar dan catatan PR']].forEach(([a, b], i) => {
    const x = MX + (i % 2) * 6.1, y = 4.35 + Math.floor(i / 2) * 0.85;
    card(s, x, y, 5.9, 0.72);
    T(s, a, x + 0.2, y, 3.6, 0.72, 15, { bold: true, color: C.warm, valign: 'middle' });
    T(s, b, x + 3.75, y, 2.1, 0.72, 14, { color: C.muted, valign: 'middle' });
  });
  T(s, `Terima kasih · ${NAME}`, MX, 6.65, 8, 0.4, 18, { head: true, bold: true, color: C.navy });
}

// ---------- tulis + transisi + animasi ----------
const buf = await pptx.write({ outputType: 'nodebuffer' });
const zip = await JSZip.loadAsync(buf);

function timingXml(ids) {
  let id = 5;
  const step = Math.min(0.14, 1.8 / Math.max(1, ids.length));
  const eff = ids.map((spid, i) => {
    const a = id++, b = id++, c = id++;
    const nt = i === 0 ? 'afterEffect' : 'withEffect';
    const delay = Math.round(i * step * 1000);
    return `<p:par><p:cTn id="${a}" presetID="10" presetClass="entr" presetSubtype="0" fill="hold" nodeType="${nt}"><p:stCondLst><p:cond delay="${delay}"/></p:stCondLst><p:childTnLst>`
      + `<p:set><p:cBhvr><p:cTn id="${b}" dur="1" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst></p:cTn><p:tgtEl><p:spTgt spid="${spid}"/></p:tgtEl><p:attrNameLst><p:attrName>style.visibility</p:attrName></p:attrNameLst></p:cBhvr><p:to><p:strVal val="visible"/></p:to></p:set>`
      + `<p:animEffect transition="in" filter="fade"><p:cBhvr><p:cTn id="${c}" dur="450"/><p:tgtEl><p:spTgt spid="${spid}"/></p:tgtEl></p:cBhvr></p:animEffect></p:childTnLst></p:cTn></p:par>`;
  }).join('');
  return '<p:timing><p:tnLst><p:par><p:cTn id="1" dur="indefinite" restart="never" nodeType="tmRoot"><p:childTnLst>'
    + '<p:seq concurrent="1" nextAc="seek"><p:cTn id="2" dur="indefinite" nodeType="mainSeq"><p:childTnLst>'
    + '<p:par><p:cTn id="3" fill="hold"><p:stCondLst><p:cond delay="indefinite"/><p:cond evt="onBegin" delay="0"><p:tn val="2"/></p:cond></p:stCondLst><p:childTnLst>'
    + `<p:par><p:cTn id="4" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst><p:childTnLst>${eff}</p:childTnLst></p:cTn></p:par>`
    + '</p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn>'
    + '<p:prevCondLst><p:cond evt="onPrev" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:prevCondLst>'
    + '<p:nextCondLst><p:cond evt="onNext" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:nextCondLst></p:seq>'
    + '</p:childTnLst></p:cTn></p:par></p:tnLst></p:timing>';
}

for (const f of Object.keys(zip.files).filter((k) => /^ppt\/slides\/slide\d+\.xml$/.test(k))) {
  const num = Number(f.match(/slide(\d+)\.xml/)[1]);
  let x = await zip.file(f).async('string');
  // id objek berurutan (p:sp dan p:pic) sesuai urutan penulisan
  const ids = [...x.matchAll(/<p:(?:sp|pic)>[\s\S]*?<p:cNvPr id="(\d+)"/g)].map((m) => m[1]);
  const targets = ids.slice(animFrom[num] ?? ids.length);
  const extra = '<p:transition spd="med"><p:fade/></p:transition>' + (targets.length ? timingXml(targets) : '');
  x = x.includes('</p:clrMapOvr>') ? x.replace('</p:clrMapOvr>', '</p:clrMapOvr>' + extra) : x.replace('</p:cSld>', '</p:cSld>' + extra);
  zip.file(f, x);
}
fs.writeFileSync(OUT, await zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' }));
console.log('ditulis', OUT, n, 'slide');
