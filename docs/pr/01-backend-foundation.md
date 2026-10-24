# PR 01 — Scaffold, backend, design system (Sesi 2)

## Ringkasan
Scaffold Laravel 12 + Inertia/Vue, skema beku, provider data + scheduler, domain (skor, pipeline, rute, dampak, master), dan sistem desain frontend.

## Jenis perubahan
- [x] feat  - [x] chore  - [x] docs  - [x] test

## Cara uji
`composer ci`; `php artisan migrate:fresh --seed && php artisan hunter:seed-prospects --offline` (≈14.7 ribu prospek, ≈9 dtk).

## Checklist
- [x] Test (398 Pest, 93 Vitest) - [x] Lint (Pint, ESLint) - [x] Build - [x] Dokumen - [ ] Screenshot (UI belum dirakit; Sesi 3)
