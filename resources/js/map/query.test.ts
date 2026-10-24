import { describe, expect, it } from 'vitest';
import { bboxToParam, buildProspectsUrl, buildSearchUrl, buildWhitespaceUrl, clampBbox, isGridZoom, prospectLink, routeLink, zoomParam } from './query';

describe('bbox helpers', () => {
    it('clamps to valid extents, orders corners and rounds to 4 decimals', () => {
        expect(clampBbox([-200, -90, 200, 90])).toEqual([-180, -85, 180, 85]);
        expect(clampBbox([107, -6, 106, -7])).toEqual([106, -7, 107, -6]);
        expect(clampBbox([106.123456, -6.987654, 107.5, -5])).toEqual([106.1235, -6.9877, 107.5, -5]);
    });

    it('formats west,south,east,north', () => {
        expect(bboxToParam([106.5, -6.3, 107, -6])).toBe('106.5,-6.3,107,-6');
    });
});

describe('zoomParam', () => {
    it('floors to an integer within 0..22', () => {
        expect(zoomParam(5.9)).toBe(5);
        expect(zoomParam(-3)).toBe(0);
        expect(zoomParam(30)).toBe(22);
        expect(zoomParam(Number.NaN)).toBe(0);
    });

    it('detects the grid threshold (zoom < 6)', () => {
        expect(isGridZoom(5.99)).toBe(true);
        expect(isGridZoom(6)).toBe(false);
    });
});

describe('buildProspectsUrl', () => {
    const bbox: [number, number, number, number] = [106.6, -6.4, 107, -6];

    it('builds the viewport query without filters', () => {
        expect(buildProspectsUrl({ bbox, zoom: 11.7, mode: 'prospects', filter: {} })).toBe('/api/map/prospects?bbox=106.6,-6.4,107,-6&zoom=11&mode=prospects');
    });

    it('maps modes: only heatmap changes the mode param', () => {
        expect(buildProspectsUrl({ bbox, zoom: 8, mode: 'heatmap', filter: {} })).toContain('mode=heatmap');
        expect(buildProspectsUrl({ bbox, zoom: 8, mode: 'coverage', filter: {} })).toContain('mode=prospects');
        expect(buildProspectsUrl({ bbox, zoom: 8, mode: 'whitespace', filter: {} })).toContain('mode=prospects');
    });

    it('appends every active filter using the array form for lists', () => {
        const url = buildProspectsUrl({
            bbox,
            zoom: 9,
            mode: 'prospects',
            filter: { province_id: 31, category_id: [1, 3], score_min: 60, has_phone: 1, pipeline_status: ['new', 'demo'], new_open_only: 1, max_distance_m: 1000, rating_min: 4 },
        });
        expect(url).toContain('province_id=31');
        expect(url).toContain('category_id[]=1&category_id[]=3');
        expect(url).toContain('score_min=60');
        expect(url).toContain('has_phone=1');
        expect(url).toContain('pipeline_status[]=new&pipeline_status[]=demo');
        expect(url).toContain('new_open_only=1');
        expect(url).toContain('max_distance_m=1000');
        expect(url).toContain('rating_min=4');
    });

    it('encodes free text such as the city', () => {
        expect(buildProspectsUrl({ bbox, zoom: 9, mode: 'prospects', filter: { city: 'Jakarta Pusat & Utara' } })).toContain('city=Jakarta%20Pusat%20%26%20Utara');
    });

    it('drops empty filter values', () => {
        const url = buildProspectsUrl({ bbox, zoom: 9, mode: 'prospects', filter: { category_id: [], city: '' } });
        expect(url).not.toContain('category_id');
        expect(url).not.toContain('city');
    });
});

describe('other builders', () => {
    it('whitespace only carries the category filter', () => {
        const url = buildWhitespaceUrl({ bbox: [95, -11, 141, 6], filter: { category_id: [2, 5], score_min: 70, province_id: 3 } });
        expect(url).toBe('/api/map/whitespace?bbox=95,-11,141,6&category_id[]=2&category_id[]=5');
        expect(buildWhitespaceUrl({ bbox: [95, -11, 141, 6], filter: {} })).toBe('/api/map/whitespace?bbox=95,-11,141,6');
    });

    it('search url trims and encodes', () => {
        expect(buildSearchUrl('  kopi senja ')).toBe('/api/search?q=kopi%20senja');
    });

    it('deep links', () => {
        expect(prospectLink(12)).toBe('/?prospect=12');
        expect(routeLink([1, 5, 9])).toBe('/?route=1,5,9');
    });
});
