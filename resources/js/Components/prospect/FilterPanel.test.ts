import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FilterPanel, { type FilterOptions } from './FilterPanel.vue';

const options: FilterOptions = {
    provinces: [{ id: 31, name: 'DKI Jakarta' }],
    cities: ['Jakarta Pusat'],
    categories: [
        { id: 1, key: 'cafe', name: 'Kafe' },
        { id: 2, key: 'resto', name: 'Restoran' },
    ],
    pipeline_statuses: ['new', 'contacted', 'demo', 'won', 'lost'],
};

const last = <T,>(w: ReturnType<typeof mount>, ev: string) => w.emitted(ev)!.at(-1)![0] as T;

describe('FilterPanel', () => {
    it('emits the filter and the query string when a checkbox changes', async () => {
        const w = mount(FilterPanel, { props: { modelValue: {}, options } });
        await w.get('[data-testid="f-newopen"]').setValue(true);
        expect(last(w, 'update:modelValue')).toEqual({ new_open_only: 1 });
        expect(last(w, 'query')).toBe('new_open_only=1');
    });

    it('toggles multi-select categories and statuses', async () => {
        const w = mount(FilterPanel, { props: { modelValue: { category_id: [1] }, options } });
        await w.get('[data-testid="f-cat-2"]').trigger('click');
        expect(last(w, 'update:modelValue')).toEqual({ category_id: [1, 2] });
        await w.get('[data-testid="f-cat-1"]').trigger('click');
        expect(last(w, 'update:modelValue')).toEqual({});
        await w.get('[data-testid="f-status-won"]').trigger('click');
        expect(last(w, 'query')).toBe('category_id[]=1&pipeline_status[]=won');
    });

    it('swaps a reversed score range and explains it (B-09)', async () => {
        const w = mount(FilterPanel, { props: { modelValue: { score_min: 90 }, options } });
        await w.get('[data-testid="f-score-max"]').setValue('10');
        expect(last(w, 'update:modelValue')).toEqual({ score_min: 10, score_max: 90 });
        expect(last(w, 'query')).toBe('score_max=90&score_min=10');
        expect(w.get('[data-testid="f-score-swapped"]').text()).toContain('ditukar otomatis');
    });

    it('emits numeric selections and drops cleared ones', async () => {
        const w = mount(FilterPanel, { props: { modelValue: { score_min: 60 }, options } });
        await w.get('[data-testid="f-province"]').setValue('31');
        expect(last(w, 'update:modelValue')).toEqual({ score_min: 60, province_id: 31 });
        await w.get('[data-testid="f-score-min"]').setValue('');
        expect(last(w, 'update:modelValue')).toEqual({});
        await w.get('[data-testid="f-distance"]').setValue('1000');
        expect(last(w, 'query')).toBe('max_distance_m=1000&score_min=60');
    });

    it('shows the active count and resets', async () => {
        const w = mount(FilterPanel, { props: { modelValue: { score_min: 60, has_phone: 1 }, options } });
        expect(w.text()).toContain('2');
        await w.get('[data-testid="filter-reset"]').trigger('click');
        expect(last(w, 'update:modelValue')).toEqual({});
        expect(last(w, 'query')).toBe('');
    });

    it('disables reset when nothing is active', () => {
        const w = mount(FilterPanel, { props: { modelValue: {}, options } });
        expect(w.get('[data-testid="filter-reset"]').attributes('disabled')).toBeDefined();
    });
});
