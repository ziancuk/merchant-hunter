import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import type { RoutePlan } from '@/types';
import MapHud from './MapHud.vue';
import MapLegend from './MapLegend.vue';
import ModeSwitcher from './ModeSwitcher.vue';
import RoutePanel from './RoutePanel.vue';
import type { RouteStop } from './types';

const stops: RouteStop[] = [
    { id: 1, name: 'Kopi Senja', lat: -6.2, lng: 106.8 },
    { id: 2, name: null, lat: -6.21, lng: 106.81 },
];

const plan: RoutePlan = {
    order: [1, 2],
    stops: [
        { id: 1, name: 'Kopi Senja', lat: -6.2, lng: 106.8, leg_km: 0 },
        { id: 2, name: 'Warung Dua', lat: -6.21, lng: 106.81, leg_km: 1.4 },
    ],
    total_km: 1.4,
    estimate_note: 'Estimasi garis lurus; jarak jalan sebenarnya lebih panjang.',
    path: [
        [106.8, -6.2],
        [106.81, -6.21],
    ],
    google_maps_urls: ['https://www.google.com/maps/dir/?api=1&origin=-6.2,106.8&destination=-6.21,106.81', 'javascript:alert(1)'],
};

function panel(over: Record<string, unknown> = {}) {
    return mount(RoutePanel, { props: { stops, plan: null, planning: false, error: '', picking: false, ...over } });
}

describe('RoutePanel', () => {
    it('shows the empty hint when nothing is selected', () => {
        const w = panel({ stops: [] });
        expect(w.find('[data-testid="route-empty"]').exists()).toBe(true);
        expect(w.get('[data-testid="route-plan"]').attributes('disabled')).toBeDefined();
    });

    it('lists the stops in order, with a placeholder for unnamed ones', () => {
        const w = panel();
        const rows = w.findAll('[data-testid="route-stop"]');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Kopi Senja');
        expect(rows[1].text()).toContain('Prospek #2');
        expect(w.get('[data-testid="route-count"]').text()).toBe('2/30');
    });

    it('disables planning below 2 stops and explains why', () => {
        const w = panel({ stops: [stops[0]] });
        expect(w.get('[data-testid="route-plan"]').attributes('disabled')).toBeDefined();
        expect(w.text()).toContain('Pilih minimal 2 prospek');
    });

    it('emits plan, clear, remove and the picking toggle', async () => {
        const w = panel();
        await w.get('[data-testid="route-plan"]').trigger('click');
        expect(w.emitted('plan')).toHaveLength(1);
        await w.get('[data-testid="route-clear"]').trigger('click');
        expect(w.emitted('clear')).toHaveLength(1);
        await w.get('[aria-label="Hapus Kopi Senja dari rute"]').trigger('click');
        expect(w.emitted('remove')?.[0]).toEqual([1]);
        await w.get('[data-testid="route-pick-toggle"]').setValue(true);
        expect(w.emitted('update:picking')?.[0]).toEqual([true]);
    });

    it('shows total distance, the straight-line note and only safe Google Maps links', () => {
        const w = panel({ plan });
        expect(w.get('[data-testid="route-total"]').text()).toContain('1,4 km');
        expect(w.get('[data-testid="route-note"]').text()).toContain('Estimasi garis lurus');
        const links = w.findAll('[data-testid="route-gmaps-link"]');
        expect(links).toHaveLength(1);
        expect(links[0].attributes('href')).toContain('https://www.google.com/maps/dir/');
        expect(links[0].attributes('rel')).toContain('noopener');
        expect(links[0].attributes('target')).toBe('_blank');
        expect(links[0].text()).toBe('Buka di Google Maps');
    });

    it('labels each part when the route needs several links', () => {
        const w = panel({ plan: { ...plan, google_maps_urls: ['https://www.google.com/maps/dir/?a=1', 'https://www.google.com/maps/dir/?a=2'] } });
        const links = w.findAll('[data-testid="route-gmaps-link"]');
        expect(links.map((l) => l.text())).toEqual(['Google Maps, bagian 1 dari 2', 'Google Maps, bagian 2 dari 2']);
    });

    it('surfaces planning errors as an alert', () => {
        const w = panel({ error: 'Minimal 2 prospek dengan koordinat diperlukan.' });
        expect(w.get('[role="alert"]').text()).toContain('Minimal 2 prospek');
    });

    it('shows the leg distance next to planned stops', () => {
        const w = panel({ plan });
        expect(w.findAll('[data-testid="route-stop"]')[1].text()).toContain('+1,4 km');
    });
});

