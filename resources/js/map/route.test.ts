import { describe, expect, it } from 'vitest';
import type { RoutePlan } from '@/types';
import { addStop, applyPlan, canPlan, isSafeMapsUrl, mapsLinkLabel, moveStop, planBlocker, planBody, planBounds, removeStop, toggleStop } from './route';
import type { RouteStop } from './types';

const stop = (id: number, name: string | null = `P${id}`): RouteStop => ({ id, name, lat: -6 - id / 100, lng: 106 + id / 100 });
const many = (n: number) => Array.from({ length: n }, (_, i) => stop(i + 1));

describe('addStop / removeStop / toggleStop', () => {
    it('adds without mutating the input', () => {
        const list = [stop(1)];
        const r = addStop(list, stop(2));
        expect(r.added).toBe(true);
        expect(r.list.map((s) => s.id)).toEqual([1, 2]);
        expect(list).toHaveLength(1);
    });

    it('refuses duplicates and the 31st stop', () => {
        expect(addStop([stop(1)], stop(1))).toMatchObject({ added: false, reason: 'duplicate' });
        const full = many(30);
        const r = addStop(full, stop(99));
        expect(r).toMatchObject({ added: false, reason: 'limit' });
        expect(r.list).toHaveLength(30);
    });

    it('removes by id', () => {
        expect(removeStop([stop(1), stop(2), stop(3)], 2).map((s) => s.id)).toEqual([1, 3]);
    });

    it('toggle adds then removes', () => {
        const a = toggleStop([], stop(4));
        expect(a.added).toBe(true);
        const b = toggleStop(a.list, stop(4));
        expect(b.removed).toBe(true);
        expect(b.list).toHaveLength(0);
    });
});

describe('moveStop', () => {
    it('reorders and ignores out-of-range moves', () => {
        const l = [stop(1), stop(2), stop(3)];
        expect(moveStop(l, 2, 0).map((s) => s.id)).toEqual([3, 1, 2]);
        expect(moveStop(l, 0, 1).map((s) => s.id)).toEqual([2, 1, 3]);
        expect(moveStop(l, 0, 5).map((s) => s.id)).toEqual([1, 2, 3]);
        expect(moveStop(l, -1, 1).map((s) => s.id)).toEqual([1, 2, 3]);
    });
});

describe('planning rules (2..30 prospects)', () => {
    it('requires between 2 and 30 stops', () => {
        expect(canPlan(many(1))).toBe(false);
        expect(canPlan(many(2))).toBe(true);
        expect(canPlan(many(30))).toBe(true);
        expect(canPlan(many(31))).toBe(false);
        expect(planBlocker(many(1))).toMatch(/minimal 2/i);
        expect(planBlocker(many(31))).toMatch(/maksimal 30/i);
        expect(planBlocker(many(5))).toBeNull();
    });

    it('builds the POST body from the selection order', () => {
        expect(planBody([stop(7), stop(3)])).toEqual({ prospect_ids: [7, 3] });
    });
});

const plan: RoutePlan = {
    order: [101, 105, 102],
    stops: [
        { id: 101, name: 'A', lat: -6.2, lng: 106.8, leg_km: 0 },
        { id: 105, name: 'C', lat: -6.21, lng: 106.81, leg_km: 1.4 },
        { id: 102, name: 'B', lat: -6.23, lng: 106.83, leg_km: 3.8 },
    ],
    total_km: 5.2,
    estimate_note: 'Estimasi garis lurus',
    path: [
        [106.8, -6.2],
        [106.81, -6.21],
        [106.83, -6.23],
    ],
    google_maps_urls: ['https://www.google.com/maps/dir/?api=1&origin=-6.2,106.8&destination=-6.23,106.83&waypoints=-6.21,106.81'],
};

describe('applyPlan / planBounds', () => {
    it('re-orders the selection to the planned order and fills coordinates', () => {
        const sel = [stop(102, null), stop(101), stop(105)];
        const out = applyPlan(sel, plan);
        expect(out.map((s) => s.id)).toEqual([101, 105, 102]);
        expect(out[2].name).toBe('B');
        expect(out[0].lat).toBe(-6.2);
    });

    it('keeps a known name when the server omits it', () => {
        const out = applyPlan([stop(101, 'Lokal')], { ...plan, stops: [{ id: 101, name: undefined as never, lat: 1, lng: 2, leg_km: 0 }] });
        expect(out[0].name).toBe('Lokal');
    });

    it('computes the bounds of the path, falling back to the stops', () => {
        expect(planBounds(plan)).toEqual([106.8, -6.23, 106.83, -6.2]);
        expect(planBounds({ path: [], stops: plan.stops })).toEqual([106.8, -6.23, 106.83, -6.2]);
        expect(planBounds({ path: [], stops: [] })).toBeNull();
    });
});

describe('Google Maps links', () => {
    it('labels single and multi-part routes', () => {
        expect(mapsLinkLabel(0, 1)).toBe('Buka di Google Maps');
        expect(mapsLinkLabel(1, 3)).toBe('Google Maps, bagian 2 dari 3');
    });

    it('only trusts https links to Google Maps', () => {
        expect(isSafeMapsUrl(plan.google_maps_urls[0])).toBe(true);
        expect(isSafeMapsUrl('javascript:alert(1)')).toBe(false);
        expect(isSafeMapsUrl('http://www.google.com/maps/dir/')).toBe(false);
        expect(isSafeMapsUrl('https://evil.example/maps')).toBe(false);
        expect(isSafeMapsUrl('not a url')).toBe(false);
    });
});
