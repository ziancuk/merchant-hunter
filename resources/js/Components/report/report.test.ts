import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api');
    return { ...actual, api: { get: vi.fn() } };
});

import { api } from '@/lib/api';
import { buildDownloadUrl, buildMapUrl, buildReportPageUrl, buildReportUrl, REPORT_COLUMNS, type ReportRow } from './report';
import ReportTable from './ReportTable.vue';
import { useReportTable } from './useReport';

const get = (api as unknown as { get: ReturnType<typeof vi.fn> }).get;

function row(over: Partial<ReportRow> = {}): ReportRow {
    return {
        id: 1, name: 'Kopi Senja', category: 'Kafe', city: 'Jakarta Pusat', province: 'DKI Jakarta', address: 'Jl. Melati 4',
        phone: '0812-5555-0101', phone_simulated: true, rating: 4.6, review_count: 312, score: 82, score_band: 'hot',
        pipeline_status: 'contacted', handled_by: 'Ayu', last_contacted_at: '2026-10-23T08:00:00Z', distance_m: 640, ...over,
    };
}
const meta = { current_page: 2, last_page: 4, total: 80, per_page: 25 };

describe('report URL builders', () => {
    const filter = { category_id: [1, 2], score_min: 60, pipeline_status: ['new' as const] };

    it('builds the table URL with filter, sort, page and per_page', () => {
        expect(buildReportUrl({}, { sort: 'score', dir: 'desc', page: 1, perPage: 25 })).toBe('/api/reports/prospects?sort=score&dir=desc&page=1&per_page=25');
        expect(buildReportUrl(filter, { sort: 'distance', dir: 'asc', page: 3, perPage: 50 })).toBe(
            '/api/reports/prospects?category_id[]=1&category_id[]=2&pipeline_status[]=new&score_min=60&sort=distance&dir=asc&page=3&per_page=50',
        );
    });

    it('builds CSV and Excel download URLs with the same filter', () => {
        expect(buildDownloadUrl('csv', {})).toBe('/prospects/export.csv');
        expect(buildDownloadUrl('xlsx', {})).toBe('/prospects/export.xlsx');
        expect(buildDownloadUrl('xlsx', filter)).toBe('/prospects/export.xlsx?category_id[]=1&category_id[]=2&pipeline_status[]=new&score_min=60');
    });

    it('builds the page and map URLs', () => {
        expect(buildReportPageUrl({})).toBe('/reports');
        expect(buildReportPageUrl({ city: 'Palu' })).toBe('/reports?city=Palu');
        expect(buildMapUrl(7)).toBe('/?prospect=7');
    });

    it('only declares sort keys the API accepts', () => {
        const keys = REPORT_COLUMNS.flatMap((c) => (c.sortKey ? [c.sortKey] : []));
        expect(keys).toEqual(['name', 'category', 'city', 'province', 'rating', 'review_count', 'score', 'status', 'last_contacted_at', 'distance']);
    });
});

describe('useReportTable', () => {
    beforeEach(() => get.mockReset());

    it('fetches with the current filter, sort and paging', async () => {
        get.mockResolvedValue({ data: { data: [row()], meta } });
        const t = useReportTable(ref({ city: 'Palu' }));
        await t.fetch();
        expect(get).toHaveBeenCalledWith('/api/reports/prospects?city=Palu&sort=score&dir=desc&page=1&per_page=25');
        expect(t.rows.value).toHaveLength(1);
        expect(t.meta.value.total).toBe(80);
    });

    it('flips direction on the active column and starts new columns in their natural direction', async () => {
        get.mockResolvedValue({ data: { data: [], meta: { ...meta, current_page: 1 } } });
        const t = useReportTable(ref({}));
        await t.setSort('score');
        expect(t.dir.value).toBe('asc');
        await t.setSort('name');
        expect([t.sort.value, t.dir.value]).toEqual(['name', 'asc']);
        await t.setSort('last_contacted_at');
        expect([t.sort.value, t.dir.value]).toEqual(['last_contacted_at', 'desc']);
    });

    it('keeps the page inside bounds and resets it when the page size changes', async () => {
        get.mockResolvedValue({ data: { data: [], meta } });
        const t = useReportTable(ref({}));
        await t.fetch();
        await t.setPage(99);
        expect(t.page.value).toBe(4);
        await t.setPerPage(50);
        expect([t.page.value, t.perPage.value]).toEqual([1, 50]);
    });

    it('drops a stale response and reports errors', async () => {
        let first: (v: unknown) => void = () => {};
        get.mockImplementationOnce(() => new Promise((r) => (first = r)));
        get.mockResolvedValueOnce({ data: { data: [row({ id: 2 })], meta } });
        const t = useReportTable(ref({}));
        const p1 = t.fetch();
        await t.fetch();
        first({ data: { data: [row({ id: 1 })], meta } });
        await p1;
        expect(t.rows.value.map((r) => r.id)).toEqual([2]);

        get.mockRejectedValueOnce(new Error('boom'));
        await t.fetch();
        expect(t.error.value).not.toBe('');
    });
});

