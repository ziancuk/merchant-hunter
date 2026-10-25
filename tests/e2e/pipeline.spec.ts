import { test, expect, freshData, loginAs, pickContactable } from './support/fixtures';
import { sql } from './support/env';

freshData();

const status = (id: number) => sql<{ pipeline_status: string }>('SELECT pipeline_status FROM prospects WHERE id = ?', [id])[0].pipeline_status;

test.describe('Pipeline', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'ayu');
    });

    test('10. Kanban drag & drop memindah status', async ({ page, collected }) => {
        const p = pickContactable(0);
        await page.goto('/pipeline');
        const card = page.getByTestId(`kanban-card-${p.id}`);
        await expect(page.getByTestId('kanban-col-new').getByTestId(`kanban-card-${p.id}`)).toBeVisible();
        const newBefore = Number(await page.getByTestId('kanban-count-new').innerText().then((t) => t.replace(/\D/g, '')));
        const demoBefore = Number(await page.getByTestId('kanban-count-demo').innerText().then((t) => t.replace(/\D/g, '')));

        await card.dragTo(page.getByTestId('kanban-col-demo'));

        await expect(page.getByTestId('kanban-col-demo').getByTestId(`kanban-card-${p.id}`)).toBeVisible();
        await expect(page.getByTestId('kanban-col-new').getByTestId(`kanban-card-${p.id}`)).toHaveCount(0);
        await expect(page.getByTestId('kanban-count-demo')).toHaveText(String(demoBefore + 1).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
        expect(newBefore).toBeGreaterThan(0);
        await expect.poll(() => status(p.id)).toBe('demo');

        // Persisted: a reload shows the card in the new column.
        await page.reload();
        await expect(page.getByTestId('kanban-col-demo').getByTestId(`kanban-card-${p.id}`)).toBeVisible();
        expect(collected.pageErrors).toEqual([]);
    });

    test('10b. memindah ke Kalah meminta alasan penolakan (drag & drop)', async ({ page }) => {
        const p = pickContactable(1);
        await page.goto('/pipeline');
        await page.getByTestId(`kanban-card-${p.id}`).dragTo(page.getByTestId('kanban-col-lost'));
        const dialog = page.getByTestId('rejection-dialog');
        await expect(dialog).toBeVisible();
        await expect(page.getByTestId('rejection-confirm')).toBeDisabled();
        expect(status(p.id)).toBe('new'); // nothing changed before a reason is given
        await page.getByTestId('rejection-select').selectOption({ index: 1 });
        await page.getByTestId('rejection-confirm').click();
        await expect(page.getByTestId('kanban-col-lost').getByTestId(`kanban-card-${p.id}`)).toBeVisible();
        await expect.poll(() => status(p.id)).toBe('lost');
        const reason = sql<{ rejection_reason_id: number | null }>('SELECT rejection_reason_id FROM prospects WHERE id = ?', [p.id])[0];
        expect(reason.rejection_reason_id).not.toBeNull();
    });

    test('10c. tampilan tabel dan menu pindah (alternatif non-drag)', async ({ page }) => {
        const p = pickContactable(2);
        await page.goto('/pipeline');
        await page.getByTestId('view-toggle-table').click();
        await expect(page.getByTestId('pipeline-table')).toBeVisible();
        await expect(page.getByTestId('pipeline-total')).toBeVisible();
        const total = sql<{ n: number }>("SELECT COUNT(*) AS n FROM prospects WHERE lifecycle_status = 'open'")[0].n;
        const digits = (await page.getByTestId('pipeline-total').innerText()).replace(/\D/g, '');
        expect(Number(digits)).toBe(total);

        await page.getByTestId('view-toggle-kanban').click();
        await page.getByTestId(`move-menu-${p.id}`).click();
        await page.getByTestId('move-to-contacted').click();
        await expect.poll(() => status(p.id)).toBe('contacted');
    });
});
