import { test, expect, freshData, loginAs } from './support/fixtures';
import { sql } from './support/env';

freshData();

const openCount = () => sql<{ n: number }>("SELECT COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open'")[0].n;
const idFormat = (n: number) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

test.describe('Laporan', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'ayu');
    });

    test('tabel menampilkan jumlah prospek yang sama dengan database dan bisa diurutkan', async ({ page, collected }) => {
        await page.goto('/reports');
        await expect(page.getByTestId('report-table')).toBeVisible();
        await expect(page.getByTestId('report-total')).toContainText(idFormat(openCount()));
        await expect(page.getByTestId('report-table').locator('tbody tr')).toHaveCount(25);

        await page.getByTestId('sort-name').click();
        await expect(page.getByTestId('sort-name').locator('..')).toHaveAttribute('aria-sort', 'ascending');
        // The header flips at once; the rows follow when the server answers.
        await expect(page.getByTestId('report-table')).not.toHaveAttribute('aria-busy', 'true');
        await expect
            .poll(async () => {
                const names = await page.getByTestId('report-table').locator('tbody tr td:first-child').allInnerTexts();
                return JSON.stringify(names) === JSON.stringify([...names].sort((a, b) => a.localeCompare(b, 'id')));
            })
            .toBe(true);
        expect(collected.pageErrors).toEqual([]);
    });

    test('filter mengubah total dan tautan unduhan CSV/Excel mengikuti filter', async ({ page }) => {
        await page.goto('/reports?has_phone=1&score_min=70');
        const expected = sql<{ n: number }>("SELECT COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open' AND score >= 70 AND phone IS NOT NULL AND phone <> ''")[0].n;
        await expect(page.getByTestId('report-total')).toContainText(idFormat(expected));
        await expect(page.getByTestId('export-csv')).toHaveAttribute('href', /\/prospects\/export\.csv\?.*score_min=70/);
        await expect(page.getByTestId('export-xlsx')).toHaveAttribute('href', /\/prospects\/export\.xlsx\?.*score_min=70/);
    });

    test('unduhan Excel adalah berkas .xlsx asli dan CSV tetap tersedia', async ({ page }) => {
        await page.goto('/reports?score_min=90');
        const xlsx = await page.request.get(await page.getByTestId('export-xlsx').getAttribute('href') as string);
        expect(xlsx.status()).toBe(200);
        expect(xlsx.headers()['content-type']).toContain('spreadsheetml.sheet');
        expect(xlsx.headers()['content-disposition']).toContain('.xlsx');
        expect((await xlsx.body()).subarray(0, 2).toString()).toBe('PK');

        const csv = await page.request.get(await page.getByTestId('export-csv').getAttribute('href') as string);
        expect(csv.status()).toBe(200);
        expect(await csv.text()).toContain('id,nama,kategori');
    });

    test('telepon simulasi diberi badge dan ada tautan ke peta', async ({ page }) => {
        await page.goto('/reports');
        await expect(page.getByTestId('report-table').getByTestId('simulasi-badge').first()).toBeVisible();
        await expect(page.locator('[data-testid^="report-map-link-"]').first()).toHaveAttribute('href', /^\/\?prospect=\d+$/);
    });
});
