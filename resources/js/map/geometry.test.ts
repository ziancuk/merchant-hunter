import { describe, expect, it, vi } from 'vitest';
import { boundsOfPositions, drawerPadding, haversine, introTargetZoom, nearestIndex, whitespacePolygon } from './geometry';
import { kindOfLayer, toMapPick } from './picking';
import { searchToItems } from './search';

describe('whitespacePolygon', () => {
    it('is a closed square of ~0.01 degrees around the centre', () => {
        const ring = whitespacePolygon({ lng: 106.8, lat: -6.2 });
        expect(ring).toHaveLength(5);
        expect(ring[0]).toEqual(ring[4]);
        expect(ring[0][0]).toBeCloseTo(106.795, 9);
        expect(ring[0][1]).toBeCloseTo(-6.205, 9);
        expect(ring[2][0]).toBeCloseTo(106.805, 9);
        expect(ring[2][1]).toBeCloseTo(-6.195, 9);
    });
});

describe('boundsOfPositions', () => {
    it('returns [w,s,e,n] or null', () => {
        expect(boundsOfPositions(Float64Array.from([1, 2, 5, -3, 3, 8]))).toEqual([1, -3, 5, 8]);
        expect(boundsOfPositions(new Float64Array(0))).toBeNull();
    });
});

describe('haversine / nearestIndex', () => {
    it('knows 1 degree of latitude is ~111 km', () => {
        expect(haversine(0, 0, 1, 0)).toBeGreaterThan(110_000);
        expect(haversine(0, 0, 1, 0)).toBeLessThan(112_000);
        expect(haversine(-6.2, 106.8, -6.2, 106.8)).toBe(0);
    });

    it('finds the closest point within the radius', () => {
        const pos = Float64Array.from([106.8, -6.2, 106.81, -6.2, 107, -6]);
        expect(nearestIndex(pos, 3, 106.8101, -6.2, 500)).toBe(1);
        expect(nearestIndex(pos, 3, 120, 0, 500)).toBe(-1);
    });
});

describe('drawerPadding', () => {
    it('reserves room for the drawer on wide screens only', () => {
        expect(drawerPadding(1440, true).right).toBe(480);
        expect(drawerPadding(1000, true).right).toBe(450);
        expect(drawerPadding(375, true).right).toBe(0);
        expect(drawerPadding(1440, false).right).toBe(0);
    });
});

describe('introTargetZoom', () => {
    it('fits Indonesia on any width and stays within bounds', () => {
        expect(introTargetZoom(1440)).toBeGreaterThan(introTargetZoom(375));
        expect(introTargetZoom(100)).toBe(2.2);
        expect(introTargetZoom(99999)).toBe(5.4);
        // at the chosen zoom ~46 degrees of longitude occupy at most the viewport width
        const z = introTargetZoom(1440);
        expect((512 * 2 ** z * 46) / 360).toBeLessThanOrEqual(1440);
    });
});

describe('picking', () => {
    it('routes layer ids to object kinds', () => {
        expect(kindOfLayer('prospect-columns')).toBe('prospect');
        expect(kindOfLayer('prospect-discs')).toBe('prospect');
        expect(kindOfLayer('outlet-core')).toBe('outlet');
        expect(kindOfLayer('grid-bubbles')).toBe('grid');
        expect(kindOfLayer('whitespace-dots')).toBe('whitespace');
        expect(kindOfLayer('outlet-halo')).toBeNull();
    });

    it('ignores empty or non-interactive picks', () => {
        expect(toMapPick(null)).toBeNull();
        expect(toMapPick({ layerId: 'route-path', index: 0, x: 1, y: 2 })).toBeNull();
        expect(toMapPick({ layerId: 'prospect-columns', index: -1, x: 1, y: 2 })).toBeNull();
        expect(toMapPick({ layerId: 'outlet-core', index: 4, x: 1, y: 2 })).toEqual({ kind: 'outlet', index: 4, x: 1, y: 2 });
    });
});

describe('searchToItems', () => {
    const handlers = { onOutlet: vi.fn(), onProspect: vi.fn(), onProvince: vi.fn() };
    const res = {
        outlets: [{ id: 1, name: 'Toko Sinar Jaya', city: 'Bandung', lng: 107.6, lat: -6.9 }],
        prospects: [{ id: 5, name: 'Kopi Senja', category: 'Kafe', city: 'Jakarta', score: 71, lng: 106.8, lat: -6.2 }],
        provinces: [{ id: 31, name: 'DKI Jakarta', lng: 106.8, lat: -6.2 }],
    };

    it('maps the three groups into palette items in order province, outlet, prospect', () => {
        const items = searchToItems(res, handlers);
        expect(items.map((i) => i.group)).toEqual(['Provinsi', 'Outlet', 'Prospek']);
        expect(items[2].hint).toBe('Kafe · Jakarta · skor 71');
        expect(items[1].id).toBe('outlet:1');
    });

    it('wires each item to its handler', () => {
        const [province, outlet, prospect] = searchToItems(res, handlers);
        province.action?.();
        outlet.action?.();
        prospect.action?.();
        expect(handlers.onProvince).toHaveBeenCalledWith(res.provinces[0]);
        expect(handlers.onOutlet).toHaveBeenCalledWith(res.outlets[0]);
        expect(handlers.onProspect).toHaveBeenCalledWith(res.prospects[0]);
    });

    it('survives empty or missing groups', () => {
        expect(searchToItems(null, handlers)).toEqual([]);
        expect(searchToItems({ outlets: [], prospects: [], provinces: [] }, handlers)).toEqual([]);
        expect(searchToItems({}, handlers)).toEqual([]);
    });

    it('caps each group at 8 results', () => {
        const prospects = Array.from({ length: 20 }, (_, i) => ({ id: i, name: `P${i}`, category: 'Kafe', city: null, score: 50, lng: 0, lat: 0 }));
        expect(searchToItems({ prospects }, handlers)).toHaveLength(8);
    });
});