describe('ModeSwitcher', () => {
    it('renders the four modes with stable test ids and marks the active one', async () => {
        const w = mount(ModeSwitcher, { props: { modelValue: 'prospects' } });
        for (const m of ['prospects', 'heatmap', 'whitespace', 'coverage']) expect(w.find(`[data-testid="map-mode-${m}"]`).exists()).toBe(true);
        expect(w.get('[data-testid="map-mode-prospects"]').attributes('aria-checked')).toBe('true');
        expect(w.get('[data-testid="map-mode-heatmap"]').attributes('aria-checked')).toBe('false');
        await w.get('[data-testid="map-mode-heatmap"]').trigger('click');
        expect(w.emitted('update:modelValue')?.[0]).toEqual(['heatmap']);
    });

    it('sits above the Filter/Rute row so its tooltips are not covered (B-12)', () => {
        const w = mount(ModeSwitcher, { props: { modelValue: 'prospects' } });
        expect(w.get('[data-testid="map-modes"]').classes()).toEqual(expect.arrayContaining(['relative', 'z-10']));
    });
});

describe('MapHud', () => {
    const hud = { outlets: 190, prospects_active: 14639, new_7d: 422, hot: 13 };

    it('shows the live count with a stable test id and the raw value', () => {
        const w = mount(MapHud, { props: { hud, count: 10334, mode: 'prospects', loading: false, truncated: false } });
        const c = w.get('[data-testid="hud-count"]');
        expect(c.text()).toBe('10.334');
        expect(c.attributes('data-count')).toBe('10334');
        expect(w.get('[data-testid="hud-outlets"]').text()).toBe('190');
        expect(w.get('[data-testid="hud-hot"]').text()).toBe('13');
    });

    it('shows a dash while the count is unknown and a truncation notice when capped', () => {
        const w = mount(MapHud, { props: { hud, count: null, mode: 'prospects', loading: true, truncated: true } });
        expect(w.get('[data-testid="hud-count"]').text()).toBe('–');
        expect(w.find('[data-testid="hud-truncated"]').exists()).toBe(true);
        expect(w.text()).toContain('memuat');
    });

    it('switches the unit label for white space', () => {
        const w = mount(MapHud, { props: { hud, count: 12, mode: 'whitespace', loading: false, truncated: false } });
        expect(w.text().toLowerCase()).toContain('sel white space');
    });

    it('renders only the pill in compact mode', () => {
        const w = mount(MapHud, { props: { hud, count: 5, mode: 'prospects', loading: false, truncated: false, compact: true } });
        expect(w.find('[data-testid="map-hud"]').exists()).toBe(false);
        expect(w.get('[data-testid="hud-count-compact"]').text()).toBe('5');
    });
});

describe('MapLegend', () => {
    it('lists score bands or statuses depending on the colouring and emits the toggle', async () => {
        const w = mount(MapLegend, { props: { colorBy: 'score' } });
        expect(w.text()).toContain('Panas');
        expect(w.text()).not.toContain('Dihubungi');
        await w.get('[data-testid="color-by-status"]').trigger('click');
        expect(w.emitted('update:colorBy')?.[0]).toEqual(['status']);
        await w.setProps({ colorBy: 'status' });
        expect(w.text()).toContain('Dihubungi');
        expect(w.text()).toContain('Baru buka');
        expect(w.text()).toContain('Data simulasi');
    });
});
