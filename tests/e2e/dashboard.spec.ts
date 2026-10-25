import { test, expect, api, enableSimulatedWhatsApp, freshData, loginAs, pickContactable } from './support/fixtures';
import { sql } from './support/env';
import { openMap, settledHudCount } from './support/map';

freshData();

/** Parses an id-ID formatted number ("9.421", "3,4%") back to a number. */
const parseId = (t: string) => Number(t.replace(/%/g, '').replace(/\./g, '').replace(',', '.').trim());
const one = (query: string) => Object.values(sql<Record<string, number>>(query)[0])[0] as number;

function expectedKpis() {
    const contacted = one('SELECT COUNT(contacted_at) FROM prospects');
    const won = one('SELECT COUNT(won_at) FROM prospects');
    return {
        outlets: one('SELECT COUNT(*) FROM outlets WHERE is_active = 1'),
        active: one("SELECT COUNT(*) FROM prospects WHERE lifecycle_status = 'open'"),
        // CONTRACTS §1: new_7d = first_seen_at >= now-7d AND is_baseline = false
        new7d: one("SELECT COUNT(*) FROM prospects WHERE is_baseline = 0 AND first_seen_at >= datetime('now', '-7 days')"),
        hot: one("SELECT COUNT(*) FROM prospects WHERE lifecycle_status = 'open' AND score >= 75"),
        conversionPct: contacted > 0 ? Math.round((won / contacted) * 10000) / 100 : 0,
        deals: one("SELECT COUNT(*) FROM prospects WHERE won_at >= date('now', 'start of month')"),
        funnel: {
            found: one('SELECT COUNT(*) FROM prospects'),
            contacted,
            demo: one('SELECT COUNT(demo_at) FROM prospects'),
            won,
            lost: one('SELECT COUNT(lost_at) FROM prospects'),
        },
    };
}

async function readKpi(page: import('@playwright/test').Page, id: string): Promise<number> {
    return parseId(await page.getByTestId(id).getByTestId('kpi-value').innerText());
}

test.describe('Dasbor', () => {
    test.beforeAll(() => {
        enableSimulatedWhatsApp(); // 12b contacts a prospect through the API (simulated 0999 numbers need the demo setting)
    });

    test.beforeEach(async ({ context, page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' }); // KPI counters animate otherwise
        await loginAs(context, 'ayu');
    });

    test('12. KPI dasbor konsisten dengan data seed (DB dan HUD peta)', async ({ page }) => {
        const exp = expectedKpis();
        expect(exp.active).toBeGreaterThan(10_000);
        await page.goto('/dashboard');
        await expect.poll(() => readKpi(page, 'kpi-prospects-active')).toBe(exp.active);
        expect(await readKpi(page, 'kpi-outlets')).toBe(exp.outlets);
        expect(await readKpi(page, 'kpi-new-7d')).toBe(exp.new7d);
        expect(await readKpi(page, 'kpi-conversion')).toBeCloseTo(exp.conversionPct, 1);
        expect(await readKpi(page, 'kpi-deals')).toBe(exp.deals);

        for (const key of ['found', 'contacted', 'demo', 'won'] as const) {
            await expect(page.getByTestId(`funnel-${key}`)).toContainText(new Intl.NumberFormat('id-ID').format(exp.funnel[key]));
        }
        await expect(page.getByTestId('funnel-lost')).toContainText(new Intl.NumberFormat('id-ID').format(exp.funnel.lost));
        await expect(page.getByTestId('scheduler-health')).toBeVisible();

        // Same numbers on the map HUD (a different code path: Inertia props on `/` vs `/dashboard`).
        await openMap(page);
        const hudActive = parseId(await page.getByTestId('hud-prospects').innerText());
        expect(hudActive).toBe(exp.active);
        expect(parseId(await page.getByTestId('hud-outlets').innerText())).toBe(exp.outlets);
        expect(parseId(await page.getByTestId('hud-new7d').innerText())).toBe(exp.new7d);
        expect(parseId(await page.getByTestId('hud-hot').innerText())).toBe(exp.hot);
        // …and the number of dots/cells the map actually shows at country zoom.
        expect(await settledHudCount(page)).toBe(exp.active);
    });

    test('12b. KPI dan corong ikut berubah setelah prospek dihubungi lalu deal', async ({ page, context }) => {
        const p = pickContactable(5);
        const before = expectedKpis();
        expect((await api(context, 'POST', `/api/prospects/${p.id}/contact`, { message: 'Halo dari uji E2E.' })).status).toBe(200);
        expect((await api(context, 'POST', `/api/prospects/${p.id}/status`, { status: 'won' })).status).toBe(200);
        const after = expectedKpis();
        expect(after.funnel.won).toBe(before.funnel.won + 1);
        expect(after.funnel.contacted).toBe(before.funnel.contacted + 1);
        expect(after.deals).toBeGreaterThan(before.deals);

        await page.goto('/dashboard');
        await expect.poll(() => readKpi(page, 'kpi-deals')).toBe(after.deals);
        expect(await readKpi(page, 'kpi-conversion')).toBeCloseTo(after.conversionPct, 1);
        await expect(page.getByTestId('funnel-won')).toContainText(new Intl.NumberFormat('id-ID').format(after.funnel.won));
        await expect(page.getByTestId('funnel-contacted')).toContainText(new Intl.NumberFormat('id-ID').format(after.funnel.contacted));
    });
});
