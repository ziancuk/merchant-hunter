import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import Index from './Index.vue';
import { api } from '@/lib/api';
import { cardFixture, mockMatchMedia, prospectFixture } from '@/lib/testing';

const visit = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { visit: (...a: unknown[]) => visit(...a), post: vi.fn() },
    usePage: () => ({ props: {}, url: '/pipeline' }),
}));
vi.mock('@/lib/api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/api')>()),
    api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

const Shell = defineComponent({ template: '<div><slot /></div>' });
const reasons = [
    { id: 7, key: 'price', label: 'Harga terlalu mahal' },
    { id: 8, key: 'no_need', label: 'Belum butuh' },
];
const baseProps = () => ({
    view: 'kanban' as const,
    columns: {
        new: { total: 2, cards: [cardFixture({ id: 1, name: 'Kopi Senja', score: 82 }), cardFixture({ id: 2, name: 'Toko Roti', score: 60 })], next_cursor: null },
        contacted: { total: 0, cards: [], next_cursor: null },
        demo: { total: 0, cards: [], next_cursor: null },
        won: { total: 0, cards: [], next_cursor: null },
        lost: { total: 0, cards: [], next_cursor: null },
    },
    filter_options: { provinces: [], cities: [], categories: [], pipeline_statuses: ['new', 'contacted', 'demo', 'won', 'lost'] as never[] },
    rejection_reasons: reasons,
    contact_rules: { warn_days: 7, min_interval_days: 14, max_overrides: 2 },
});

let wrapper: VueWrapper | null = null;
function mountPage(props: Record<string, unknown> = {}) {
    wrapper = mount(Index, { props: { ...baseProps(), ...props }, global: { stubs: { AppShell: Shell } }, attachTo: document.body });
    return wrapper;
}

async function drag(w: VueWrapper, id: number, to: string) {
    await w.get(`[data-testid="kanban-card-${id}"]`).trigger('dragstart');
    await w.get(`[data-testid="kanban-col-${to}"]`).trigger('dragover');
    await w.get(`[data-testid="kanban-col-${to}"]`).trigger('drop');
    await flushPromises();
}

const count = (w: VueWrapper, s: string) => w.get(`[data-testid="kanban-count-${s}"]`).text();
const inCol = (w: VueWrapper, s: string, id: number) => w.find(`[data-testid="kanban-col-${s}"] [data-testid="kanban-card-${id}"]`).exists();

beforeEach(() => {
    mockMatchMedia(false);
    get.mockReset();
    post.mockReset();
    visit.mockReset();
    window.history.replaceState(null, '', '/pipeline');
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.innerHTML = '';
});

