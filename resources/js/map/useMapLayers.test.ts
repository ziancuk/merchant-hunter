import { describe, expect, it } from 'vitest';
import { pulseWanted, PULSE_INTERVAL_MS } from './useMapLayers';
import { buildTooltip, tooltipPosition, unwrapDetail } from './useMapTooltip';
import { debugApiEnabled } from './useMapDebugApi';
import { cut } from './payload';
import { labelledStops, single } from './layers';

const base = { motion: true, introActive: false, mode: 'prospects' as const, newOpenCount: 3, visible: true };

describe('pulseWanted', () => {
    it('runs only with motion, a visible tab, prospect/coverage mode and new-open points', () => {
        expect(pulseWanted(base)).toBe(true);
        expect(pulseWanted({ ...base, mode: 'coverage' })).toBe(true);
        expect(pulseWanted({ ...base, motion: false })).toBe(false);
        expect(pulseWanted({ ...base, introActive: true })).toBe(false);
        expect(pulseWanted({ ...base, visible: false })).toBe(false);
        expect(pulseWanted({ ...base, mode: 'heatmap' })).toBe(false);
        expect(pulseWanted({ ...base, mode: 'whitespace' })).toBe(false);
        expect(pulseWanted({ ...base, newOpenCount: 0 })).toBe(false);
    });
    it('is throttled to roughly 12 fps', () => {
        expect(Math.round(1000 / PULSE_INTERVAL_MS)).toBe(12);
    });
});

describe('map tooltip helpers', () => {
    const empty = { points: null, outlets: null, grid: null, whitespace: null, meta: null, routePicking: false };
    it('returns null without a pick or matching data', () => {
        expect(buildTooltip(null, empty)).toBeNull();
        expect(buildTooltip({ kind: 'prospect', index: 0, x: 1, y: 1 }, empty)).toBeNull();
    });
    it('describes an outlet and ignores out-of-range indices', () => {
        const outlets = { count: 1, ids: new Int32Array([1]), positions: new Float64Array(2), names: ['Toko A'], codes: ['A1'], provinceIds: new Int32Array(1) };
        expect(buildTooltip({ kind: 'outlet', index: 0, x: 5, y: 6 }, { ...empty, outlets })).toMatchObject({ title: 'Toko A', x: 5, y: 6 });
        expect(buildTooltip({ kind: 'outlet', index: 3, x: 0, y: 0 }, { ...empty, outlets })).toBeNull();
    });
    it('flips the tooltip near the right edge', () => {
        expect(tooltipPosition(null, 1000)).toEqual({});
        expect(tooltipPosition({ x: 100, y: 10 }, 1000)).toMatchObject({ left: '114px', top: '24px', transform: undefined });
        expect(tooltipPosition({ x: 900, y: 10 }, 1000)).toMatchObject({ left: '886px', transform: 'translateX(-100%)' });
    });
    it('unwraps API detail envelopes', () => {
        expect(unwrapDetail({ id: 1, name: 'a' })).toMatchObject({ id: 1 });
        expect(unwrapDetail({ data: { id: 2 } })).toMatchObject({ id: 2 });
        expect(unwrapDetail({ prospect: { id: 3 } })).toMatchObject({ id: 3 });
    });
});

describe('debug api gating (D-93)', () => {
    it('is on only when the server sets debug_hooks (never inferred from a client-visible env string)', () => {
        expect(debugApiEnabled(false)).toBe(false);
        expect(debugApiEnabled(true)).toBe(true);
        expect(debugApiEnabled(undefined)).toBe(false);
    });
});

describe('payload cut / layer data memo', () => {
    it('skips the copy when length already matches', () => {
        const a = [1, 2, 3];
        expect(cut(a, 3)).toBe(a);
        expect(cut(a, 2)).toEqual([1, 2]);
    });
    it('returns identical data objects for identical inputs so deck.gl does not re-upload', () => {
        const stops = [{ id: 1, name: 'a', lat: 0, lng: 0 }];
        expect(labelledStops(stops)).toBe(labelledStops(stops));
        expect(labelledStops(stops)[0].label).toBe('1');
        const at = { lng: 1, lat: 2 };
        expect(single(at)).toBe(single(at));
    });
});
