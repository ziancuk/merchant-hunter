# PR 02 — Frontend (peta, pipeline, dashboard/master) + suite E2E

## Ringkasan
Menggabungkan tiga cabang frontend paralel (`feat/fe-peta`, `feat/fe-pipeline`, `feat/fe-dashboard-master`), perbaikan integrasi, deep link peta, dan suite Playwright (`test/qa-e2e`).

## Jenis perubahan
- [x] Fitur  - [x] Perbaikan  - [x] Tes  - [x] Dokumen

## Cara uji
`composer ci` (Pest 399, Pint, ESLint, Vitest 274, build) dan `npm run e2e` (42 tes: 15 skenario TEST-PLAN, axe, performa, keamanan; ±4,3 menit).

## Catatan
- Spike peta: globe hanya untuk intro, lalu mercator (D-90).
- Normalizer WhatsApp menerima rentang demo `0999` hanya bila `demo.allow_simulated_whatsapp` aktif (D-96).
- F-001/F-002 (a11y) ditemukan QA-E2E dan diperbaiki; tes regresi aktif.
- FPS di SwiftShader 3–4 (ambang E2E ≥2); FPS GPU nyata belum terukur (D-94).

## Checklist
- [x] Test  - [x] Lint  - [x] Build  - [x] Dokumen  - [ ] Screenshot (Sesi 4)
