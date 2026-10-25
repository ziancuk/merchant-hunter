import { afterEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import BarList from './BarList.vue';
import Cartogram, { type CartogramItem } from './Cartogram.vue';
import FunnelChart from './FunnelChart.vue';
import Sparkline from './Sparkline.vue';
import { provinceShort } from './provinceShort';

let wrapper: VueWrapper | null = null;
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

const items: CartogramItem[] = [
    { id: 1, label: 'DKI Jakarta', short: 'JKT', value: 0.02, details: ['Prospek: 310'] },
    { id: 2, label: 'Bali', short: 'BAL', value: 0.01 },
    { id: 3, label: 'Aceh', short: 'ACE', value: 0 },
];
const fmt = (n: number) => `${(n * 100).toFixed(1)}%`;

describe('Cartogram', () => {
    it('renders one accessible tile per item in the given order, with a table alternative', () => {
        wrapper = mount(Cartogram, { props: { items, metric: 'Penetrasi', format: fmt, label: 'Penetrasi per provinsi' } });
        const tiles = wrapper.findAll('button');
        expect(tiles.map((t) => t.text().slice(0, 7))).toEqual(['JKT2.0%', 'BAL1.0%', 'ACE0.0%']);
        // WCAG 2.5.3: the accessible name must contain the visible tile text.
        expect(tiles[0].text()).toContain('DKI Jakarta: Penetrasi 2.0%, Prospek: 310');
        expect(tiles[0].text()).toMatch(/^JKT2\.0%/);
        expect(wrapper.findAll('[data-testid="cartogram-table"] tbody tr')).toHaveLength(3);
    });

    it('marks only the top tier with the hot outline', () => {
        wrapper = mount(Cartogram, { props: { items, metric: 'Penetrasi', format: fmt, label: 'x' } });
        const [top, mid, zero] = wrapper.findAll('button');
        expect(top.classes()).toContain('border-hot');
        expect(mid.classes()).not.toContain('border-hot');
        expect(zero.classes()).not.toContain('border-hot');
    });

    it('shows a tooltip on hover/focus and hides it on leave', async () => {
        wrapper = mount(Cartogram, { props: { items, metric: 'Penetrasi', format: fmt, label: 'x' } });
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(false);
        await wrapper.findAll('button')[0].trigger('mouseenter');
        expect(wrapper.get('[role="tooltip"]').text()).toContain('DKI Jakarta');
        expect(wrapper.get('[role="tooltip"]').text()).toContain('Prospek: 310');
        await wrapper.get('[data-testid="cartogram"]').trigger('mouseleave');
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(false);
        await wrapper.findAll('button')[1].trigger('focus');
        expect(wrapper.get('[role="tooltip"]').text()).toContain('Bali');
    });

    it('emits select on click and copes with all-zero data', async () => {
        wrapper = mount(Cartogram, { props: { items: items.map((i) => ({ ...i, value: 0 })), metric: 'Penetrasi', format: fmt, label: 'x' } });
        await wrapper.findAll('button')[1].trigger('click');
        expect(wrapper.emitted('select')?.[0][0]).toMatchObject({ id: 2 });
        expect(wrapper.findAll('button.border-hot')).toHaveLength(0);
    });
});

describe('provinceShort', () => {
    it('maps known provinces and falls back to initials', () => {
        expect(provinceShort('DKI Jakarta')).toBe('JKT');
        expect(provinceShort('Provinsi Baru')).toBe('PRO');
    });
});

describe('Sparkline', () => {
    it('describes the series for screen readers', () => {
        wrapper = mount(Sparkline, { props: { values: [3, 9, 5], label: 'Prospek baru' } });
        expect(wrapper.get('svg').attributes('aria-label')).toBe('Prospek baru: 3 titik, terakhir 5, terendah 3, tertinggi 9');
        expect(wrapper.findAll('polyline')).toHaveLength(1);
    });

    it('handles empty and single-point series', () => {
        wrapper = mount(Sparkline, { props: { values: [], label: 'Kosong' } });
        expect(wrapper.get('svg').attributes('aria-label')).toBe('Kosong: tidak ada data');
        wrapper.unmount();
        wrapper = mount(Sparkline, { props: { values: [4], label: 'Satu' } });
        expect(wrapper.findAll('circle')).toHaveLength(1);
        expect(wrapper.findAll('polyline')).toHaveLength(0);
    });
});

describe('FunnelChart', () => {
    const stages = [
        { key: 'found', label: 'Ditemukan', value: 1000, color: 'red' },
        { key: 'contacted', label: 'Dihubungi', value: 250, color: 'red' },
        { key: 'won', label: 'Menang', value: 0, color: 'red' },
    ];
    it('shows conversion from the previous stage and a separate lost row', () => {
        wrapper = mount(FunnelChart, { props: { stages, lost: { label: 'Kalah', value: 40, color: 'pink' }, label: 'Corong' } });
        expect(wrapper.get('[data-testid="funnel-contacted"]').text()).toContain('25,0% dari tahap sebelumnya');
        expect(wrapper.get('[data-testid="funnel-won"]').text()).toContain('0,0%');
        expect(wrapper.get('[data-testid="funnel-lost"]').text()).toContain('40');
        expect(wrapper.findAll('ol > li')).toHaveLength(3);
    });
});

describe('BarList', () => {
    it('scales bars to the largest value', () => {
        wrapper = mount(BarList, { props: { label: 'Kategori', items: [{ label: 'Kafe', value: 200 }, { label: 'Toko', value: 50, hint: 'skor 40' }] } });
        const bars = wrapper.findAll('[data-testid="bar-item"]');
        expect(bars).toHaveLength(2);
        expect(bars[1].html()).toContain('width: 25%');
        expect(bars[1].text()).toContain('skor 40');
    });
});
