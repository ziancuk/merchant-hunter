# DESIGN — POS Merchant Hunter

Sistem desain frontend. Sumber kebenaran token: `resources/css/app.css`. Komponen: `resources/js/Components/ui`, `Components/prospect`, `Layouts/AppShell.vue`.

## 1. Konsep: "pusat komando radar"

Tim sales memandang satu layar peta penuh; semua UI **melayang di atas peta** (panel kaca, laci geser, HUD sudut). Tidak ada sidebar + header + grid kartu. Dark-first (nuansa layar radar/pemantau), tema terang tersedia untuk ruangan terang.

- **Satu aksen kuat**: oranye "flare" `--c-hot` hanya untuk prospek panas (dan hal yang menuntut perhatian: titik "baru buka" berdenyut, tombol primer di login). Elemen UI lain netral (tulang/ink) supaya titik panas langsung terlihat di peta.
- Latar bukan hitam/biru murni, melainkan **ink hijau-kelam** (`#070D0C`) dengan garis halus kehijauan, mengingatkan layar fosfor radar.
- Konsep visual berulang: cincin konsentris (login `.radar-bg`, cincin skor, logo), label HUD huruf kapital mono (`.hud-label`).

## 2. Tipografi (Google Fonts, dimuat di `app.blade.php`)

| Peran | Font | Alasan |
|---|---|---|
| Display (judul, nama prospek, brand) | **Bricolage Grotesque** 500–800 | Grotesk berkarakter dengan sedikit "kerut" optik; tidak generik seperti Inter/Space Grotesk, tetap terbaca pada ukuran kecil. |
| Teks (UI, paragraf) | **Instrument Sans** 400–700 | Netral, rapat, sangat terbaca di panel padat. |
| Angka & kode (skor, KPI, jarak, kbd) | **JetBrains Mono** 400–600 | Angka tabular (`font-variant-numeric: tabular-nums`) agar KPI tidak "bergoyang" saat count-up; kesan instrumen. |

Skala (Tailwind): `text-2xs` 11/16 (label HUD, badge) · `text-xs` 12 · `text-sm` 14 (default UI) · `text-base` 16 · `text-lg` 18 (judul panel) · `text-3xl` 30 (KPI) · judul login 30/1.15 bold. Kelas util: `.num` (mono tabular), `.hud-label` (mono 11px, kapital, tracking 0.12em, warna muted). Ukuran input minimal 16px pada ponsel (hindari zoom iOS) — field login memakai `text-base`.

Fallback font: `ui-sans-serif, system-ui` (tampilan tetap utuh jika Google Fonts diblokir; CSP ditunda per D-83, saat CSP aktif izinkan `fonts.googleapis.com` dan `fonts.gstatic.com`).

## 3. Token warna

Tema memakai class `dark` / `light` pada `<html>`; skrip kecil di `app.blade.php` membaca `localStorage['hunter.theme']` sebelum cat pertama (tanpa kedipan). Default: gelap. Tailwind: `bg-ink`, `bg-surface`, `bg-raised`, `text-fg`, `text-muted`, `border-line`, `text-hot`, dll. (via `@theme inline` → variabel `--c-*`).

Rasio kontras dihitung dengan rumus WCAG 2.x (luminans relatif) terhadap latar halaman (`bg`) dan permukaan kartu (`surface`). Syarat: teks ≥ 4,5:1.

### Gelap (bg `#070D0C`, surface `#0F1917`)

| Token | Hex | vs bg | vs surface |
|---|---|---|---|
| `--c-text` (fg) | `#E8F0EB` | 16,88 | 15,44 |
| `--c-muted` | `#9FB3AA` | 8,87 | 8,11 |
| `--c-focus` | `#7DE3C4` | 12,74 | 11,66 |
| `--c-hot` (aksen) | `#FF6A2B` | 6,86 | 6,27 |
| `--c-warm` | `#F2B63A` | 10,75 | 9,84 |
| `--c-medium` | `#4FC3A9` | 9,06 | 8,29 |
| `--c-cold` | `#8CA4B8` | 7,57 | 6,93 |
| `--c-danger` | `#FF6B6B` | 7,06 | 6,46 |
| status `new` | `#8FA3B0` | 7,49 | 6,86 |
| status `contacted` | `#4DB6E8` | 8,56 | 7,83 |
| status `demo` | `#E6A93B` | 9,43 | 8,62 |
| status `won` | `#5FD07A` | 10,07 | 9,21 |
| status `lost` | `#E0768A` | 6,65 | 6,08 |

