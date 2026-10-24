import { describe, expect, it } from 'vitest';
import { countActiveFilters, filterToParams, filterToQuery, queryToFilter, useFilters } from './useFilters';

describe('filter ⇄ query', () => {
    it('serialises arrays as key[] and drops empty values', () => {
        const q = filterToQuery({
            province_id: 31,
            city: '',
            category_id: [1, 2],
            pipeline_statuses: undefined,
            pipeline_status: [],
            score_min: 60,
            rating_min: undefined,
            new_open_only: 1,
        } as never);
        expect(q).toBe('category_id[]=1&category_id[]=2&new_open_only=1&province_id=31&score_min=60');
    });

    it('keeps flag value 0 and encodes text', () => {
        expect(filterToQuery({ has_phone: 0, city: 'Jakarta Pusat' })).toBe('city=Jakarta%20Pusat&has_phone=0');
    });

    it('builds params with array keys', () => {
        expect(filterToParams({ category_id: [3], score_min: 10 })).toEqual({ 'category_id[]': [3], score_min: 10 });
    });

    it('parses a query string back (round trip)', () => {
        const filter = { province_id: 31, category_id: [1, 2], pipeline_status: ['new', 'demo'] as ('new' | 'demo')[], score_min: 60, has_phone: 1 as const, city: 'Bandung' };
        expect(queryToFilter(filterToQuery(filter))).toEqual(filter);
    });

    it('ignores unknown keys, invalid numbers and invalid statuses', () => {
        expect(queryToFilter('?foo=1&score_min=abc&pipeline_status[]=hack&pipeline_status[]=won&has_phone=5&province_id=')).toEqual({ pipeline_status: ['won'] });
    });

    it('accepts non-bracketed array keys', () => {
        expect(queryToFilter('category_id=4&category_id=5').category_id).toEqual([4, 5]);
    });

    it('counts active filter groups', () => {
        expect(countActiveFilters({})).toBe(0);
        expect(countActiveFilters({ category_id: [1, 2], score_min: 5 })).toBe(2);
    });
});

describe('useFilters', () => {
    it('sets, resets and exposes the query', () => {
        const f = useFilters({ province_id: 31 });
        expect(f.query.value).toBe('province_id=31');
        f.set({ score_min: 70, province_id: undefined });
        expect(f.query.value).toBe('score_min=70');
        expect(f.activeCount.value).toBe(1);
        f.fromQuery('city=Medan');
        expect(f.filter.value).toEqual({ city: 'Medan' });
        f.reset();
        expect(f.query.value).toBe('');
    });
});
