/* eslint-disable vue/one-component-per-file, vue/require-prop-types -- inline stubs for Inertia/AppShell */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick, reactive } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) }),
    router: { visit: vi.fn(), post: vi.fn() },
    usePage: () => ({ url: '/', props: {} }),
    useForm: (data: Record<string, unknown>) => reactive({ ...data, errors: {}, processing: false, post: vi.fn() }),
}));
vi.mock('@/Layouts/AppShell.vue', () => ({ default: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }) }));
vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api');
    return { ...actual, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

import { setLocale } from '@/i18n';
import { mockMatchMedia } from '@/lib/testing';
import { formatDuration } from '@/Components/master/duration';
import { validateTemplate } from '@/Components/master/template';
import { WEIGHT_FIELDS } from '@/Components/master/scoring';
import Login from './Auth/Login.vue';
import Dashboard from './Dashboard/Index.vue';
import Impact from './Impact/Index.vue';
import Settings from './Master/Settings.vue';
import Runs from './Scheduler/Runs.vue';

let wrapper: VueWrapper | null = null;
beforeEach(() => mockMatchMedia(true));
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    setLocale('id');
});

const defaults = {
    outlets: 190, google_syncs_per_month: 30, google_groups_per_outlet: 12, avg_pages: 1, details_ratio: 0,
    cost_per_1000_search: 32, cost_per_1000_details: 17, usd_idr: 16000, prospects_per_outlet: 75,
    conv_found_to_contact: 0.2, conv_contact_to_demo: 0.15, conv_demo_to_won: 0.25, deal_value_idr: 5000000,
    hours_saved_per_week: 10, hourly_cost_idr: 50000,
};
const results = { google_calls_per_month: 68400, google_cost_usd: 2188.8, google_cost_idr: 35020800, osm_cost_idr: 0, prospects_found: 14250, expected_deals: 106.88, expected_revenue_idr: 534375000, hours_saved_per_month: 43.3, hours_saved_value_idr: 2166667 };