Teks `--c-on-hot` `#1A0A02` di atas `--c-hot`: 6,75. Tombol primer: ink `#070D0C` di atas tulang `#E8F0EB`: 16,88.

### Terang (bg `#F3F1EA`, surface `#FFFFFF`)

| Token | Hex | vs bg | vs surface |
|---|---|---|---|
| `--c-text` | `#10201B` | 14,93 | 16,87 |
| `--c-muted` | `#4A5C55` | 6,29 | 7,11 |
| `--c-focus` | `#0A7A63` | 4,67 | 5,28 |
| `--c-hot` | `#C23B00` | 4,74 | 5,36 |
| `--c-warm` | `#8A5F00` | 5,00 | 5,65 |
| `--c-medium` | `#08705D` | 5,33 | 6,02 |
| `--c-cold` | `#4A6277` | 5,62 | 6,35 |
| `--c-danger` | `#B3261E` | 5,78 | 6,54 |
| status `new` | `#52667A` | 5,25 | 5,93 |
| status `contacted` | `#0B6E99` | 5,02 | 5,67 |
| status `demo` | `#8A5F00` | 5,00 | 5,65 |
| status `won` | `#1B7A38` | 4,78 | 5,40 |
| status `lost` | `#B0304B` | 5,51 | 6,23 |

`--c-on-hot` `#FFFFFF` di atas `#C23B00`: 5,36.

> Catatan: teks `muted`, badge, dan label tinted (`bg-hot/15` + `text-hot`) menjadi sedikit lebih terang/gelap daripada nilai di atas karena latar tint; rasio ke `surface` tetap dipakai sebagai batas bawah dan perlu diverifikasi visual + axe pada Sesi 3/4.

### Band skor (`lib/colors.ts`, ambang beku di CONTRACTS)

| Band | Skor | Warna | Peran |
|---|---|---|---|
| `hot` | ≥ 75 | `--c-hot` oranye flare | **Satu-satunya aksen kuat** |
| `warm` | 55–74 | `--c-warm` kuning amber | |
| `medium` | 35–54 | `--c-medium` hijau-tosca | |
| `cold` | < 35 | `--c-cold` abu-biru redup | |

Untuk kanvas/WebGL (deck.gl tidak membaca CSS variable) gunakan `BAND_HEX.dark|light` dan `STATUS_HEX.dark|light`. Warna band tidak pernah menjadi satu-satunya penanda: selalu ada angka skor + label ("Panas/Hangat/Sedang/Dingin") di `aria-label`.

### Status pipeline

`new` abu-biru netral · `contacted` biru langit · `demo` amber · `won` hijau · `lost` mawar. Label: Baru, Dihubungi, Demo, Menang, Kalah (`STATUS_LABEL`).

## 4. Spasi, radius, bayangan, blur

- Spasi: skala Tailwind 4 px (kelipatan 4); padding panel 16 (`p-4`), jarak antar elemen HUD 12 (`left-3/top-3`), jarak bagian di laci 24 (`space-y-6`).
- Radius: `rounded-panel` 18 px (panel kaca, kartu), `rounded-chip` 8 px (tombol/input), `rounded-full` (dock, badge, avatar).
- Bayangan: `--shadow-float` (panel melayang), `--shadow-pop`.
- Kaca: `.glass` = latar `--c-glass` (72–78% opak) + `backdrop-filter: blur(18px) saturate(140%)` + garis 1 px `--c-line`; fallback permukaan solid bila `backdrop-filter` tidak didukung.