describe('Pipeline/Index kanban', () => {
    it('renders columns and totals from props', () => {
        const w = mountPage();
        expect(count(w, 'new')).toBe('2');
        expect(inCol(w, 'new', 1)).toBe(true);
        expect(w.get('[data-testid="pipeline-total"]').text()).toContain('2 prospek');
        expect(w.get('[data-testid="contact-rules"]').text()).toContain('14 hari');
    });

    it('drop → POST status, optimistic move and totals', async () => {
        post.mockResolvedValue({ data: { prospect: prospectFixture({ id: 1, pipeline_status: 'contacted', handled_by: { id: 1, name: 'Ayu' } }) } });
        const w = mountPage();
        await drag(w, 1, 'contacted');
        expect(post).toHaveBeenCalledWith('/api/prospects/1/status', { status: 'contacted' });
        expect(inCol(w, 'contacted', 1)).toBe(true);
        expect(inCol(w, 'new', 1)).toBe(false);
        expect(count(w, 'new')).toBe('1');
        expect(count(w, 'contacted')).toBe('1');
        expect(w.get('[data-testid="kanban-col-contacted"]').text()).toContain('Ayu');
    });

    it('moves optimistically before the API answers', async () => {
        let resolve!: (v: unknown) => void;
        post.mockReturnValue(new Promise((r) => (resolve = r)) as never);
        const w = mountPage();
        await drag(w, 1, 'demo');
        expect(inCol(w, 'demo', 1)).toBe(true);
        resolve({ data: { prospect: prospectFixture({ id: 1, pipeline_status: 'demo' }) } });
        await flushPromises();
        expect(inCol(w, 'demo', 1)).toBe(true);
    });

    it('rolls back and toasts when the API fails', async () => {
        post.mockRejectedValue({ message: 'Server error', errors: {}, status: 500 });
        const w = mountPage();
        await drag(w, 1, 'demo');
        expect(post).toHaveBeenCalledTimes(1);
        expect(inCol(w, 'new', 1)).toBe(true);
        expect(inCol(w, 'demo', 1)).toBe(false);
        expect(count(w, 'new')).toBe('2');
        expect(count(w, 'demo')).toBe('0');
    });

    it('asks for a rejection reason when moving to Ditolak, and only then calls the API', async () => {
        post.mockResolvedValue({ data: { prospect: prospectFixture({ id: 2, pipeline_status: 'lost' }) } });
        const w = mountPage();
        await drag(w, 2, 'lost');
        expect(post).not.toHaveBeenCalled();
        expect(inCol(w, 'new', 2)).toBe(true);
        expect(w.find('[data-testid="rejection-dialog"]').exists()).toBe(true);
        expect(w.get('[data-testid="rejection-confirm"]').attributes('disabled')).toBeDefined();

        await w.get('[data-testid="rejection-select"]').setValue(8);
        await w.get('[data-testid="rejection-confirm"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/2/status', { status: 'lost', rejection_reason_id: 8 });
        expect(inCol(w, 'lost', 2)).toBe(true);
        expect(w.find('[data-testid="rejection-dialog"]').exists()).toBe(false);
    });

    it('cancelling the rejection dialog leaves the card where it was', async () => {
        const w = mountPage();
        await drag(w, 2, 'lost');
        await w.get('[data-testid="rejection-cancel"]').trigger('click');
        expect(post).not.toHaveBeenCalled();
        expect(inCol(w, 'new', 2)).toBe(true);
        expect(w.find('[data-testid="rejection-dialog"]').exists()).toBe(false);
    });

    it('loads more cards with the cursor and dedupes', async () => {
        get.mockResolvedValue({ data: { columns: { new: { total: 3, cards: [cardFixture({ id: 2 }), cardFixture({ id: 3, name: 'Baru', score: 10 })], next_cursor: null } } } });
        const w = mountPage({ columns: { ...baseProps().columns, new: { total: 3, cards: baseProps().columns.new.cards, next_cursor: '60:2' } } });
        await w.get('[data-testid="kanban-more-new"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenCalledWith('/api/pipeline/cards?status=new&per_status=50&cursor=60%3A2');
        expect(w.get('[data-testid="kanban-col-new"]').findAll('[data-testid^="kanban-card-"]')).toHaveLength(3);
        expect(w.find('[data-testid="kanban-more-new"]').exists()).toBe(false);
    });

    it('refetches with the filter and syncs the URL when the filter changes', async () => {
        get.mockResolvedValue({ data: { columns: {} } });
        const w = mountPage();
        await w.get('[data-testid="filter-toggle"]').trigger('click');
        await w.get('[data-testid="f-score-min"]').setValue('70');
        await w.get('[data-testid="f-score-min"]').trigger('change');
        await flushPromises();
        expect(get).toHaveBeenCalledWith('/api/pipeline/cards?score_min=70&per_status=50');
        expect(window.location.search).toBe('?view=kanban&score_min=70');
        expect(w.get('[data-testid="export-csv"]').attributes('href')).toBe('/prospects/export.csv?score_min=70');
        expect(w.find('[data-testid="board-empty"]').exists()).toBe(true);
    });

    it('shows an error state with retry when the reload fails and there is nothing to show', async () => {
        get.mockRejectedValueOnce({ message: 'Gagal memuat', errors: {}, status: 500 });
        const w = mountPage({ columns: {} });
        await w.get('[data-testid="filter-toggle"]').trigger('click');
        await w.get('[data-testid="f-phone"]').setValue(true);
        await flushPromises();
        expect(w.get('[data-testid="error-state"]').text()).toContain('Gagal memuat');
        get.mockResolvedValue({ data: { columns: { new: { total: 1, cards: [cardFixture({ id: 5 })], next_cursor: null } } } });
        await w.get('[data-testid="error-state"] button').trigger('click');
        await flushPromises();
        expect(inCol(w, 'new', 5)).toBe(true);
    });

    it('shows the empty state on an empty board', () => {
        const w = mountPage({ columns: {} });
        expect(w.find('[data-testid="board-empty"]').exists()).toBe(true);
    });
});

describe('Pipeline/Index table + bulk', () => {
    const tableResponse = { data: { data: [cardFixture({ id: 1, name: 'Kopi Senja' }), cardFixture({ id: 2, name: 'Toko Roti' })], meta: { current_page: 1, last_page: 2, total: 40, per_page: 25 } } };

    async function mountTable() {
        get.mockResolvedValue(tableResponse);
        const w = mountPage({ view: 'table' });
        await flushPromises();
        return w;
    }

    it('fetches the table on mount and sorts via the API', async () => {
        const w = await mountTable();
        expect(get).toHaveBeenCalledWith('/api/pipeline/table?sort=score&dir=desc&page=1&per_page=25');
        expect(w.findAll('tbody tr')).toHaveLength(2);
        await w.get('[data-testid="sort-name"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/api/pipeline/table?sort=name&dir=asc&page=1&per_page=25');
        await w.get('[data-testid="sort-name"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/api/pipeline/table?sort=name&dir=desc&page=1&per_page=25');
        await w.get('[data-testid="page-next"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/api/pipeline/table?sort=name&dir=desc&page=2&per_page=25');
    });

    it('switches views through the toggle', async () => {
        const w = mountPage();
        get.mockResolvedValue(tableResponse);
        await w.get('[data-testid="view-toggle-table"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-testid="pipeline-table"]').exists()).toBe(true);
        expect(window.location.search).toContain('view=table');
        expect(w.get('[data-testid="view-toggle-table"]').attributes('aria-pressed')).toBe('true');
    });

    it('bulk status change posts ids and reports updated/skipped', async () => {
        const w = await mountTable();
        post.mockResolvedValue({ data: { updated: 1, skipped: [{ id: 2, reason: 'same_status' }] } });
        await w.get('[data-testid="select-1"]').setValue(true);
        await w.get('[data-testid="select-2"]').setValue(true);
        expect(w.get('[data-testid="bulk-count"]').text()).toBe('2 dipilih');
        await w.get('[data-testid="bulk-status"]').setValue('demo');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/bulk', { ids: [1, 2], action: 'status', status: 'demo' });
        const result = w.get('[data-testid="bulk-result"]').text();
        expect(result).toContain('1 prospek diperbarui');
        expect(result).toContain('Toko Roti');
        expect(result).toContain('Status sudah sama');
        expect(w.find('[data-testid="bulk-bar"]').exists()).toBe(false);
    });

    it('bulk reject requires a reason first', async () => {
        const w = await mountTable();
        post.mockResolvedValue({ data: { updated: 1, skipped: [] } });
        await w.get('[data-testid="select-1"]').setValue(true);
        await w.get('[data-testid="bulk-status"]').setValue('lost');
        expect(post).not.toHaveBeenCalled();
        await w.get('[data-testid="rejection-select"]').setValue(7);
        await w.get('[data-testid="rejection-confirm"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/bulk', { ids: [1], action: 'status', status: 'lost', rejection_reason_id: 7 });
        expect(w.find('[data-testid="rejection-dialog"]').exists()).toBe(false);
    });

    it('bulk opt-out posts the opt_out action', async () => {
        const w = await mountTable();
        post.mockResolvedValue({ data: { updated: 2, skipped: [] } });
        await w.get('[data-testid="select-all"]').setValue(true);
        await w.get('[data-testid="bulk-optout"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/bulk', { ids: [1, 2], action: 'opt_out' });
    });

    it('plans a route for 2..30 selected prospects only', async () => {
        const w = await mountTable();
        await w.get('[data-testid="select-1"]').setValue(true);
        expect(w.get('[data-testid="bulk-route"]').attributes('disabled')).toBeDefined();
        await w.get('[data-testid="select-2"]').setValue(true);
        await w.get('[data-testid="bulk-route"]').trigger('click');
        expect(visit).toHaveBeenCalledWith('/?route=1,2');
    });

    it('shows the table error state with retry', async () => {
        get.mockRejectedValueOnce({ message: 'Tabel gagal', errors: {}, status: 500 });
        const w = mountPage({ view: 'table' });
        await flushPromises();
        expect(w.get('[data-testid="error-state"]').text()).toContain('Tabel gagal');
        get.mockResolvedValue(tableResponse);
        await w.get('[data-testid="error-state"] button').trigger('click');
        await flushPromises();
        expect(w.findAll('tbody tr')).toHaveLength(2);
    });
});

