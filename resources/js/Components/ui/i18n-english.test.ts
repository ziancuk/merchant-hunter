import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { setLocale } from '@/i18n';
import { cardFixture } from '@/lib/testing';
import { normalizeError } from '@/lib/api';
import { BLOCKED_REASON_TEXT, whatsappAvailability } from '@/lib/contact';
import BulkBar from '@/Components/pipeline/BulkBar.vue';
import KanbanBoard from '@/Components/pipeline/KanbanBoard.vue';
import PipelineTable from '@/Components/pipeline/PipelineTable.vue';
import PipelineToolbar from '@/Components/pipeline/PipelineToolbar.vue';
import RejectionDialog from '@/Components/pipeline/RejectionDialog.vue';
import { emptyColumns } from '@/Components/pipeline/pipeline';
import ReportTable from '@/Components/report/ReportTable.vue';
import ErrorState from './ErrorState.vue';
import ShortcutHelp from './ShortcutHelp.vue';
import { registerShortcut, shortcutHelpOpen } from '@/composables/useShortcuts';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

afterEach(() => setLocale('id'));

const meta = { current_page: 1, last_page: 2, total: 40, per_page: 25 };

describe('English rendering (pipeline, report, ui)', () => {
    it('renders the pipeline table in English and follows the language switch', async () => {
        const rows = [cardFixture({ id: 2, name: 'Toko Roti', score: 40, score_band: 'medium', phone_present: false, last_contacted_at: null })];
        const w = mount(PipelineTable, { props: { rows, meta, sort: 'score', dir: 'desc' } });
        expect(w.text()).toContain('Terakhir dihubungi');
        setLocale('en');
        await nextTick();
        expect(w.text()).toContain('Last contacted');
        expect(w.get('[data-testid="table-row-2"]').text()).toContain('None');
        expect(w.get('[data-testid="table-row-2"]').text()).toContain('Never');
        expect(w.get('[data-testid="table-row-2"]').text()).toContain('Potential');
        expect(w.get('[data-testid="table-pagination"]').text()).toContain('1–25 of 40 prospects');
        expect(w.get('[data-testid="page-next"]').text()).toBe('Next');
    });

    it('renders the toolbar, bulk bar and rejection dialog in English', () => {
        setLocale('en');
        const toolbar = mount(PipelineToolbar, {
            props: { total: 12, view: 'kanban', filter: {}, filterOptions: { categories: [], provinces: [], cities: [], statuses: [] } as never, activeFilters: 2, exportUrl: '/x' },
        });
        expect(toolbar.text()).toContain('Sales pipeline');
        expect(toolbar.get('[data-testid="pipeline-total"]').text()).toBe('12 prospects match the filter');
        expect(toolbar.get('[data-testid="export-csv"]').text()).toBe('Export CSV');
        expect(toolbar.get('[data-testid="view-toggle-table"]').text()).toBe('Table');

        const bulk = mount(BulkBar, { props: { count: 1 } });
        expect(bulk.get('[data-testid="bulk-count"]').text()).toBe('1 selected');
        expect(bulk.get('[data-testid="bulk-optout"]').text()).toContain('Do not contact');
        expect(bulk.text()).toContain('Select at least 2 prospects for a route');

        const dialog = mount(RejectionDialog, { props: { open: true, reasons: [{ id: 1, label: 'x' }] as never } });
        expect(dialog.get('[data-testid="rejection-confirm"]').text()).toBe('Mark as lost');
        expect(dialog.get('[data-testid="rejection-cancel"]').text()).toBe('Cancel');
        expect(dialog.text()).toContain('Rejection reason');
    });

    it('renders the kanban empty column and status names in English', () => {
        setLocale('en');
        const w = mount(KanbanBoard, { props: { columns: emptyColumns() } });
        expect(w.get('[data-testid="kanban-col-won"]').text()).toContain('Won');
        expect(w.get('[data-testid="kanban-empty-new"]').text()).toBe('No prospects. Drag a card here.');
    });

    it('renders the report table headers in English', async () => {
        const w = mount(ReportTable, { props: { rows: [], meta: { ...meta, total: 0 }, sort: 'score', dir: 'desc', loading: false, error: '' } });
        expect(w.text()).toContain('Tidak ada prospek yang cocok');
        setLocale('en');
        await nextTick();
        expect(w.text()).toContain('No matching prospects');
        expect(w.text()).toContain('Reset filters');
    });

    it('translates the generic error state, shortcut help and API fallbacks', async () => {
        setLocale('en');
        const err = mount(ErrorState);
        expect(err.text()).toContain('Something went wrong');
        expect(err.text()).toContain('Try again');

        const off = registerShortcut({ keys: 'q', description: () => (document.documentElement.lang === 'en' ? 'Quit' : 'Keluar'), handler: () => undefined });
        shortcutHelpOpen.value = true;
        const help = mount(ShortcutHelp, { attachTo: document.body });
        await nextTick();
        expect(document.body.textContent).toContain('Keyboard shortcuts');
        expect(document.body.textContent).toContain('Quit');
        help.unmount();
        shortcutHelpOpen.value = false;
        off();

        expect(normalizeError({ isAxiosError: true, response: { status: 404, data: {} } }).message).toBe('Data not found.');
        expect(whatsappAvailability({ phone: null, whatsappUrl: null }).reason).toBe('No phone number');
        expect(BLOCKED_REASON_TEXT.closed).toBe('This business has closed.');
    });
});
