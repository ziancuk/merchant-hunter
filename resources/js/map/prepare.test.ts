import { describe, expect, it } from 'vitest';
import { MAP_FLAG } from '@/types';
import { parseGrid, parsePoints } from './payload';
import { gridWeights, prepareGrid, preparePoints, recolor } from './prepare';

const data = parsePoints({
    type: 'points',
    count: 3,
    truncated: false,
    ids: [1, 2, 3],
    lng: [106, 107, 108],
    lat: [-6, -7, -8],
    score: [90, 50, 20],
    status: [0, 1, 2],
    flags: [MAP_FLAG.NEW_OPEN, MAP_FLAG.SIMULATED, MAP_FLAG.NEW_OPEN | MAP_FLAG.SIMULATED],
});

describe('preparePoints', () => {
    it('derives colours, extrusion, weights and the flagged subsets in one pass', () => {
        const p = preparePoints(data, 'score');
        expect(p.colors.length).toBe(12);
        expect(p.elevations.length).toBe(3);
        expect(p.weights.length).toBe(3);
        expect(Array.from(p.newOpen)).toEqual([106, -6, 108, -8]);
        expect(Array.from(p.simulated)).toEqual([107, -7, 108, -8]);
        expect(p.count).toBe(3);
    });

    it('recolour only changes the colour array', () => {
        const p = preparePoints(data, 'score');
        const q = recolor(p, 'status');
        expect(q.colors).not.toEqual(p.colors);
        expect(q.positions).toBe(p.positions);
        expect(q.elevations).toBe(p.elevations);
    });
});

describe('prepareGrid', () => {
    const grid = parseGrid({
        type: 'grid',
        cell_size_deg: 1,
        cells: [
            { lng: 100, lat: 0, count: 400, avg_score: 50, hot: 2 },
            { lng: 101, lat: 0, count: 100, avg_score: 30, hot: 0 },
        ],
    });

    it('sizes bubbles by count and colours hot cells', () => {
        const g = prepareGrid(grid);
        expect(g.radii[0]).toBeCloseTo(32, 5);
        expect(g.radii[1]).toBeCloseTo(6 + 26 * 0.5, 5);
        expect(g.colors.length).toBe(8);
        expect(g.weights[0]).toBe(20);
    });

    it('square-roots the heat weights', () => {
        expect(Array.from(gridWeights(Uint32Array.from([4, 9])))).toEqual([2, 3]);
    });
});
