import { test, expect, api, freshData, loginAs, pickContactable } from './support/fixtures';
import { sql, USERS } from './support/env';
import { flyTo, openMap, pixelOf } from './support/map';

freshData();

const XSS_NAME = '<img src=x onerror=alert(1)>';

test.describe('Keamanan', () => {
    test('rate limit login: percobaan ke-6 dalam semenit dijawab 429', async ({ browser }) => {
        const context = await browser.newContext();
        try {
            const request = context.request;
            await request.get('/login');
            const token = decodeURIComponent((await context.cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value);
            const attempt = () =>
                request.post('/login', { form: { email: USERS.budi.email, password: 'salah-salah-1' }, headers: { 'X-XSRF-TOKEN': token }, maxRedirects: 0 });
            const statuses: number[] = [];
            for (let i = 0; i < 6; i++) statuses.push((await attempt()).status());
            expect(statuses.slice(0, 5).every((s) => s !== 429)).toBe(true);
            expect(statuses[5]).toBe(429);
            // Even the right password is refused while throttled.
            const ok = await request.post('/login', { form: { email: USERS.budi.email, password: process.env.DEMO_USER_PASSWORD ?? 'e2e-password-123' }, headers: { 'X-XSRF-TOKEN': token }, maxRedirects: 0 });
            expect(ok.status()).toBe(429);
        } finally {
            await context.close();
        }
    });

    test('API tanpa login: 401 untuk JSON, pengalihan ke login untuk navigasi', async ({ request }) => {
        const gets = ['/api/map/outlets', '/api/map/prospects?bbox=90,-12,142,7&zoom=5', '/api/search?q=ab', '/api/prospects/1', '/api/pipeline/cards', '/api/pipeline/table', '/api/settings', '/api/master/data-sources', '/api/scheduler/runs', '/api/digest'];
        for (const url of gets) {
            const res = await request.get(url, { headers: { Accept: 'application/json' }, maxRedirects: 0 });
            expect(res.status(), `GET ${url}`).toBe(401);
            expect(await res.text()).not.toMatch(/"(name|phone|lat)"\s*:/);
        }
        for (const url of ['/api/prospects/1/contact', '/api/prospects/1/status', '/api/routes/plan', '/api/prospects/bulk']) {
            const res = await request.post(url, { headers: { Accept: 'application/json' }, data: {}, maxRedirects: 0 });
            expect([401, 419], `POST ${url}`).toContain(res.status());
        }
        for (const url of ['/api/prospects/1', '/dashboard', '/prospects/export.csv', '/dashboard/export.csv']) {
            const res = await request.get(url, { maxRedirects: 0 });
            expect(res.status(), `GET ${url}`).toBe(302);
            expect(res.headers().location).toMatch(/\/login$/);
        }
    });

    test('POST tanpa token CSRF ditolak (419) meski sudah login', async ({ context }) => {
        await loginAs(context, 'ayu');
        const res = await context.request.post('/api/prospects/1/opt-out', { headers: { Accept: 'application/json' }, data: {}, maxRedirects: 0 });
        expect(res.status()).toBe(419);
    });

    test('XSS: nama bisnis berisi <img onerror> tidak dieksekusi dan ter-escape di drawer, Kanban, tabel, dan tooltip', async ({ page, context, collected }) => {
        await loginAs(context, 'ayu');
        const p = pickContactable(0);
        sql('UPDATE prospects SET name = ? WHERE id = ?', [XSS_NAME, p.id]);
        // The API returns it verbatim as JSON data (escaping is the renderer's job).
        const detail = await api<{ name: string }>(context, 'GET', `/api/prospects/${p.id}`);
        expect(detail.json.name).toBe(XSS_NAME);

        // Any alert() would show up as a dialog (collected.dialogs) and a stray <img> would exist in the DOM.
        const noInjectedImg = async () => expect(await page.locator('img[src="x"]').count()).toBe(0);

        // Drawer
        await openMap(page, `prospect=${p.id}`);
        await expect(page.getByRole('heading', { name: XSS_NAME })).toBeVisible();
        await noInjectedImg();

        // Map tooltip (hover over the dot)
        await page.getByRole('button', { name: /Tutup/ }).first().click().catch(() => undefined);
        await flyTo(page, p.lng, p.lat, 16);
        await expect(page.getByTestId('map-hud')).toHaveAttribute('aria-busy', 'false', { timeout: 30_000 });
        await expect
            .poll(
                async () => {
                    const { x, y } = await pixelOf(page, p.lng, p.lat);
                    await page.mouse.move(x - 4, y - 4);
                    await page.mouse.move(x, y);
                    await page.waitForTimeout(400);
                    return page.getByTestId('map-tooltip').isVisible();
                },
                { timeout: 45_000, intervals: [500, 1000] },
            )
            .toBe(true);
        await expect(page.getByTestId('map-tooltip')).toContainText(XSS_NAME, { timeout: 15_000 });
        await noInjectedImg();

        // Kanban card and table row
        await page.goto('/pipeline');
        await expect(page.getByTestId(`kanban-card-${p.id}`)).toContainText(XSS_NAME);
        await noInjectedImg();
        await page.getByTestId('view-toggle-table').click();
        await expect(page.getByTestId('pipeline-table')).toBeVisible();
        await noInjectedImg();

        await page.waitForTimeout(500);
        expect(collected.dialogs, 'no alert()/confirm()/prompt() may fire').toEqual([]);
        expect(collected.pageErrors).toEqual([]);
    });
});
