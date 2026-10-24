import { describe, expect, it } from 'vitest';
import {
    buildCardsUrl,
    buildExportUrl,
    buildMapUrl,
    buildPageUrl,
    buildRouteUrl,
    buildTableUrl,
    insertSorted,
    mergeDetail,
    normalizeColumns,
    skipReasonLabel,
} from './pipeline';
import { cardFixture, prospectFixture } from '@/lib/testing';

describe('pipeline URL builders (filter → query)', () => {
    const filter = { category_id: [1, 2], score_min: 60, pipeline_status: ['new' as const], has_phone: 1 as const };

    it('builds the cards URL with filter, per_status and optional status/cursor', () => {
        expect(buildCardsUrl({})).toBe('/api/pipeline/cards?per_status=50');
        expect(buildCardsUrl(filter)).toBe('/api/pipeline/cards?category_id[]=1&category_id[]=2&has_phone=1&pipeline_status[]=new&score_min=60&per_status=50');
        expect(buildCardsUrl({ score_min: 10 }, { status: 'demo', cursor: '82:14' })).toBe('/api/pipeline/cards?score_min=10&status=demo&per_status=50&cursor=82%3A14');
    });

    it('builds the table URL with sort, dir, page and per_page', () => {
        expect(buildTableUrl({ city: 'Palu' }, { sort: 'name', dir: 'asc', page: 3, perPage: 50 })).toBe('/api/pipeline/table?city=Palu&sort=name&dir=asc&page=3&per_page=50');
    });

    it('uses the same filter for the CSV export', () => {
        expect(buildExportUrl({})).toBe('/prospects/export.csv');
        expect(buildExportUrl(filter)).toBe('/prospects/export.csv?category_id[]=1&category_id[]=2&has_phone=1&pipeline_status[]=new&score_min=60');
    });

    it('keeps view and filter in the page URL', () => {
        expect(buildPageUrl('table', { score_min: 5 })).toBe('/pipeline?view=table&score_min=5');
        expect(buildPageUrl('kanban', {})).toBe('/pipeline?view=kanban');
    });

    it('builds map and route links (route capped at 30)', () => {
        expect(buildMapUrl(7)).toBe('/?prospect=7');
        expect(buildRouteUrl([1, 2, 3])).toBe('/?route=1,2,3');
        expect(buildRouteUrl(Array.from({ length: 40 }, (_, i) => i + 1)).split(',')).toHaveLength(30);
    });
});

describe('pipeline helpers', () => {
    it('fills missing columns', () => {
        const c = normalizeColumns({ new: { total: 3, cards: [cardFixture()] } });
        expect(Object.keys(c)).toEqual(['new', 'contacted', 'demo', 'won', 'lost']);
        expect(c.lost).toEqual({ total: 0, cards: [], next_cursor: null });
        expect(c.new.next_cursor).toBeNull();
    });

    it('inserts by score desc then id desc and dedupes', () => {
        const a = cardFixture({ id: 1, score: 90 });
        const b = cardFixture({ id: 2, score: 50 });
        const result = insertSorted([a, b], cardFixture({ id: 3, score: 70 }));
        expect(result.map((c) => c.id)).toEqual([1, 3, 2]);
        expect(insertSorted(result, cardFixture({ id: 3, score: 70 })).map((c) => c.id)).toEqual([1, 3, 2]);
        expect(insertSorted(result, cardFixture({ id: 9, score: 10 })).map((c) => c.id)).toEqual([1, 3, 2, 9]);
    });

    it('merges detail onto a card but keeps its city', () => {
        const merged = mergeDetail(cardFixture({ city: 'Palu', handled_by: null }), prospectFixture({ handled_by: { id: 1, name: 'Ayu' }, do_not_contact: true, badges: ['opt_out'] }));
        expect(merged.city).toBe('Palu');
        expect(merged.handled_by).toBe('Ayu');
        expect(merged.do_not_contact).toBe(true);
        expect(merged.badges).toEqual(['opt_out']);
    });

    it('labels skip reasons in Indonesian and falls back to the raw code', () => {
        expect(skipReasonLabel('same_status')).toBe('Status sudah sama');
        expect(skipReasonLabel('whatever')).toBe('whatever');
    });
});
