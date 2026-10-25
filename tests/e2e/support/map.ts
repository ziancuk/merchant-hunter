import { expect, type Page } from '@playwright/test';
import type { PickedProspect } from './fixtures';

/** Opens the map with intro/motion disabled (honoured because APP_ENV != production, D-17) and waits for it to be ready. */
export async function openMap(page: Page, query = ''): Promise<void> {
    const q = new URLSearchParams(query);
    if (!q.has('intro')) q.set('intro', '0');
    if (!q.has('motion')) q.set('motion', '0');
    await page.goto(`/?${q.toString()}`);
    await waitMapReady(page);
}

export async function waitMapReady(page: Page): Promise<void> {
    await expect(page.getByTestId('map-canvas')).toHaveAttribute('data-ready', 'true', { timeout: 60_000 });
    await expect(page.getByTestId('hud-count')).not.toHaveAttribute('data-count', '', { timeout: 60_000 });
}

/** Current HUD count as a number. */
export async function hudCount(page: Page): Promise<number> {
    const v = await page.getByTestId('hud-count').getAttribute('data-count');
    return Number(v);
}

/** Waits until the HUD count stops changing for `settleMs` (viewport loads are debounced). */
export async function settledHudCount(page: Page, settleMs = 1200): Promise<number> {
    let last = await hudCount(page);
    let stableSince = Date.now();
    const deadline = Date.now() + 30_000;
    while (Date.now() < deadline) {
        await page.waitForTimeout(150);
        const now = await hudCount(page);
        const busy = await page.getByTestId('map-hud').getAttribute('aria-busy');
        if (now !== last || busy === 'true') {
            last = now;
            stableSince = Date.now();
        } else if (Date.now() - stableSince >= settleMs) return now;
    }
    throw new Error('HUD count did not settle');
}

/** Screen position (page coordinates) of a lng/lat via MapLibre's own projection. */
export async function pixelOf(page: Page, lng: number, lat: number): Promise<{ x: number; y: number }> {
    return page.evaluate(
        ([lo, la]) => {
            const hook = (window as unknown as { __hunterMap?: { map: { project(l: [number, number]): { x: number; y: number }; getContainer(): HTMLElement } } }).__hunterMap;
            if (!hook?.map) throw new Error('window.__hunterMap not available');
            const pt = hook.map.project([lo, la]);
            const r = hook.map.getContainer().getBoundingClientRect();
            return { x: r.left + pt.x, y: r.top + pt.y };
        },
        [lng, lat] as [number, number],
    );
}

export async function flyTo(page: Page, lng: number, lat: number, zoom = 15): Promise<void> {
    await page.evaluate(([lo, la, z]) => (window as unknown as { __hunterMap: { flyTo(a: number, b: number, c: number): void } }).__hunterMap.flyTo(lo, la, z), [lng, lat, zoom] as [number, number, number]);
}

/** Centres the map on the prospect and clicks its dot until the drawer opens (deck.gl picking needs a rendered frame). */
export async function clickProspectOnMap(page: Page, p: Pick<PickedProspect, 'id' | 'lng' | 'lat'>): Promise<void> {
    await flyTo(page, p.lng, p.lat, 16);
    await expect(page.getByTestId('map-hud')).toHaveAttribute('aria-busy', 'false', { timeout: 30_000 });
    await expect
        .poll(
            async () => {
                const { x, y } = await pixelOf(page, p.lng, p.lat);
                await page.mouse.move(x - 3, y - 3);
                await page.mouse.move(x, y);
                await page.mouse.click(x, y);
                await page.waitForTimeout(300);
                // The URL mirrors the opened prospect (?prospect=ID), so a stale drawer of another prospect does not count.
                return new URL(page.url()).searchParams.get('prospect') === String(p.id) && (await page.getByTestId('drawer-content').isVisible());
            },
            { timeout: 45_000, intervals: [500, 1000, 1500] },
        )
        .toBe(true);
}
