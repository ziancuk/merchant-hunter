/* eslint-disable vue/one-component-per-file, vue/require-prop-types -- inline stubs for Inertia/AppShell */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) }),
    router: { visit: vi.fn(), post: vi.fn() },
    usePage: () => ({ url: '/', props: {} }),
}));
vi.mock('@/Layouts/AppShell.vue', () => ({ default: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }) }));
vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api');
    return { ...actual, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

import { api } from '@/lib/api';
import { mockMatchMedia } from '@/lib/testing';
import Dashboard from './Dashboard/Index.vue';
import Impact from './Impact/Index.vue';
import Runs from './Scheduler/Runs.vue';
import Settings from './Master/Settings.vue';

const mocked = api as unknown as Record<'get' | 'post' | 'put' | 'delete', ReturnType<typeof vi.fn>>;
let wrapper: VueWrapper | null = null;
beforeEach(() => {
    Object.values(mocked).forEach((f) => f.mockReset());
    mockMatchMedia(true);
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.useRealTimers();
});

const defaults = {
    outlets: 190, google_syncs_per_month: 30, google_groups_per_outlet: 12, avg_pages: 1, details_ratio: 0,
    cost_per_1000_search: 32, cost_per_1000_details: 17, usd_idr: 16000, prospects_per_outlet: 75,
    conv_found_to_contact: 0.2, conv_contact_to_demo: 0.15, conv_demo_to_won: 0.25, deal_value_idr: 5000000,
    hours_saved_per_week: 10, hourly_cost_idr: 50000,
};
const results = { google_calls_per_month: 68400, google_cost_usd: 2188.8, google_cost_idr: 35020800, osm_cost_idr: 0, prospects_found: 14250, expected_deals: 106.88, expected_revenue_idr: 534375000, hours_saved_per_month: 43.3, hours_saved_value_idr: 2166667 };

describe('Impact page', () => {
    it('shows every assumption as an editable field and the results', () => {
        wrapper = mount(Impact, { props: { assumptions: defaults, defaults, results } });
        for (const k of Object.keys(defaults)) expect(wrapper.find(`[data-testid="impact-${k}"]`).exists(), k).toBe(true);
        expect(wrapper.get('[data-testid="result-google-calls"]').text()).toBe('68.400');
        expect(wrapper.get('[data-testid="result-osm-cost"]').text()).toBe('Rp 0');
        expect(wrapper.text()).toContain('estimasi');
    });

    it('debounces edits into one PUT with all assumptions and renders the returned results', async () => {
        vi.useFakeTimers();
        mocked.put.mockResolvedValue({ data: { assumptions: { ...defaults, outlets: 300 }, results: { ...results, google_calls_per_month: 108000 } } });
        wrapper = mount(Impact, { props: { assumptions: defaults, defaults, results } });
        await wrapper.get('[data-testid="impact-outlets"]').setValue('250');
        await wrapper.get('[data-testid="impact-outlets"]').setValue('300');
        expect(mocked.put).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(500);
        await flushPromises();
        expect(mocked.put).toHaveBeenCalledTimes(1);
        expect(mocked.put).toHaveBeenCalledWith('/api/settings/impact', { ...defaults, outlets: 300 });
        expect(wrapper.get('[data-testid="result-google-calls"]').text()).toBe('108.000');
    });

    it('shows a validation error from 422 and restores defaults on reset', async () => {
        vi.useFakeTimers();
        mocked.put.mockRejectedValueOnce({ isAxiosError: true, response: { status: 422, data: { message: 'x', errors: { usd_idr: ['Kurs harus ≥ 1.'] } } } });
        wrapper = mount(Impact, { props: { assumptions: defaults, defaults, results } });
        await wrapper.get('[data-testid="impact-usd_idr"]').setValue('0');
        await vi.advanceTimersByTimeAsync(500);
        await flushPromises();
        expect(wrapper.text()).toContain('Kurs harus ≥ 1.');
        mocked.put.mockResolvedValueOnce({ data: { assumptions: defaults, results } });
        await wrapper.get('[data-testid="impact-reset"]').trigger('click');
        await flushPromises();
        expect(mocked.put).toHaveBeenLastCalledWith('/api/settings/impact', defaults);
        expect((wrapper.get('[data-testid="impact-usd_idr"]').element as HTMLInputElement).value).toBe('16000');
    });
});

describe('Scheduler runs page', () => {
    const run = { id: 7, command: 'hunter:sync', status: 'success', started_at: '2026-10-24T01:00:00Z', finished_at: null, duration_ms: 9000, outlets_total: 190, outlets_ok: 190, outlets_failed: 0, prospects_new: 12, prospects_updated: 3, prospects_closed: 1, api_calls: 0, error: null };
    const meta = { current_page: 1, last_page: 1, total: 1, per_page: 20 };

    it('renders rows with stable test ids', () => {
        wrapper = mount(Runs, { props: { runs: { data: [run], meta }, health: { last_run_at: null, status: 'success', age_hours: 1, failed_last_7d: 0 } } });
        expect(wrapper.get('[data-testid="scheduler-row-7"]').text()).toContain('hunter:sync');
        expect(wrapper.find('[data-testid="scheduler-health"]').exists()).toBe(true);
    });

    it('shows the loading state, then the empty state', async () => {
        let resolve!: (v: unknown) => void;
        mocked.get.mockReturnValue(new Promise((r) => (resolve = r)));
        wrapper = mount(Runs, { props: { runs: null, health: null } });
        expect(wrapper.find('[aria-busy="true"][role="status"]').exists()).toBe(true);
        resolve({ data: { data: [], meta: { ...meta, total: 0 } } });
        await flushPromises();
        expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(true);
    });

    it('shows the error state and retries', async () => {
        mocked.get.mockRejectedValueOnce({ isAxiosError: true, response: { status: 500, data: {} } });
        wrapper = mount(Runs, { props: { runs: null, health: null } });
        await flushPromises();
        expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true);
        mocked.get.mockResolvedValueOnce({ data: { data: [run], meta } });
        await wrapper.get('[data-testid="error-state"] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="scheduler-row-7"]').exists()).toBe(true);
    });

    it('opens the per-outlet drawer from GET /api/scheduler/runs/{id}', async () => {
        mocked.get.mockResolvedValue({ data: { ...run, outlets: [{ id: 1, outlet_id: 3, outlet: 'Toko Sinar Jaya', provider: 'osm', status: 'success', fetched_count: 10, new_count: 4, updated_count: 0, closed_count: 0, error: null, duration_ms: 500 }] } });
        wrapper = mount(Runs, { props: { runs: { data: [run], meta }, health: null }, attachTo: document.body });
        await wrapper.get('[data-testid="scheduler-detail-7"]').trigger('click');
        await flushPromises();
        expect(mocked.get).toHaveBeenCalledWith('/api/scheduler/runs/7');
        expect(document.body.textContent).toContain('Toko Sinar Jaya');
    });
});

