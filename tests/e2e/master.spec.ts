import type { Response } from '@playwright/test';
import { test, expect, freshData, loginAs } from './support/fixtures';
import { sql } from './support/env';

freshData();

// Test value for this app only (never a real key). Distinct enough to search for in the DOM, API bodies and DB.
const SECRET = 'AIzaSyE2E-TEST-ONLY-KEY-9f3a7c1b';

test.describe('Master Data Source', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'ayu');
    });

    test('13. sumber data bisa diaktifkan/dinonaktifkan dan Google butuh key', async ({ page }) => {
        await page.goto('/master/data-sources');
        await expect(page.getByTestId('ds-list')).toBeVisible();
        const isActive = (key: string) => sql<{ is_active: number }>('SELECT is_active FROM data_sources WHERE key = ?', [key])[0].is_active;

        // OSM starts active: toggle off, then on.
        await expect(page.getByTestId('ds-state-osm')).toHaveText('Aktif');
        await page.getByTestId('ds-toggle-osm').click();
        await expect(page.getByTestId('ds-state-osm')).toHaveText('Nonaktif');
        await expect.poll(() => isActive('osm')).toBe(0);
        await page.getByTestId('ds-toggle-osm').click();
        await expect(page.getByTestId('ds-state-osm')).toHaveText('Aktif');
        await expect.poll(() => isActive('osm')).toBe(1);

        // Google cannot be enabled without a key (422): row stays inactive and shows the error.
        await expect(page.getByTestId('ds-state-google')).toHaveText('Nonaktif');
        await page.getByTestId('ds-toggle-google').click();
        await expect(page.getByTestId('ds-error-google')).toBeVisible();
        await expect(page.getByTestId('ds-state-google')).toHaveText('Nonaktif');
        expect(isActive('google')).toBe(0);
    });

    test('13b. menyimpan API key tidak membocorkan nilainya (DOM, respons API, DB)', async ({ page }) => {
        const bodies: string[] = [];
        page.on('response', (r: Response) => {
            if (r.url().includes('/api/master/data-sources')) void r.text().then((t) => bodies.push(t)).catch(() => undefined);
        });

        await page.goto('/master/data-sources');
        await expect(page.getByTestId('ds-keystate-google')).toContainText('Belum diisi');
        const input = page.getByTestId('ds-key-input-google').locator('input').or(page.getByTestId('ds-key-input-google'));
        await input.first().fill(SECRET);
        const saved = page.waitForResponse((r) => /\/api\/master\/data-sources\/\d+$/.test(r.url()) && r.request().method() === 'PUT');
        await page.getByTestId('ds-save-google').click();
        const res = await saved;
        expect(res.status()).toBe(200);
        const json = (await res.json()) as Record<string, unknown>;
        expect(JSON.stringify(json)).not.toContain(SECRET);
        expect(json.has_key).toBe(true);
        expect(String(json.key_hint)).toMatch(new RegExp(`${SECRET.slice(-4)}$`));
        expect(String(json.key_hint).length).toBeLessThan(SECRET.length / 2);

        await expect(page.getByTestId('ds-keystate-google')).toContainText('tersimpan');
        await expect(page.getByTestId('ds-keystate-google')).toContainText(SECRET.slice(-4));
        await expect(page.getByTestId('ds-keystate-google')).not.toContainText(SECRET);

        // The input is cleared and the page (incl. input values and attributes) never contains the key, also after a reload.
        for (const reload of [false, true]) {
            if (reload) await page.reload();
            await expect(page.getByTestId('ds-row-google')).toBeVisible();
            const html = await page.content();
            expect(html).not.toContain(SECRET);
            const values = await page.evaluate(() => Array.from(document.querySelectorAll('input,textarea')).map((e) => (e as HTMLInputElement).value));
            expect(values.join('|')).not.toContain(SECRET);
            const inertia = await page.evaluate(() => document.getElementById('app')?.getAttribute('data-page') ?? '');
            expect(inertia).not.toContain(SECRET);
        }
        expect(bodies.join('\n')).not.toContain(SECRET);

        // The listing endpoint never returns the key either.
        const list = await page.request.get('/api/master/data-sources', { headers: { Accept: 'application/json' } });
        expect(await list.text()).not.toContain(SECRET);

        // At rest the key is encrypted (D: column is not plaintext).
        const stored = sql<{ api_key: string | null }>("SELECT api_key FROM data_sources WHERE key = 'google'")[0].api_key;
        expect(stored).toBeTruthy();
        expect(stored).not.toContain(SECRET);

        // With a key, Google can now be enabled.
        await page.getByTestId('ds-toggle-google').click();
        await expect(page.getByTestId('ds-state-google')).toHaveText('Aktif');
        expect(sql<{ is_active: number }>("SELECT is_active FROM data_sources WHERE key = 'google'")[0].is_active).toBe(1);
    });

    test('14. riwayat scheduler tampil', async ({ page }) => {
        const runs = sql<{ id: number; command: string; status: string; prospects_new: number }>('SELECT id, command, status, prospects_new FROM scheduler_runs ORDER BY id DESC');
        expect(runs.length).toBeGreaterThan(0);
        await page.goto('/scheduler');
        await expect(page.getByTestId('scheduler-table')).toBeVisible();
        const row = page.getByTestId(`scheduler-row-${runs[0].id}`);
        await expect(row).toBeVisible();
        await expect(row).toContainText(runs[0].command);
        await expect(row).toContainText(runs[0].status);
        await expect(row).toContainText(new Intl.NumberFormat('id-ID').format(runs[0].prospects_new));
        await expect(page.getByTestId('scheduler-health')).toBeVisible();

        await page.getByTestId(`scheduler-detail-${runs[0].id}`).click();
        await expect(page.getByTestId('scheduler-drawer')).toBeVisible();
        await expect(page.getByTestId('scheduler-outlets')).toBeVisible();
    });
});