## 5. Gerak

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--d-duration-fast` | 120 ms | tooltip, pop menu |
| `--d-duration-base` | 220 ms | laci geser, fade scrim |
| `--d-duration-slow` | 420 ms | (cadangan) |
| `--ease-out-expo` | `cubic-bezier(.16,1,.3,1)` | semua transisi masuk |

Gerak bermakna: laci prospek meluncur dari kanan (24 px + fade), cincin skor/bar rincian terisi, KPI count-up 900 ms ease-out-expo, titik "Baru buka" berdenyut (`animate-pulse-ring`, 1,8 s), sapuan radar di login (`animate-sweep`), skeleton shimmer, toast masuk.

**Aturan reduced-motion**: `@media (prefers-reduced-motion: reduce)` mematikan semua transisi/animasi (durasi ≈ 0, tanpa pengulangan, tanpa smooth scroll) di `app.css`; `KpiCounter` memakai `useReducedMotion()` dan langsung menampilkan nilai akhir tanpa `requestAnimationFrame`. Tidak ada informasi yang hanya disampaikan lewat gerak.

## 6. Lapisan z-index

| Lapisan | z | Isi |
|---|---|---|
| Peta / konten | 0 | `<main>` AppShell, kanvas peta |
| HUD | 20 | brand, dock navigasi, kontrol, slot sudut |
| Scrim laci | 30 | `Drawer` modal |
| Laci | 40 | `Drawer`, `ProspectDrawer` |
| Palet perintah | 60 | `CommandPalette` |
| Toast | 70 | `Toast` |
| Tooltip | 80 | `Tooltip` |
| Skip link | 90 | "Lewati ke konten utama" |

## 7. Katalog komponen

### `Components/ui`

| Komponen | Props | Event / slot |
|---|---|---|
| `GlassPanel` | `as`, `padded`, `rounded` (`panel`/`full`/`chip`) | slot default |
| `Button` | `variant` (`primary`/`secondary`/`ghost`/`hot`/`danger`), `size`, `type`, `loading`, `disabled`, `iconOnly` (wajib `aria-label`) | slot |
| `Badge` | `tone` (`neutral`/`hot`/`warm`/`medium`/`cold`/`info`/`success`/`danger`/`simulasi`), `dot`, `pulse`, `size` | slot |
| `Icon` | `name` (set inline SVG: map, kanban, chart, coins, database, clock, search, sun, moon, close, check, alert, info, phone, whatsapp, globe, star, pin, ban, logout, user, command, filter, chevron-*, sparkle, refresh, keyboard, store, navigation, note, send, flame), `size`, `label` | — |
| `Drawer` | `modelValue`, `title`, `description`, `side`, `widthClass`, `modal` | `update:modelValue`, `close`; slot `title`, default, `footer`. Fokus masuk/terjebak (jika modal), `Esc` menutup, fokus kembali ke pemicu |
| `CommandPalette` | `open`, `items: {id,label,hint?,group,action?}[]`, `search?(q)=>Promise<items>` (debounce), `debounceMs`, `hotkey` | `update:open`, `select`. ARIA combobox + listbox, ↑↓ Home End Enter Esc |
| `Skeleton` | `width`, `height`, `lines`, `circle` | — |
| `EmptyState` | `title`, `description`, `icon` | slot aksi |
| `ErrorState` | `title`, `message`, `retryLabel`, `retrying` | `retry` |
| `Tooltip` | `text`, `placement` | slot pemicu; tampil saat hover/fokus, `Esc` menyembunyikan |
| `KpiCounter` | `value`, `label`, `decimals`, `prefix`, `suffix`, `duration`, `format` | — (teks akhir untuk pembaca layar) |
| `Toast` + `useToasts()` | `items` | `dismiss`; `push(message, tone, ttl)` |
| `ShortcutHelp` | — | membaca registri `useShortcuts` |

### `Components/prospect`

| Komponen | Props | Event |
|---|---|---|
| `ScoreRing` | `score`, `band?`, `size` | — |
| `ScoreBreakdown` | `components: ScoreComponent[]`, `band?` | — |
| `SimulasiBadge` | `field?` | — (tooltip + fokus keyboard) |
| `ProspectBadges` | `badges: Badge[]` | — (Baru buka berdenyut, Simulasi, Tutup, Jangan hubungi) |
| `WhatsAppButton` | `phone`, `phoneSimulated`, `whatsappUrl`, `doNotContact`, `lifecycleStatus`, `label`, `intercept` | `click`. Nonaktif + alasan: opt-out, tutup, tanpa nomor, "Nomor simulasi", nomor tidak valid |
| `WhatsAppComposer` | `prospectId`, `aiEnabled?` (default dari `app.ai_enabled`), `autoCheck` | `contacted(prospect)`, `closed`. Memanggil `contact-check`; kirim = `window.open(wa.me)` **sinkron** lalu `fetch keepalive POST /contact` (D-65) |
| `ProspectCard` | `prospect: PipelineCard`, `selected` | `select(id)` |
| `ActivityTimeline` | `activities` | — |
| `ProspectDrawer` | `prospectId \| null`, `loader?`, `rejectionReasons`, `aiEnabled?` | `close`, `updated(prospect)`, `contacted(prospect)`, `locate({lat,lng})` |
| `FilterPanel` | `modelValue: ProspectFilter`, `options: FilterOptions`, `collapsible` | `update:modelValue`, `query(string)` |

### `Layouts/AppShell`

Props: `fullBleed` (peta), `commandItems`, `search`. Slot: default (konten), `top-left`, `top-right`, `bottom-left`, `bottom-right`. Berisi brand, dock navigasi (atas-tengah di tablet/desktop, bawah di ponsel; status aktif dari `page.url`, `aria-current="page"`), palet perintah, tombol tema, bantuan pintasan, menu pengguna (`router.post('/logout')`), dan toast dari `flash.success/error`.

### Composable & lib

`useTheme`, `useReducedMotion`, `useDebounceFn/useDebouncedRef`, `useShortcuts`, `useFilters` (+ fungsi murni `filterToParams`, `filterToQuery`, `queryToFilter`, `countActiveFilters`), `useToasts`; `lib/format.ts`, `colors.ts`, `api.ts` (`api`, `normalizeError`, `postKeepalive`), `contact.ts`, `nav.ts`.

## 8. Pintasan keyboard

| Pintasan | Aksi |
|---|---|
| `Ctrl/⌘ + K` | Buka/tutup palet perintah (bekerja juga saat fokus di input) |
| `/` | Buka palet perintah untuk mencari |
| `?` | Daftar pintasan (dibangun dari registri; halaman boleh menambah via `useShortcuts`) |
| `t` | Ganti tema |
| `Esc` | Tutup laci/palet/menu |
| `↑ ↓ Home End Enter` | Navigasi hasil di palet |
| `Tab` | Terjebak di dalam laci modal |

Pintasan tanpa modifier diabaikan saat fokus berada di input/textarea/select/contenteditable (WCAG 2.1.4).

## 9. Daftar periksa aksesibilitas

- [x] Kontras teks ≥ 4,5:1 di kedua tema (tabel §3, dihitung).
- [x] Fokus keyboard terlihat: outline 2 px `--c-focus` global untuk button/a/input/select/textarea.
- [x] Tombol ikon memiliki `aria-label` berbahasa Indonesia; ikon dekoratif `aria-hidden`.
- [x] Laci: `role="dialog"`, `aria-labelledby`, `aria-modal`, Esc, jebakan fokus, fokus kembali.
- [x] Palet: `role="combobox"` + `listbox`/`option`, `aria-activedescendant`, `aria-selected`.
- [x] Status tidak hanya warna: label teks, angka skor, `role="meter"` pada rincian, `aria-pressed` pada status.
- [x] State loading (`role="status"`), error (`role="alert"`), kosong yang dirancang.
- [x] Skip link, landmark `nav` ber-label, `main`.
- [x] `prefers-reduced-motion` dihormati (CSS + `KpiCounter`).
- [x] Tooltip dapat dicapai lewat fokus (badge Simulasi `tabindex=0`).
- [ ] Belum diverifikasi di browser nyata: tata letak responsif (tablet/ponsel), kontras pada panel kaca di atas peta, axe — menunggu Sesi 3/4.

## 10. Batasan yang diketahui

- Tampilan belum ditinjau visual (tanpa browser pada Sesi 2); tinjau di Sesi 3/4.
- `ProspectDrawer` memakai `Drawer` non-modal (peta tetap interaktif); fokus tidak dijebak. Laci lain (`ShortcutHelp`) modal.
- Inline `<script>` tema di `app.blade.php` perlu nonce/hash saat CSP diaktifkan (D-83).