describe('Pipeline/Index drawer', () => {
    it('opens the drawer on card click and reflects drawer updates on the card without reload', async () => {
        get.mockResolvedValue({ data: prospectFixture({ id: 1, name: 'Kopi Senja' }) });
        const w = mountPage();
        await w.get('[data-testid="kanban-card-1"] [data-testid="prospect-card"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenCalledWith('/api/prospects/1');

        post.mockResolvedValue({ data: { prospect: prospectFixture({ id: 1, pipeline_status: 'demo', handled_by: { id: 1, name: 'Budi' } }) } });
        await document.body.querySelector<HTMLElement>('[data-testid="status-demo"]')!.click();
        await flushPromises();
        expect(inCol(w, 'demo', 1)).toBe(true);
        expect(w.get('[data-testid="kanban-col-demo"]').text()).toContain('Budi');
        expect(count(w, 'demo')).toBe('1');
    });

    it('"Lihat di peta" visits /?prospect=ID', async () => {
        get.mockResolvedValue({ data: prospectFixture({ id: 1 }) });
        const w = mountPage();
        await w.get('[data-testid="kanban-card-1"] [data-testid="prospect-card"]').trigger('click');
        await flushPromises();
        const btn = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.includes('Lihat di peta'));
        btn!.click();
        expect(visit).toHaveBeenCalledWith('/?prospect=1');
    });
});
