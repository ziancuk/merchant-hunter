# E2E suite (Playwright)

Run everything with one command: `npm run e2e` (about 4 minutes). No internet is needed.

## How it works

- `playwright.config.ts` starts `scripts/e2e-serve.sh` as `webServer`. The script (via `scripts/e2e-prepare.sh`):
  creates `database/e2e.sqlite` (`migrate:fresh --seed`, then `hunter:demo-reset`, which runs
  `hunter:seed-prospects --offline --seed=42`), copies it to `database/e2e.base.sqlite` (pristine snapshot),
  runs `npm run build` (skip with `E2E_SKIP_BUILD=1`) and serves `php artisan serve` on `127.0.0.1:8001`
  with `APP_ENV=e2e HUNTER_BASEMAP=local DEMO_USER_PASSWORD=e2e-password-123`.
- `workers: 1`. Every spec file calls `freshData()` (support/fixtures.ts), which restores the snapshot in `beforeAll`, so
  files are independent and order-free. Tests inside a file pick their own prospects at run time through SQL
  (`pickContactable(offset)`), never hard-coded IDs.
- Simulated WhatsApp numbers (`0999-...`) are only linkable with setting `demo.allow_simulated_whatsapp`; the contact and
  dashboard specs enable it in `beforeAll` (D-62). `window.open` is stubbed, so `wa.me` is never opened.
- Browser: Playwright Chromium (`npx playwright install chromium`). If that download is blocked use the system Chrome:
  `E2E_CHROME=1 npm run e2e`.
- WebGL: `--use-gl=angle --use-angle=swiftshader --enable-unsafe-swiftshader --ignore-gpu-blocklist` (verified, renderer
  reported as "SwiftShader"). `?intro=0&motion=0` are honoured because `APP_ENV` is not production.
- Console policy: tests fail on `console.error` and `pageerror` only (see `IGNORED_CONSOLE_ERRORS` in support/fixtures.ts).
  luma.gl `console.warn` noise is ignored.

## Layout

| File | Scenarios (docs/TEST-PLAN.md) |
|---|---|
| `auth.spec.ts` | 1 login/logout, guests redirected |
| `map.spec.ts` | 2 intro, 3 map loads, 4 filters vs HUD, 5 click prospect, 11 route planner, 15 Simulasi badge |
| `contact.spec.ts` | 6 wa.me link, 7 contact logged, 8 double-contact warning, 9 opt-out |
| `pipeline.spec.ts` | 10 Kanban drag and drop (+ lost reason, table view) |
| `dashboard.spec.ts` | 12 KPI vs DB and HUD |
| `master.spec.ts` | 13 data sources, secret never leaks, 14 scheduler history |
| `a11y.spec.ts` | axe on every page; exceptions live only in `support/axe.ts` |
| `perf.spec.ts` | 10k+ points, writes `perf-results.json` (git-ignored) |
| `security.spec.ts` | login rate limit, 401/redirect, CSRF, XSS |

Open application bugs found by the suite are in `FINDINGS.md`; the matching tests are `test.fixme` with the finding ID.