describe('English rendering of the dashboard / impact / master / scheduler / auth pages', () => {
    it('Dashboard follows the language switch, including the renamed hot band', async () => {
        const props = {
            kpis: { outlets: 190, prospects_active: 9421, new_7d: 312, conversion_rate: 0.034, deals_this_month: 7 },
            funnel: { found: 9800, contacted: 900, demo: 120, won: 40, lost: 361 },
            penetration: [{ province_id: 31, province: 'DKI Jakarta', outlets: 5, prospects: 310, won: 3, penetration: 0.0097 }],
            top_categories: [{ category: 'Kafe', count: 1200, avg_score: 58 }],
            whitespace_top: [{ cell_id: '-6.20:106.80', lat: -6.2, lng: 106.8, prospects: 42, nearest_outlet_m: 1200, province: null }],
            activity_by_user: [{ user_id: 1, name: 'Ayu', contacted: 14, demo: 3, won: 1 }],
            scheduler_health: { last_run_at: '2026-10-24T18:00:00Z', status: 'success', age_hours: 6.2, failed_last_7d: 0 },
            digest: { date: '2026-10-24', new_open: [], hot: [], follow_up: [] },
        };
        wrapper = mount(Dashboard, { props });
        expect(wrapper.text()).toContain('Corong penjualan');
        expect(wrapper.text()).toContain('Bidik Utama');
        setLocale('en');
        await nextTick();
        const text = wrapper.text();
        expect(text).toContain('Sales funnel');
        expect(text).toContain('Active outlets');
        expect(text).toContain('Conversion rate');
        expect(text).toContain('Penetration by province');
        expect(text).toContain('Top white space areas');
        expect(text).toContain('No province');
        expect(text).toContain('nearest outlet 1.2 km');
        expect(text).toContain('14 contacted · 3 demo · 1 won');
        expect(text).toContain('6.2 h');
        expect(text).toContain('Prime Target');
        expect(text).not.toContain('Corong');
        await wrapper.get('[data-testid="digest-tab-hot"]').trigger('click');
        expect(wrapper.text()).toContain('No Prime Target prospects are waiting to be contacted.');
    });

    it('Impact page labels, units and result captions are English', async () => {
        setLocale('en');
        wrapper = mount(Impact, { props: { assumptions: defaults, defaults, results } });
        const text = wrapper.text();
        expect(text).toContain('Impact & cost');
        expect(text).toContain('Google Places calls');
        expect(text).toContain('Number of outlets');
        expect(text).toContain('Rates & exchange rate');
        expect(text).toContain('Estimated results');
        expect(text).toContain('Google calls / month');
        expect(text).toContain('≈ USD 2,188.80');
        expect(text).toContain('Changes are saved automatically');
        expect(text).toContain('estimate');
        expect(text).toContain('Restore defaults');
        expect(wrapper.get('[data-testid="result-google-calls"]').text()).toBe('68,400');
    });

    it('Scheduler history is English', () => {
        setLocale('en');
        const run = { id: 7, command: 'hunter:sync', status: 'partial', started_at: '2026-10-24T01:00:00Z', finished_at: null, duration_ms: 65_000, outlets_total: 190, outlets_ok: 188, outlets_failed: 2, prospects_new: 12, prospects_updated: 3, prospects_closed: 1, api_calls: 0, error: null };
        const meta = { current_page: 1, last_page: 1, total: 1, per_page: 20 };
        wrapper = mount(Runs, { props: { runs: { data: [run], meta }, health: { last_run_at: null, status: 'success', age_hours: 6.2, failed_last_7d: 2 } } });
        const row = wrapper.get('[data-testid="scheduler-row-7"]').text();
        expect(row).toContain('Partly failed');
        expect(row).toContain('1 min 5 s');
        expect(row).toContain('(2 failed)');
        expect(wrapper.get('[data-testid="scheduler-age"]').text()).toContain('6.2 h ago');
        expect(wrapper.get('[data-testid="scheduler-age"]').text()).toContain('2 failed runs');
        expect(wrapper.text()).toContain('Scheduler history');
        expect(wrapper.text()).toContain('Outlets ok/total');
    });

    it('Settings page uses the band label in the digest threshold', () => {
        setLocale('en');
        const settings = {
            contact: { warn_days: 7, min_interval_days: 14, max_overrides: 2 },
            digest: { email_to: null, hot_threshold: 75, follow_up_days: 7 },
            sync: { max_outlets_per_run: 200 },
            scoring: { params: {} },
            enrichment: { simulated: false },
            demo: { allow_simulated_whatsapp: false },
        };
        wrapper = mount(Settings, { props: { settings } });
        const text = wrapper.text();
        expect(text).toContain('Contact rules');
        expect(text).toContain('Prime Target score threshold');
        expect(text).toContain('Maximum points per component');
        expect(text).toContain('Category match');
        expect(text).toContain('default 25');
        expect(text).toContain('Save settings');
    });

    it('Login page shows the language switcher and English copy', async () => {
        wrapper = mount(Login);
        expect(wrapper.find('[data-testid="locale-switcher"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Masuk');
        await wrapper.get('[data-testid="locale-en"]').trigger('click');
        expect(wrapper.text()).toContain('Sign in to see the prospects on the radar.');
        expect(wrapper.text()).toContain('Password');
        expect(wrapper.text()).toContain('Remember me');
    });

    it('helper texts built in .ts files follow the locale', () => {
        expect(validateTemplate('')).toBe('Template wajib diisi.');
        expect(formatDuration(65_000)).toBe('1 mnt 5 dtk');
        expect(WEIGHT_FIELDS[0].label).toBe('Kecocokan kategori');
        setLocale('en');
        expect(validateTemplate('')).toBe('Template is required.');
        expect(validateTemplate('Halo saja')).toContain('at least one placeholder');
        expect(formatDuration(65_000)).toBe('1 min 5 s');
        expect(formatDuration(850)).toBe('0.9 s');
        expect(WEIGHT_FIELDS[0].label).toBe('Category match');
    });
});
