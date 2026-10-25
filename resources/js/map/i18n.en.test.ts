import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { setLocale } from '@/i18n';
import type { RoutePlan } from '@/types';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import ProspectBadges from '@/Components/prospect/ProspectBadges.vue';
import ProspectCard from '@/Components/prospect/ProspectCard.vue';
import ProspectDrawer from '@/Components/prospect/ProspectDrawer.vue';
import ScoreBreakdown from '@/Components/prospect/ScoreBreakdown.vue';
import SimulasiBadge from '@/Components/prospect/SimulasiBadge.vue';
import WhatsAppButton from '@/Components/prospect/WhatsAppButton.vue';
import { cardFixture, mockMatchMedia, prospectFixture } from '@/lib/testing';
import MapHud from './MapHud.vue';
import MapLegend from './MapLegend.vue';
import MapOverlays from './MapOverlays.vue';
import ModeSwitcher from './ModeSwitcher.vue';
import RoutePanel from './RoutePanel.vue';
import { buildTooltip } from './useMapTooltip';
import { mapsLinkLabel, planBlocker } from './route';
import { searchToItems } from './search';

beforeEach(() => {
    mockMatchMedia(false);
    setLocale('en');
});
afterEach(() => setLocale('id'));

const plan: RoutePlan = {
    order: [1, 2],
    stops: [
        { id: 1, name: 'Kopi Senja', lat: -6.2, lng: 106.8, leg_km: 0 },
        { id: 2, name: 'Warung Dua', lat: -6.21, lng: 106.81, leg_km: 1.4 },
    ],
    total_km: 1.4,
    estimate_note: 'note',
    path: [],
    google_maps_urls: ['https://www.google.com/maps/dir/?api=1&origin=1,1&destination=2,2'],
};

describe('map chrome in English', () => {
    it('MapHud uses the band label for the hot count', () => {
        const w = mount(MapHud, { props: { hud: { outlets: 3, prospects_active: 10, new_7d: 2, hot: 4 }, count: 5, mode: 'prospects', loading: false, truncated: true } });
        const text = w.text();
        expect(text).toContain('prospects shown');
        expect(text).toContain('Active prospects');
        expect(text).toContain('New in 7 days');
        expect(text).toContain('Prime Target');
        expect(text).toContain('Limited to 20,000 points');
        expect(w.get('[data-testid="map-hud"]').attributes('aria-label')).toBe('Map summary');
    });

    it('ModeSwitcher and MapLegend follow the language', async () => {
        const modes = mount(ModeSwitcher, { props: { modelValue: 'prospects' }, attachTo: document.body });
        expect(modes.get('[data-testid="map-mode-coverage"]').text()).toBe('Coverage');
        expect(modes.get('[data-testid="map-mode-whitespace"]').text()).toBe('White space');
        expect(modes.get('[data-testid="map-modes"]').attributes('aria-label')).toBe('Map mode');
        modes.unmount();
        const legend = mount(MapLegend, { props: { colorBy: 'score' } });
        expect(legend.text()).toContain('Colour by');
        expect(legend.text()).toContain('Prime Target');
        expect(legend.text()).toContain('Newly opened');
        expect(legend.text()).toContain('Simulated data');
        setLocale('id');
        await flushPromises();
        expect(legend.text()).toContain('Warna menurut');
        expect(legend.text()).toContain('Bidik Utama');
    });

    it('RoutePanel is translated, including planning hints', () => {
        const stops = [
            { id: 1, name: 'Kopi Senja', lat: 1, lng: 1 },
            { id: 2, name: null, lat: 1, lng: 1 },
        ];
        const w = mount(RoutePanel, { props: { stops, plan, planning: false, error: '', picking: false } });
        const text = w.text();
        expect(text).toContain('Visit route');
        expect(text).toContain('Prospect #2');
        expect(text).toContain('2 stops');
        expect(text).toContain('Recalculate');
        expect(text).toContain('Open in Google Maps');
        expect(w.find('[aria-label="Move Kopi Senja down"]').exists()).toBe(true);
        const empty = mount(RoutePanel, { props: { stops: [], plan: null, planning: false, error: '', picking: false } });
        expect(empty.get('[data-testid="route-empty"]').text()).toContain('No prospects yet');
        expect(planBlocker([stops[0]])).toBe('Pick at least 2 prospects.');
        expect(mapsLinkLabel(0, 3)).toBe('Google Maps, part 1 of 3');
    });

    it('MapOverlays shows English empty and error states', () => {
        const base = { ready: true, mapError: '', basemapFailed: true, tooltip: null, tooltipStyle: {}, loadError: '', loading: false, isEmpty: true, introActive: true, mode: 'whitespace', activeCount: 2, liveSummary: '' } as const;
        const text = mount(MapOverlays, { props: base }).text();
        expect(text).toContain('Basemap unavailable');
        expect(text).toContain('No white space areas in this view');
        expect(text).toContain('Pan or zoom out, or loosen the filters.');
        expect(text).toContain('Reset filters');
        expect(text).toContain('Skip intro');
    });

    it('buildTooltip and search items are translated', () => {
        const points = { count: 1, ids: new Uint32Array([7]), scores: new Uint8Array([90]), status: new Uint8Array([0]), flags: new Uint8Array([1]), positions: new Float32Array([0, 0]) };
        const tip = buildTooltip({ kind: 'prospect', index: 0, x: 0, y: 0 }, { points: points as never, outlets: null, grid: null, whitespace: null, meta: null, routePicking: false });
        expect(tip?.title).toBe('Prospect #7');
        expect(tip?.lines.join(' | ')).toContain('Score 90 (Prime Target) · New');
        expect(tip?.lines.at(-1)).toBe('Click for details');
        const items = searchToItems({ provinces: [{ id: 1, name: 'Bali', lng: 0, lat: 0 }], prospects: [{ id: 2, name: 'X', category: 'Cafe', city: null, score: 80, lng: 0, lat: 0 }] }, { onOutlet: () => {}, onProspect: () => {}, onProvince: () => {} });
        expect(items[0]).toMatchObject({ hint: 'Province', group: 'Province' });
        expect(items[1]).toMatchObject({ hint: 'Cafe · score 80', group: 'Prospect' });
    });
});

