import type { Page } from '@playwright/test';
import { test, expect, api, enableSimulatedWhatsApp, expectedWaNumber, freshData, loginAs, pickContactable } from './support/fixtures';
import { sql, USERS } from './support/env';
import { openMap } from './support/map';

freshData();

/** Replaces window.open before any app script runs; calls are recorded in window.__opened (no real wa.me navigation, no internet). */
async function stubWindowOpen(page: Page): Promise<void> {
    await page.addInitScript(() => {
        const w = window as unknown as { __opened: { url: string; target?: string; features?: string }[] };
        w.__opened = [];
        window.open = ((url?: string | URL, target?: string, features?: string) => {
            w.__opened.push({ url: String(url), target, features });
            return null;
        }) as typeof window.open;
    });
}

const opened = (page: Page) => page.evaluate(() => (window as unknown as { __opened: { url: string; target?: string; features?: string }[] }).__opened);

test.describe('Kontak WhatsApp', () => {
    test.beforeAll(() => {
        enableSimulatedWhatsApp(); // D-62: simulated 0999-… numbers are only linkable in demo/E2E with this setting
    });

    test('6. tautan WhatsApp berisi nomor ternormalisasi dan pesan terisi', async ({ page, context }) => {
        await loginAs(context, 'ayu');
        await stubWindowOpen(page);
        const p = pickContactable(0);
        const wa = expectedWaNumber(p.phone);
        expect(wa).toMatch(/^62999\d{8}$/);

        await openMap(page, `prospect=${p.id}`);
        const button = page.getByTestId('wa-button');
        await expect(button).toBeVisible();
        await expect(button).toHaveAttribute('href', new RegExp(`^https://wa\\.me/${wa}`));
        await expect(page.getByTestId('wa-reason')).toContainText('Nomor simulasi');

        await button.click();
        const message = page.getByTestId('wa-message');
        await expect(message).toBeVisible();
        await expect(message).not.toHaveValue('');
        await expect(message).toHaveValue(new RegExp(p.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
        const text = await message.inputValue();

        await page.getByTestId('wa-send').click();
        await expect.poll(async () => (await opened(page)).length).toBe(1);
        const [call] = await opened(page);
        const url = new URL(call.url);
        expect(url.origin + url.pathname).toBe(`https://wa.me/${wa}`);
        expect(url.searchParams.get('text')).toBe(text);
        expect(call.features).toContain('noopener');
    });

    test('7. klik WhatsApp mencatat aktivitas dan mengubah status', async ({ page, context }) => {
        await loginAs(context, 'ayu');
        await stubWindowOpen(page);
        const p = pickContactable(1);
        await openMap(page, `prospect=${p.id}`);
        await expect(page.getByTestId('status-new')).toHaveAttribute('aria-pressed', 'true');

        await page.getByTestId('wa-button').click();
        await expect(page.getByTestId('wa-message')).not.toHaveValue('');
        await page.getByTestId('wa-send').click();
        await expect(page.getByTestId('wa-sent')).toBeVisible();
        await expect(page.getByTestId('status-contacted')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('drawer-content')).toContainText(USERS.ayu.name);

        const row = sql<{ pipeline_status: string; contact_count: number; handled_by: number; last_contacted_by: number }>(
            'SELECT pipeline_status, contact_count, handled_by, last_contacted_by FROM prospects WHERE id = ?',
            [p.id],
        )[0];
        const ayuId = sql<{ id: number }>('SELECT id FROM users WHERE email = ?', [USERS.ayu.email])[0].id;
        expect(row).toMatchObject({ pipeline_status: 'contacted', contact_count: 1, handled_by: ayuId, last_contacted_by: ayuId });

        const acts = sql<{ type: string; meta: string }>("SELECT type, meta FROM prospect_activities WHERE prospect_id = ? AND type IN ('contacted','status_change') ORDER BY id", [p.id]);
        expect(acts.map((a) => a.type)).toContain('contacted');
        const contacted = JSON.parse(acts.find((a) => a.type === 'contacted')!.meta) as Record<string, unknown>;
        expect(JSON.stringify(contacted)).toContain(expectedWaNumber(p.phone));
        await expect(page.getByTestId('activity').first()).toBeVisible();
    });

    test('8. peringatan dobel kontak: akun kedua melihat siapa dan kapan', async ({ context, browser }) => {
        const p = pickContactable(2);
        // Account A (Ayu) contacts through the real endpoint.
        await loginAs(context, 'ayu');
        const sent = await api(context, 'POST', `/api/prospects/${p.id}/contact`, { message: 'Halo, ini pesan pertama dari Ayu.' });
        expect(sent.status).toBe(200);

        // Account B (Budi) in an isolated browser context.
        const ctxB = await browser.newContext();
        try {
            await loginAs(ctxB, 'budi');
            const pageB = await ctxB.newPage();
            await stubWindowOpen(pageB);
            await openMap(pageB, `prospect=${p.id}`);
            await pageB.getByTestId('wa-button').click();
            const warning = pageB.getByTestId('wa-warning');
            await expect(warning).toBeVisible();
            await expect(warning).toContainText(USERS.ayu.name);
            await expect(warning).toContainText(/0 hari lalu/);
            // Minimum interval violated right after a contact: an override reason is required before sending.
            await expect(pageB.getByTestId('wa-override')).toBeVisible();
            await expect(pageB.getByTestId('wa-send')).toBeDisabled();
            await pageB.getByTestId('wa-override-reason').fill('Pelanggan meminta dihubungi ulang.');
            await expect(pageB.getByTestId('wa-send')).toBeEnabled();
            await expect(pageB.getByTestId('wa-overrides-left')).toContainText('2');
        } finally {
            await ctxB.close();
        }
    });

    test('9. opt-out menyembunyikan (menonaktifkan) WhatsApp dan bisa dibatalkan', async ({ page, context }) => {
        await loginAs(context, 'ayu');
        const p = pickContactable(3);
        await openMap(page, `prospect=${p.id}`);
        const wa = page.getByTestId('wa-button');
        await expect(wa).toBeEnabled();
        await expect(wa).toHaveAttribute('href', /wa\.me/);

        await page.getByTestId('optout-toggle').click();
        await expect(page.getByTestId('optout-toggle')).toHaveAttribute('aria-checked', 'true');
        await expect(wa).toBeDisabled();
        await expect(wa).not.toHaveAttribute('href', /.+/);
        await expect(page.getByTestId('wa-reason')).toBeVisible();
        await expect(page.getByTestId('wa-message')).toHaveCount(0);
        expect(sql<{ do_not_contact: number }>('SELECT do_not_contact FROM prospects WHERE id = ?', [p.id])[0].do_not_contact).toBe(1);
        const detail = await api<{ whatsapp_url: string | null; badges: string[] }>(context, 'GET', `/api/prospects/${p.id}`);
        expect(detail.json.whatsapp_url).toBeNull();
        expect(JSON.stringify(detail.json.badges)).toContain('opt_out');

        // Blocked server-side too, not only in the UI.
        const blocked = await api<{ code?: string }>(context, 'POST', `/api/prospects/${p.id}/contact`, { message: 'tes' });
        expect(blocked.status).toBe(422);

        await page.getByTestId('optout-toggle').click();
        await expect(page.getByTestId('optout-toggle')).toHaveAttribute('aria-checked', 'false');
        await expect(wa).toBeEnabled();
    });
});
