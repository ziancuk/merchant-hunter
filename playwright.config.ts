import { defineConfig, devices } from '@playwright/test';

/**
 * E2E configuration (docs/TEST-PLAN.md, D-17).
 *
 * Browser: Playwright's own headless Chromium (`npx playwright install chromium`) works on macOS and Linux CI. If the
 * download is blocked, set `E2E_CHROME=1` to use the system Google Chrome (`channel: 'chrome'`) instead.
 *
 * WebGL: headless Chromium has no GPU, so the SwiftShader software rasteriser is forced through ANGLE with
 * `--use-gl=angle --use-angle=swiftshader --enable-unsafe-swiftshader --ignore-gpu-blocklist`. Verified: the map's
 * deck.gl/MapLibre canvas renders and `canvas.getContext('webgl2')` succeeds with exactly these flags.
 *
 * Server: `scripts/e2e-serve.sh` (re)creates database/e2e.sqlite (migrate:fresh --seed, hunter:seed-prospects
 * --offline --seed=42, hunter:demo-reset), snapshots it to database/e2e.base.sqlite, builds the frontend and serves
 * on 127.0.0.1:8001 with APP_ENV=e2e and HUNTER_BASEMAP=local (no internet needed). Every spec file restores the
 * snapshot in `beforeAll` (tests/e2e/support/env.ts), so files are independent and order-free.
 */
const useSystemChrome = process.env.E2E_CHROME === '1';

export default defineConfig({
    testDir: 'tests/e2e',
    testMatch: /.*\.spec\.ts/,
    workers: 1,
    fullyParallel: false,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: { width: 1440, height: 900 },
        ...(useSystemChrome ? { channel: 'chrome' as const } : {}),
        launchOptions: {
            args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'],
        },
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } }],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command: 'bash scripts/e2e-serve.sh',
              url: 'http://127.0.0.1:8001/up',
              timeout: 240_000,
              reuseExistingServer: false,
              stdout: 'pipe',
              stderr: 'pipe',
          },
});