describe('Dashboard page', () => {
    const props = {
        kpis: { outlets: 190, prospects_active: 9421, new_7d: 312, conversion_rate: 0.034, deals_this_month: 7 },
        funnel: { found: 9800, contacted: 900, demo: 120, won: 40, lost: 361 },
        penetration: [{ province_id: 31, province: 'DKI Jakarta', outlets: 5, prospects: 310, won: 3, penetration: 0.0097 }, { province_id: 11, province: 'Aceh', outlets: 5, prospects: 200, won: 1, penetration: 0.005 }],
        top_categories: [{ category: 'Kafe', count: 1200, avg_score: 58 }],
        whitespace_top: [{ cell_id: '-6.20:106.80', lat: -6.2, lng: 106.8, prospects: 42, nearest_outlet_m: 1200, province: 'DKI Jakarta' }],
        activity_by_user: [{ user_id: 1, name: 'Ayu', contacted: 14, demo: 3, won: 1 }],
        scheduler_health: { last_run_at: '2026-10-24T18:00:00Z', status: 'success', age_hours: 6.2, failed_last_7d: 0 },
        digest: { date: '2026-10-24', new_open: [{ id: 1, name: 'Kopi Senja', score: 78 }], hot: [], follow_up: [] },
    };

    it('renders the five KPIs (final value readable) and links', () => {
        wrapper = mount(Dashboard, { props });
        expect(wrapper.get('[data-testid="kpi-outlets"]').text()).toContain('190');
        expect(wrapper.get('[data-testid="kpi-prospects-active"]').text()).toContain('9.421');
        expect(wrapper.get('[data-testid="kpi-new-7d"]').text()).toContain('312');
        expect(wrapper.get('[data-testid="kpi-conversion"]').text()).toContain('3,4%');
        expect(wrapper.get('[data-testid="kpi-deals"]').text()).toContain('7');
        expect(wrapper.get('[data-testid="whitespace-row"]').attributes('href')).toBe('/?lat=-6.2&lng=106.8&mode=whitespace');
        expect(wrapper.get('[data-testid="digest-row"]').attributes('href')).toBe('/?prospect=1');
        expect(wrapper.get('[data-testid="export-csv"]').attributes('href')).toBe('/dashboard/export.csv');
    });

    it('sorts the cartogram tiles by the chosen metric', async () => {
        wrapper = mount(Dashboard, { props });
        const order = () => wrapper!.findAll('[data-testid^="cartogram-tile-"]').map((t) => t.attributes('data-testid'));
        expect(order()).toEqual(['cartogram-tile-31', 'cartogram-tile-11']);
        await wrapper.get('[data-testid="sort-name"]').trigger('click');
        expect(order()).toEqual(['cartogram-tile-11', 'cartogram-tile-31']);
    });

    it('loads another digest date from /api/digest', async () => {
        mocked.get.mockResolvedValue({ data: { date: '2026-10-20', new_open: [], hot: [], follow_up: [] } });
        wrapper = mount(Dashboard, { props });
        await wrapper.get('[data-testid="digest-date"]').setValue('2026-10-20');
        await wrapper.get('[data-testid="digest-date"]').trigger('change');
        await flushPromises();
        expect(mocked.get).toHaveBeenCalledWith('/api/digest', { params: { date: '2026-10-20' } });
        expect(wrapper.find('[data-testid="digest-row"]').exists()).toBe(false);
    });
});

describe('Settings page', () => {
    const settings = {
        contact: { warn_days: 7, min_interval_days: 14, max_overrides: 2 },
        digest: { email_to: null, hot_threshold: 75, follow_up_days: 7 },
        sync: { max_outlets_per_run: 200 },
        scoring: { params: {} },
        enrichment: { simulated: false },
        demo: { allow_simulated_whatsapp: false },
    };
    it('sends only changed scoring params and shows 422 messages', async () => {
        mocked.put.mockRejectedValueOnce({ isAxiosError: true, response: { status: 422, data: { message: 'x', errors: { 'contact.warn_days': ['Maks 90.'] } } } });
        wrapper = mount(Settings, { props: { settings } });
        await wrapper.get('[data-testid="setting-contact-warn_days"]').setValue('500');
        await wrapper.get('[data-testid="scoring-new_days"]').setValue('14');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        const body = mocked.put.mock.calls[0][1];
        expect(body.contact.warn_days).toBe(500);
        expect(body.scoring).toEqual({ params: { new_days: 14 } });
        expect(wrapper.text()).toContain('Maks 90.');
    });
});
