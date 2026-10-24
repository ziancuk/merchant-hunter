import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PipelineTable from './PipelineTable.vue';
import { cardFixture } from '@/lib/testing';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const meta = { current_page: 2, last_page: 4, total: 80, per_page: 25 };
const rows = [cardFixture({ id: 1, name: 'Kopi Senja', score: 82 }), cardFixture({ id: 2, name: 'Toko Roti', score: 40, score_band: 'medium', phone_present: false, last_contacted_at: '2026-10-20T00:00:00Z', handled_by: 'Ayu' })];

function mountTable(props: Record<string, unknown> = {}) {
    return mount(PipelineTable, { props: { rows, meta, sort: 'score', dir: 'desc', ...props } });
}

describe('PipelineTable', () => {
    it('renders rows, phone presence, handled-by and a map link', () => {
        const w = mountTable();
        expect(w.findAll('tbody tr')).toHaveLength(2);
        expect(w.get('[data-testid="table-row-1"]').text()).toContain('Kopi Senja');
        expect(w.get('[data-testid="table-row-2"]').text()).toContain('Tidak ada');
        expect(w.get('[data-testid="table-row-2"]').text()).toContain('Ayu');
        expect(w.get('[data-testid="map-link-1"]').attributes('href')).toBe('/?prospect=1');
    });

    it('marks the sorted column with aria-sort and emits sort', async () => {
        const w = mountTable({ sort: 'score', dir: 'asc' });
        const ths = w.findAll('th');
        expect(ths.find((t) => t.text().startsWith('Skor'))!.attributes('aria-sort')).toBe('ascending');
        expect(ths.find((t) => t.text().startsWith('Nama'))!.attributes('aria-sort')).toBe('none');
        await w.get('[data-testid="sort-name"]').trigger('click');
        await w.get('[data-testid="sort-last_contacted_at"]').trigger('click');
        expect(w.emitted('sort')).toEqual([['name'], ['last_contacted_at']]);
    });

    it('paginates and changes page size', async () => {
        const w = mountTable();
        expect(w.get('[data-testid="table-pagination"]').text()).toContain('26–50 dari 80 prospek');
        await w.get('[data-testid="page-next"]').trigger('click');
        await w.get('[data-testid="page-prev"]').trigger('click');
        expect(w.emitted('page')).toEqual([[3], [1]]);
        await w.get('[data-testid="per-page"]').setValue('50');
        expect(w.emitted('per-page')).toEqual([[50]]);
    });

    it('disables prev/next at the edges', () => {
        const first = mountTable({ meta: { ...meta, current_page: 1 } });
        expect(first.get('[data-testid="page-prev"]').attributes('disabled')).toBeDefined();
        const last = mountTable({ meta: { ...meta, current_page: 4 } });
        expect(last.get('[data-testid="page-next"]').attributes('disabled')).toBeDefined();
    });

    it('selects rows and all rows on the page', async () => {
        const w = mountTable({ selected: [2] });
        expect((w.get('[data-testid="select-2"]').element as HTMLInputElement).checked).toBe(true);
        await w.get('[data-testid="select-1"]').trigger('change');
        expect(w.emitted('toggle')).toEqual([[1]]);
        await w.get('[data-testid="select-all"]').setValue(true);
        expect(w.emitted('toggle-all')).toEqual([[true]]);
    });

    it('opens a prospect from the name button', async () => {
        const w = mountTable();
        await w.get('[data-testid="open-2"]').trigger('click');
        expect(w.emitted('open')).toEqual([[2]]);
    });

    it('shows a status column only when the server sends pipeline_status', () => {
        expect(mountTable().text()).not.toContain('Status');
        const w = mountTable({ rows: [cardFixture({ id: 5, pipeline_status: 'won' })] });
        expect(w.text()).toContain('Deal');
    });

    it('renders the loading skeleton, empty and error states', async () => {
        const loading = mountTable({ rows: [], loading: true });
        expect(loading.find('[data-testid="table-skeleton"]').exists()).toBe(true);

        const empty = mountTable({ rows: [] });
        expect(empty.find('[data-testid="table-empty"]').exists()).toBe(true);
        await empty.get('[data-testid="table-empty"] button').trigger('click');
        expect(empty.emitted('reset-filter')).toHaveLength(1);

        const error = mountTable({ rows: [], error: 'Gagal.' });
        expect(error.get('[role="alert"]').text()).toContain('Gagal.');
        await error.get('[data-testid="error-state"] button').trigger('click');
        expect(error.emitted('retry')).toHaveLength(1);
    });
});
