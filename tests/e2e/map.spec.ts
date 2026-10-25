import { test, expect, freshData, loginAs, nearbyProspects, pickContactable } from './support/fixtures';
import { sql } from './support/env';
import { clickProspectOnMap, hudCount, openMap, settledHudCount, waitMapReady } from './support/map';

freshData();

test.describe('Peta', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'ayu');
    });

    test('2. intro bisa dilewati dan preferensi diingat di localStorage', async ({ page }) => {
        await page.goto('/?motion=1');
        const canvas = page.getByTestId('map-canvas');
        await expect(page.getByTestId('map-intro')).toBeVisible({ timeout: 30_000 });
        await expect(canvas).toHaveAttribute('data-intro', 'true');

        await page.getByTestId('skip-intro').click();
        await expect(page.getByTestId('map-intro')).toBeHidden();
        await expect(canvas).toHaveAttribute('data-intro', 'false');
        const stored = await page.evaluate(() => Object.entries(localStorage).filter(([k]) => /intro/i.test(k)));
        expect(stored.length).toBeGreaterThan(0);
        expect(stored[0][1]).toBe('1');

        // Remembered: a fresh visit (no ?intro override) does not play the intro again.
        await page.goto('/?motion=1');
        await waitMapReady(page);
        await expect(page.getByTestId('map-intro')).toHaveCount(0);
        await expect(canvas).toHaveAttribute('data-intro', 'false');
    });

    test('3. peta termuat: canvas WebGL ada dan tanpa console.error', async ({ page, collected }) => {
        await openMap(page);
        const info = await page.evaluate(() => {
            const canvases = Array.from(document.querySelectorAll('[data-testid="map-canvas"] canvas')) as HTMLCanvasElement[];
            const gl2 = document.createElement('canvas').getContext('webgl2');
            return { canvases: canvases.length, sized: canvases.every((c) => c.width > 0 && c.height > 0), webgl2: !!gl2 };
        });
        expect(info.webgl2, 'SwiftShader WebGL2 must be available (D-17)').toBe(true);
        expect(info.canvases).toBeGreaterThan(0);
        expect(info.sized).toBe(true);
        await expect(page.getByTestId('map-error')).toHaveCount(0);
        await expect(page.getByTestId('map-load-error')).toHaveCount(0);
        // Let late async work (outlets layer, first tiles) surface errors before asserting.
        await page.waitForTimeout(1500);
        expect(collected.pageErrors).toEqual([]);
        expect(collected.errors).toEqual([]);
    });

    test('4. filter kategori dan provinsi mengubah jumlah titik di HUD', async ({ page }) => {
        await openMap(page);
        const total = await settledHudCount(page);
        const dbOpen = sql<{ n: number }>("SELECT COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open'")[0].n;
        expect(total, 'HUD at country zoom equals open prospects in DB').toBe(dbOpen);

        // Category filter keeps the camera, so the HUD must equal the DB count for that category.
        const cat = sql<{ id: number; n: number }>(
            "SELECT category_id AS id, COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open' GROUP BY category_id ORDER BY n ASC LIMIT 1",
        )[0];
        await page.getByTestId(`f-cat-${cat.id}`).click();
        await expect(page.getByTestId(`f-cat-${cat.id}`)).toHaveAttribute('aria-pressed', 'true');
        await expect.poll(() => hudCount(page), { timeout: 20_000 }).toBe(cat.n);
        expect(cat.n).toBeLessThan(total);

        // Province filter: pick the province with the fewest prospects; the count must shrink and be non-zero.
        await page.getByTestId('filter-reset').click();
        await expect.poll(() => hudCount(page), { timeout: 20_000 }).toBe(total);
        const prov = sql<{ id: number; n: number }>(
            "SELECT province_id AS id, COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open' AND province_id IS NOT NULL GROUP BY province_id ORDER BY n DESC LIMIT 1",
        )[0];
        await page.getByTestId('f-province').selectOption(String(prov.id));
        await expect.poll(async () => (await settledHudCount(page, 2000)) < total, { timeout: 40_000 }).toBe(true);
        const shown = await hudCount(page);
        expect(shown).toBeGreaterThan(0);
        expect(shown).toBeLessThanOrEqual(prov.n);
    });

    test('5. klik prospek di peta membuka drawer dengan data benar', async ({ page }) => {
        const p = pickContactable();
        await openMap(page);
        await clickProspectOnMap(page, p);
        const drawer = page.getByTestId('drawer-content');
        await expect(page.getByRole('dialog').or(page.locator('aside')).getByText(p.name, { exact: true }).first()).toBeVisible();
        await expect(drawer).toContainText(p.phone);
        const db = sql<{ score: number; address: string | null }>('SELECT score, address FROM prospects WHERE id = ?', [p.id])[0];
        await expect(drawer.getByText(String(db.score), { exact: true }).first()).toBeVisible();
        expect(new URL(page.url()).searchParams.get('prospect')).toBe(String(p.id));
    });

    test('15. badge Simulasi tampil pada data simulasi', async ({ page }) => {
        const p = pickContactable();
        await openMap(page, `prospect=${p.id}`);
        const drawer = page.getByTestId('drawer-content');
        await expect(drawer).toBeVisible();
        const badges = drawer.getByTestId('simulasi-badge');
        expect(await badges.count()).toBeGreaterThanOrEqual(3); // header + telepon + rating (+ ulasan)
        await expect(badges.first()).toContainText('Simulasi');
    });
});

