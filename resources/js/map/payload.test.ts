import { describe, expect, it } from 'vitest';
import { MAP_FLAG, type MapPointsResponse } from '@/types';
import { decodeFlags, indexOfId, interleave, isGridPayload, isPointsPayload, parseGrid, parseOutlets, parsePoints, parseWhitespace, pickByFlag, statusFromIndex } from './payload';

const points = {
    type: 'points' as const,
    count: 3,
    truncated: false,
    ids: [101, 102, 103],
    lng: [106.81, 106.82, 106.83],
    lat: [-6.21, -6.22, -6.23],
    score: [82, 47, 61],
    status: [0, 1, 4],
    flags: [MAP_FLAG.NEW_OPEN, 0, MAP_FLAG.SIMULATED | MAP_FLAG.NEW_OPEN],
};

describe('parsePoints', () => {
    it('converts the columnar payload to typed arrays with interleaved positions', () => {
        const d = parsePoints(points);
        expect(d.count).toBe(3);
        expect(d.ids).toBeInstanceOf(Int32Array);
        expect(d.positions).toBeInstanceOf(Float64Array);
        expect(Array.from(d.positions)).toEqual([106.81, -6.21, 106.82, -6.22, 106.83, -6.23]);
        expect(Array.from(d.scores)).toEqual([82, 47, 61]);
        expect(Array.from(d.status)).toEqual([0, 1, 4]);
        expect(d.truncated).toBe(false);
    });

    it('bounds the count by the shortest column so a corrupt payload cannot overrun', () => {
        const d = parsePoints({ ...points, score: [82, 47] });
        expect(d.count).toBe(2);
        expect(d.positions.length).toBe(4);
        expect(d.ids.length).toBe(2);
    });

    it('keeps the truncated flag', () => {
        expect(parsePoints({ ...points, truncated: true }).truncated).toBe(true);
    });

    it('handles an empty payload', () => {
        const d = parsePoints({ type: 'points', count: 0, truncated: false, ids: [], lng: [], lat: [], score: [], status: [], flags: [] });
        expect(d.count).toBe(0);
        expect(d.positions.length).toBe(0);
    });
});

describe('parseGrid', () => {
    it('uses the cell centre (origin + half the cell size) and sums the total', () => {
        const g = parseGrid({
            type: 'grid',
            cell_size_deg: 0.5,
            cells: [
                { lng: 106.5, lat: -6, count: 812, avg_score: 54.2, hot: 40 },
                { lng: 107, lat: -6.5, count: 8, avg_score: 30, hot: 0 },
            ],
        });
        expect(g.count).toBe(2);
        expect(g.total).toBe(820);
        expect(Array.from(g.positions)).toEqual([106.75, -5.75, 107.25, -6.25]);
        expect(Array.from(g.counts)).toEqual([812, 8]);
        expect(g.hots[0]).toBe(40);
        expect(g.avgScores[0]).toBeCloseTo(54.2, 3);
    });
});

describe('payload discrimination', () => {
    it('tells points from grid', () => {
        const grid: MapPointsResponse = { type: 'grid', cell_size_deg: 1, cells: [] };
        expect(isPointsPayload(points)).toBe(true);
        expect(isGridPayload(points)).toBe(false);
        expect(isGridPayload(grid)).toBe(true);
    });
});

describe('parseOutlets / parseWhitespace', () => {
    it('parses outlets', () => {
        const o = parseOutlets({ count: 2, ids: [1, 2], codes: ['A', 'B'], names: ['Satu', 'Dua'], lng: [106, 107], lat: [-6, -7], province_ids: [31, 32] });
        expect(o.count).toBe(2);
        expect(Array.from(o.positions)).toEqual([106, -6, 107, -7]);
        expect(o.names).toEqual(['Satu', 'Dua']);
        expect(Array.from(o.provinceIds)).toEqual([31, 32]);
    });

    it('parses white space cells and totals prospects', () => {
        const w = parseWhitespace({
            cell_size_m: 1000,
            cells: [
                { id: 'a', lng: 1, lat: 2, prospects: 20, avg_score: 50, nearest_outlet_m: 700 },
                { id: 'b', lng: 1, lat: 2, prospects: 15, avg_score: 40, nearest_outlet_m: 900 },
            ],
        });
        expect(w.count).toBe(2);
        expect(w.total).toBe(35);
        expect(parseWhitespace({ cell_size_m: 1000, cells: undefined as never }).count).toBe(0);
    });
});

describe('flags and lookups', () => {
    it('decodes the flag bitmask', () => {
        expect(decodeFlags(0)).toEqual({ newOpen: false, simulated: false, closed: false, optOut: false });
        expect(decodeFlags(MAP_FLAG.NEW_OPEN | MAP_FLAG.OPT_OUT)).toEqual({ newOpen: true, simulated: false, closed: false, optOut: true });
        expect(decodeFlags(15)).toEqual({ newOpen: true, simulated: true, closed: true, optOut: true });
    });

    it('maps status indexes to pipeline statuses with a safe default', () => {
        expect(statusFromIndex(0)).toBe('new');
        expect(statusFromIndex(3)).toBe('won');
        expect(statusFromIndex(99)).toBe('new');
    });

    it('extracts the positions of flagged points', () => {
        const d = parsePoints(points);
        const open = pickByFlag(d, MAP_FLAG.NEW_OPEN);
        expect(open.count).toBe(2);
        expect(Array.from(open.indices)).toEqual([0, 2]);
        expect(Array.from(open.positions)).toEqual([106.81, -6.21, 106.83, -6.23]);
        expect(pickByFlag(d, MAP_FLAG.CLOSED).count).toBe(0);
    });

    it('finds ids and interleaves columns', () => {
        expect(indexOfId(Int32Array.from([5, 6, 7]), 6)).toBe(1);
        expect(indexOfId(Int32Array.from([5, 6, 7]), 9)).toBe(-1);
        expect(Array.from(interleave([1, 2], [3, 4]))).toEqual([1, 3, 2, 4]);
    });
});
