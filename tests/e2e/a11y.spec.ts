import { test, expect, freshData, loginAs, pickContactable } from './support/fixtures';
import { scan } from './support/axe';
import { openMap } from './support/map';

freshData();

const PAGES: { name: string; path: string; ready: (page: import('@playwright/test').Page) => Promise<void> }[] = [
    { name: 'peta', path: '/?intro=0&motion=0', ready: async (p) => void (await openMap(p)) },
    { name: 'pipeline (kanban)', path: '/pipeline', ready: async (p) => void (await p.getByTestId('kanban-board').waitFor()) },
    { name: 'pipeline (tabel)', path: '/pipeline?view=table', ready: async (p) => void (await p.getByTestId('pipeline-table').waitFor()) },
    { name: 'dashboard', path: '/dashboard', ready: async (p) => void (await p.getByTestId('funnel').waitFor()) },
    { name: 'impact', path: '/impact', ready: async (p) => void (await p.getByTestId('impact-results').waitFor()) },
    { name: 'master/data-sources', path: '/master/data-sources', ready: async (p) => void (await p.getByTestId('ds-list').waitFor()) },
    { name: 'master/outlets', path: '/master/outlets', ready: async (p) => void (await p.getByTestId('outlet-table').waitFor()) },
    { name: 'master/categories', path: '/master/categories', ready: async (p) => void (await p.getByTestId('category-list').waitFor()) },
    { name: 'master/settings', path: '/master/settings', ready: async (p) => void (await p.getByTestId('settings-save').waitFor()) },
    { name: 'scheduler', path: '/scheduler', ready: async (p) => void (await p.getByTestId('scheduler-table').waitFor()) },
];

test('a11y: halaman login tanpa pelanggaran serius/kritis', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
    const { blocking } = await scan(page);
    expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
});

test.describe('a11y: halaman terautentikasi', () => {
    test.beforeEach(async ({ context, page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await loginAs(context, 'ayu');
    });

    for (const def of PAGES) {
        test(`a11y: ${def.name}`, async ({ page }) => {
            await page.goto(def.path);
            await def.ready(page);
            await page.waitForTimeout(500); // let entrance transitions finish so contrast is measured on final colours
            const { blocking } = await scan(page);
            expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
        });
    }

    test('a11y: peta dengan drawer prospek terbuka', async ({ page }) => {
        const p = pickContactable();
        await openMap(page, `prospect=${p.id}`);
        await expect(page.getByTestId('drawer-content')).toBeVisible();
        await page.waitForTimeout(500);
        const { blocking } = await scan(page);
        expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
    });
});

// Known application findings (see FINDINGS.md). These dedicated tests assert the CORRECT behaviour and stay fixme
// until the app is fixed; the rules are excluded from the generic scans above only (support/axe.ts DISABLED_RULES).
test.describe('a11y: temuan terbuka', () => {
    test.beforeEach(async ({ context, page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await loginAs(context, 'ayu');
    });

    test.fixme('F-001 label-in-name (WCAG 2.5.3): nama aksesibel memuat teks yang terlihat di dock, kartu Kanban, kartogram', async ({ page }) => {
        for (const path of ['/pipeline', '/dashboard']) {
            await page.goto(path);
            await page.waitForTimeout(800);
            const { blocking } = await scan(page, { onlyRules: ['label-content-name-mismatch'] });
            expect(blocking, `${path}: ${JSON.stringify(blocking, null, 2)}`).toEqual([]);
        }
    });

    test.fixme('F-002 definition-list: kartu sumber data memakai struktur dl yang valid', async ({ page }) => {
        await page.goto('/master/data-sources');
        await page.getByTestId('ds-list').waitFor();
        const { blocking } = await scan(page, { onlyRules: ['definition-list', 'dlitem'] });
        expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
    });
});