test.describe('Perencana rute', () => {
    test('11. perencana rute menghasilkan urutan dan tautan Google Maps', async ({ page, context }) => {
        await loginAs(context, 'ayu');
        const base = pickContactable();
        const stops = nearbyProspects(base, 4);
        expect(stops).toHaveLength(4);

        await openMap(page);
        // Route state lives in the page (no reload between stops): pick each prospect by clicking its dot on the map.
        for (const s of stops) {
            await clickProspectOnMap(page, s);
            await expect(page.getByRole('heading', { name: s.name })).toBeVisible();
            await page.getByTestId('add-to-route').click();
            await expect(page.getByTestId('route-stop')).toHaveCount(stops.indexOf(s) + 1);
        }
        await expect(page.getByTestId('route-panel')).toBeVisible();
        await expect(page.getByTestId('route-stop')).toHaveCount(4);
        await expect(page.getByTestId('route-count')).toContainText('4/');

        const planResponse = page.waitForResponse((r) => r.url().endsWith('/api/routes/plan') && r.request().method() === 'POST');
        await page.getByTestId('route-plan').click();
        const plan = (await (await planResponse).json()) as { order: number[]; total_km: number; google_maps_urls: string[]; stops: { id: number }[] };

        expect([...plan.order].sort()).toEqual(stops.map((s) => s.id).sort());
        await expect(page.getByTestId('route-result')).toBeVisible();
        await expect(page.getByTestId('route-note')).toContainText('garis lurus');
        await expect(page.getByTestId('route-total')).toContainText('km');
        // The panel lists the stops in the planned order.
        const names = new Map(stops.map((s) => [s.id, s.name]));
        const listed = await page.getByTestId('route-stop').allInnerTexts();
        plan.order.forEach((id, i) => expect(listed[i]).toContain(names.get(id)!));

        const links = page.getByTestId('route-gmaps-link');
        expect(plan.google_maps_urls.length).toBeGreaterThan(0);
        await expect(links).toHaveCount(plan.google_maps_urls.length);
        const href = (await links.first().getAttribute('href'))!;
        const u = new URL(href);
        expect(u.origin + u.pathname).toBe('https://www.google.com/maps/dir/');
        expect(u.searchParams.get('api')).toBe('1');
        const first = stops.find((s) => s.id === plan.order[0])!;
        const [olat, olng] = u.searchParams.get('origin')!.split(',').map(Number);
        expect(olat).toBeCloseTo(first.lat, 3);
        expect(olng).toBeCloseTo(first.lng, 3);
        await expect(links.first()).toHaveAttribute('target', '_blank');
        await expect(links.first()).toHaveAttribute('rel', /noopener/);
    });
});