describe('prospect components in English', () => {
    const options: FilterOptions = { provinces: [], cities: [], categories: [], pipeline_statuses: ['new', 'won', 'lost'] };

    it('FilterPanel labels, statuses and the swapped-range note', async () => {
        const w = mount(FilterPanel, { props: { modelValue: { score_min: 90 }, options } });
        const text = w.text();
        expect(text).toContain('Province');
        expect(text).toContain('Min score');
        expect(text).toContain('Pipeline status');
        expect(text).toContain('Won');
        expect(text).toContain('Show closed businesses');
        expect(w.get('section').attributes('aria-label')).toBe('Prospect filters');
        await w.get('[data-testid="f-score-max"]').setValue('10');
        expect(w.get('[data-testid="f-score-swapped"]').text()).toContain('values were swapped');
    });

    it('badges, simulated badge, score breakdown, card and WhatsApp reason', async () => {
        expect(mount(ProspectBadges, { props: { badges: ['new_open', 'closed', 'opt_out'] } }).text()).toBe('Newly openedClosedDo not contact');
        const sim = mount(SimulasiBadge, { props: { field: 'Phone' } });
        expect(sim.text()).toBe('Simulated');
        expect(sim.get('[data-testid="simulasi-badge"]').attributes('aria-label')).toBe('Phone is simulated data');
        expect(mount(ScoreBreakdown, { props: { components: [] } }).text()).toBe('Score breakdown not available yet.');
        const card = mount(ProspectCard, { props: { prospect: cardFixture({ phone_present: false }) } });
        expect(card.get('[data-testid="no-phone"]').text()).toBe('No phone');
        expect(card.get('[role="img"]').attributes('aria-label')).toMatch(/^Score \d+ out of 100, /);
        const wa = mount(WhatsAppButton, { props: { phone: null, whatsappUrl: null } });
        expect(wa.get('[data-testid="wa-reason"]').text()).toBe('No phone number');
    });

    it('ProspectDrawer renders English chrome', async () => {
        const w = mount(ProspectDrawer, { props: { prospectId: 101, loader: async () => prospectFixture(), rejectionReasons: [] }, attachTo: document.body });
        await flushPromises();
        const text = w.get('[data-testid="drawer-content"]').text();
        expect(text).toContain('Score breakdown');
        expect(text).toContain('Location');
        expect(text).toContain('Contact & reputation');
        expect(text).toContain('Pipeline status');
        expect(text).toContain('Notes');
        expect(text).toContain('Activity');
        expect(text).toContain('Show on map');
        expect(text).toContain('First seen');
        w.unmount();
    });
});
