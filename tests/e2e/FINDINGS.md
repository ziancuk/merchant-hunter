# E2E findings (QA-E2E)

Date: 2026-10-25. Environment: Playwright Chromium headless shell 156 with SwiftShader, `APP_ENV=e2e`, seed 42 (14.639 open prospects).

## Application findings (F-001 and F-002 FIXED by the orchestrator, e2e tests enabled; 42/42 green)

### F-001 Label in Name (WCAG 2.5.3, axe `label-content-name-mismatch`, serious by axe)
- Steps: log in, open `/pipeline` (or `/dashboard`) and run axe.
- Expected: the accessible name of a control contains its visible text.
- Actual: `aria-label` replaces the visible text on (a) the command palette button in the dock (`Buka palet perintah (⌘ K)`), (b) Kanban/table prospect cards (`Prospek <nama>, skor N` while category/city text is also visible), (c) cartogram tiles on the dashboard.
- Severity: Low to Medium (speech-control users cannot target these by visible text; no blocker).
- Suspected files: `resources/js/Layouts/AppShell.vue` (palette button), `resources/js/Components/prospect/ProspectCard.vue`, `resources/js/Components/charts/Cartogram.vue`.
- Status: FIXED (palette button now uses sr-only text, ProspectCard drops aria-label, Cartogram tile text carries the full label); `DISABLED_RULES` is empty; regression test `a11y.spec.ts` F-001.

### F-002 Invalid definition list on data-source cards (axe `definition-list`, serious)
- Steps: open `/master/data-sources` and run axe.
- Expected: `<dl>` contains only `dt`/`dd` groups.
- Actual: a `dd` carrying `role="progressbar"` (quota bar) inside the `<dl>` of `ds-row-google` (visible when a quota is set).
- Severity: Low.
- Suspected file: `resources/js/Components/master/DataSourceRow.vue` (line with `role="progressbar"`); move the bar outside the `dl` or wrap it in a valid `dd`.
- Status: FIXED (progressbar wrapped in a valid `dd`); regression test `a11y.spec.ts` F-002.

No other application bug was found. There are no `test.fixme` tests left.

## Observations (not bugs)
- Only `console.warn` from luma.gl was seen; no `console.error`/`pageerror` on map, pipeline, dashboard or master pages.
- Query counts per request (DB::listen, in-process; `app_queries` excludes the DB session and cache-store queries): map points 1, grid 1, outlets 1, whitespace 2, prospect detail 7, pipeline cards 7, search 3. Pages: `/` 17, `/pipeline` 21, `/dashboard` 20 (ceiling in the test: 30).
- The login limiter allows 5 failed attempts per minute and answers the 6th with 429 (matches CONTRACTS section 2).

## Performance (SwiftShader, 14.639 points; full data in `tests/e2e/perf-results.json`, git-ignored)

| Metric | Measured | Threshold |
|---|---|---|
| `/api/map/prospects` country bbox, zoom 6 | 14.639 points, 480 kB raw, 151 kB gzip, median 112-133 ms HTTP (warm) | <= 500 ms, <= 1 MB gzip |
| Time to map ready (`data-ready` and HUD count) | 1,7-4,4 s | < 30 s (guard) |
| Load of the 14.6k-point payload to drawn frame | about 330-390 ms (fetch 225-300 ms, parse 3 ms) | none |
| FPS pan / zoom with 14.6k points redrawn each frame | 3,4 / 3,1 (run 2); 4,2 / 3,8 (earlier run) | >= 2 (env `E2E_MIN_FPS`) |
| App queries per API request | 1 to 7 | <= 8 |

The plan's FPS >= 40 target is for real GPUs; on SwiftShader the measured 3-4 fps (jump on every frame, worst case) is recorded as is. The guard (>= 2) only catches a frozen renderer.

## Accessibility (axe, tags wcag2a/2aa/21a/21aa/best-practice, serious and critical block)
Pages scanned: login, map (also with drawer open), pipeline (kanban and table), dashboard, impact, master data-sources / outlets / categories / settings, scheduler. Result: no serious/critical violations except F-001 and F-002 above. Exceptions are listed once, with reasons, in `tests/e2e/support/axe.ts`; only the map `<canvas>` is excluded as a selector (no DOM semantics, its information is exposed through the HUD, live region and drawer, which are scanned).

## Not tested
- Real basemap tiles / OpenFreeMap (no internet; local style only), real Google/Overpass calls ("Uji koneksi" is not clicked).
- Outlet CSV import, review queue resolution, category editor, settings save, impact calculator interactions (only axe-scanned, covered by Pest/Vitest).
- `page.clock` / `APP_FAKE_NOW` time freezing: time-dependent values (new_7d, "x hari lalu") are compared relative to the DB at run time instead.
- Mobile viewports, Firefox/WebKit, dark/light theme contrast comparison (dark default only).
- CSV exports, AI message generation (`ai_enabled` is false).