describe('ReportTable', () => {
    const mountTable = (props: Record<string, unknown> = {}) => mount(ReportTable, { props: { rows: [row(), row({ id: 2, name: 'Bengkel Jaya', phone: null, phone_simulated: false, rating: null, review_count: null, address: null, city: null, province: null, last_contacted_at: null, distance_m: null, handled_by: null, pipeline_status: 'new', score: 40, score_band: 'medium' })], meta, sort: 'score', dir: 'desc', ...props } });

    it('renders every column and a map link', () => {
        const w = mountTable();
        expect(w.findAll('thead th')).toHaveLength(REPORT_COLUMNS.length + 1);
        const r1 = w.get('[data-testid="report-row-1"]').text();
        for (const t of ['Kopi Senja', 'Kafe', 'Jakarta Pusat', 'DKI Jakarta', 'Jl. Melati 4', '0812-5555-0101', '4,6', '312', '82', 'Dihubungi', 'Ayu', '640 m']) expect(r1, t).toContain(t);
        expect(w.get('[data-testid="report-map-link-1"]').attributes('href')).toBe('/?prospect=1');
    });

    it('labels simulated phones and shows readable placeholders for missing values', () => {
        const w = mountTable();
        expect(w.get('[data-testid="report-row-1"]').find('[data-testid="simulasi-badge"]').exists()).toBe(true);
        const r2 = w.get('[data-testid="report-row-2"]');
        expect(r2.find('[data-testid="simulasi-badge"]').exists()).toBe(false);
        expect(r2.text()).toContain('Tidak ada');
        expect(r2.text()).toContain('Belum tersedia');
        expect(r2.text()).toContain('Belum pernah');
    });

    it('marks the sorted column with aria-sort and emits sort / paging events', async () => {
        const w = mountTable({ sort: 'distance', dir: 'asc' });
        expect(w.findAll('th').find((t) => t.text().startsWith('Jarak'))!.attributes('aria-sort')).toBe('ascending');
        expect(w.findAll('th').find((t) => t.text().startsWith('Nama'))!.attributes('aria-sort')).toBe('none');
        await w.get('[data-testid="sort-rating"]').trigger('click');
        await w.get('[data-testid="report-next"]').trigger('click');
        await w.get('[data-testid="report-prev"]').trigger('click');
        await w.get('[data-testid="report-per-page"]').setValue('50');
        expect(w.emitted('sort')).toEqual([['rating']]);
        expect(w.emitted('page')).toEqual([[3], [1]]);
        expect(w.emitted('per-page')).toEqual([[50]]);
        expect(w.get('[data-testid="report-pagination"]').text()).toContain('26–50 dari 80 prospek');
    });

    it('shows skeleton, empty and error states', async () => {
        expect(mountTable({ rows: [], loading: true }).find('[data-testid="report-skeleton"]').exists()).toBe(true);
        const empty = mountTable({ rows: [] });
        expect(empty.find('[data-testid="report-empty"]').exists()).toBe(true);
        await empty.get('button').trigger('click');
        expect(empty.emitted('reset-filter')).toHaveLength(1);
        await flushPromises();
        expect(mountTable({ rows: [], error: 'Gagal' }).text()).toContain('Gagal memuat laporan');
    });
});
