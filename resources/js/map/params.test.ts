import { describe, expect, it } from 'vitest';
import { INTRO_STORAGE_KEY } from './constants';
import { parseMapParams, readIntroSkipped, readPref, resolveMotionFlags, withProspectParam, withRouteParam, writeIntroSkipped, writePref } from './params';

function memoryStorage(initial: Record<string, string> = {}) {
    const data = new Map(Object.entries(initial));
    return {
        getItem: (k: string) => data.get(k) ?? null,
        setItem: (k: string, v: string) => void data.set(k, v),
        removeItem: (k: string) => void data.delete(k),
        data,
    };
}

const throwing = {
    getItem: () => {
        throw new Error('blocked');
    },
    setItem: () => {
        throw new Error('blocked');
    },
    removeItem: () => {
        throw new Error('blocked');
    },
};

describe('parseMapParams', () => {
    it('reads intro/motion switches', () => {
        expect(parseMapParams('?intro=0&motion=0')).toMatchObject({ intro: false, motion: false });
        expect(parseMapParams('?intro=1&motion=1')).toMatchObject({ intro: true, motion: true });
        expect(parseMapParams('')).toMatchObject({ intro: null, motion: null, prospect: null, route: [] });
        expect(parseMapParams('?intro=maybe').intro).toBeNull();
    });

    it('reads the prospect deep link and rejects non-numeric ids', () => {
        expect(parseMapParams('?prospect=4409').prospect).toBe(4409);
        expect(parseMapParams('?prospect=abc').prospect).toBeNull();
        expect(parseMapParams('?prospect=-5').prospect).toBeNull();
        expect(parseMapParams('?prospect=0').prospect).toBeNull();
        expect(parseMapParams('?prospect=12abc').prospect).toBeNull();
    });

    it('reads the route deep link: unique positive ids, junk dropped, capped at 30', () => {
        expect(parseMapParams('?route=1,2,3').route).toEqual([1, 2, 3]);
        expect(parseMapParams('?route=1,2,2,x,,-4,3').route).toEqual([1, 2, 3]);
        const many = Array.from({ length: 50 }, (_, i) => i + 1).join(',');
        expect(parseMapParams(`?route=${many}`).route).toHaveLength(30);
    });

    it('accepts URLSearchParams too', () => {
        expect(parseMapParams(new URLSearchParams('prospect=7')).prospect).toBe(7);
    });
});

describe('resolveMotionFlags', () => {
    const base = { params: parseMapParams(''), env: 'local', introSkipped: false, reducedMotion: false };

    it('plays the intro by default', () => {
        expect(resolveMotionFlags(base)).toEqual({ intro: true, motion: true });
    });

    it('stored skip disables the intro but keeps motion', () => {
        expect(resolveMotionFlags({ ...base, introSkipped: true })).toEqual({ intro: false, motion: true });
    });

    it('?intro=0 and ?motion=0 are honoured outside production', () => {
        expect(resolveMotionFlags({ ...base, params: parseMapParams('?intro=0') })).toEqual({ intro: false, motion: true });
        expect(resolveMotionFlags({ ...base, params: parseMapParams('?motion=0') })).toEqual({ intro: false, motion: false });
        expect(resolveMotionFlags({ ...base, env: 'e2e', params: parseMapParams('?intro=0&motion=0') })).toEqual({ intro: false, motion: false });
    });

    it('?intro=1 can force the intro again for demos (non-production)', () => {
        expect(resolveMotionFlags({ ...base, introSkipped: true, params: parseMapParams('?intro=1') })).toEqual({ intro: true, motion: true });
    });

    it('ignores the URL switches in production', () => {
        const params = parseMapParams('?intro=0&motion=0');
        expect(resolveMotionFlags({ ...base, env: 'production', params })).toEqual({ intro: true, motion: true });
        expect(resolveMotionFlags({ ...base, env: 'production', introSkipped: true, params: parseMapParams('?intro=1') })).toEqual({ intro: false, motion: true });
    });

    it('prefers-reduced-motion switches everything off and cannot be overridden by the URL', () => {
        expect(resolveMotionFlags({ ...base, reducedMotion: true })).toEqual({ intro: false, motion: false });
        expect(resolveMotionFlags({ ...base, reducedMotion: true, params: parseMapParams('?intro=1&motion=1') })).toEqual({ intro: false, motion: false });
    });
});

describe('intro preference storage', () => {
    it('remembers and forgets the skip preference', () => {
        const s = memoryStorage();
        expect(readIntroSkipped(s)).toBe(false);
        writeIntroSkipped(true, s);
        expect(s.data.get(INTRO_STORAGE_KEY)).toBe('1');
        expect(readIntroSkipped(s)).toBe(true);
        writeIntroSkipped(false, s);
        expect(readIntroSkipped(s)).toBe(false);
    });

    it('never throws when storage is blocked or missing', () => {
        expect(readIntroSkipped(throwing)).toBe(false);
        expect(() => writeIntroSkipped(true, throwing)).not.toThrow();
        expect(readIntroSkipped(null)).toBe(false);
        expect(() => writeIntroSkipped(true, null)).not.toThrow();
        expect(readPref('x', throwing)).toBeNull();
        expect(() => writePref('x', 'y', throwing)).not.toThrow();
    });

    it('generic prefs round-trip', () => {
        const s = memoryStorage();
        writePref('k', 'status', s);
        expect(readPref('k', s)).toBe('status');
        expect(readPref('missing', s)).toBeNull();
    });
});

describe('withProspectParam', () => {
    it('sets and clears the prospect parameter while keeping the others', () => {
        expect(withProspectParam('/?intro=0', 5)).toBe('/?intro=0&prospect=5');
        expect(withProspectParam('/?intro=0&prospect=5', 9)).toBe('/?intro=0&prospect=9');
        expect(withProspectParam('/?intro=0&prospect=5', null)).toBe('/?intro=0');
        expect(withProspectParam('/?prospect=5', null)).toBe('/');
        expect(withProspectParam('/', 3)).toBe('/?prospect=3');
    });
});

describe('focus and mode deep links', () => {
    it('parses valid lat/lng/mode and rejects bad values', () => {
        expect(parseMapParams('?lat=-6.2&lng=106.8&mode=whitespace')).toMatchObject({ focus: { lat: -6.2, lng: 106.8 }, mode: 'whitespace' });
        expect(parseMapParams('?lat=99&lng=106.8&mode=nope')).toMatchObject({ focus: null, mode: null });
        expect(parseMapParams('?lat=-6.2')).toMatchObject({ focus: null });
    });
});

describe('withRouteParam (B-05)', () => {
    it('sets the route ids and keeps the other params', () => {
        expect(withRouteParam('/?intro=0', [3, 4, 5])).toBe('/?intro=0&route=3,4,5');
    });
    it('removes the route param when the route is emptied', () => {
        expect(withRouteParam('/?route=3,4&mode=heatmap', [])).toBe('/?mode=heatmap');
        expect(withRouteParam('/?route=3,4', [])).toBe('/');
    });
});
