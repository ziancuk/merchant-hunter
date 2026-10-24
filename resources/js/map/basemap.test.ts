import { describe, expect, it, vi } from 'vitest';
import { BasemapGuard, createLocalStyle, graticule, resolveStyle } from './basemap';
import { OPENFREEMAP_STYLE_URL } from './constants';

describe('resolveStyle', () => {
    it('uses the OpenFreeMap dark style URL for the openfreemap basemap', () => {
        expect(resolveStyle('openfreemap')).toBe(OPENFREEMAP_STYLE_URL);
        expect(OPENFREEMAP_STYLE_URL).toBe('https://tiles.openfreemap.org/styles/dark');
    });

    it('uses the local style for basemap=local and once the remote one failed', () => {
        expect(typeof resolveStyle('local')).toBe('object');
        expect(typeof resolveStyle('openfreemap', true)).toBe('object');
    });
});

describe('createLocalStyle', () => {
    const style = createLocalStyle();

    it('is a valid minimal style: dark background first, then the graticule', () => {
        expect(style.version).toBe(8);
        expect(style.layers[0]).toMatchObject({ id: 'bg', type: 'background' });
        expect(style.layers.map((l) => l.id)).toContain('graticule');
    });

    it('needs no network: only an inline GeoJSON source, no sprite/glyph/url', () => {
        const json = JSON.stringify(style);
        expect(json).not.toMatch(/https?:\/\//);
        expect(style).not.toHaveProperty('sprite');
        expect(style).not.toHaveProperty('glyphs');
        const src = style.sources.graticule as { type: string; data: unknown };
        expect(src.type).toBe('geojson');
        expect(typeof src.data).toBe('object');
    });
});

describe('graticule', () => {
    it('draws one line per meridian and parallel at the requested step', () => {
        const g = graticule(30);
        // meridians -180..180 step 30 = 13, parallels -80..80 step 30 = 6
        expect(g.features).toHaveLength(13 + 6);
        expect(g.features.every((f) => f.geometry.type === 'LineString')).toBe(true);
    });
});

describe('BasemapGuard', () => {
    function setup(opts: { maxSourceErrors?: number } = {}) {
        let t = 0;
        let pending: { fn: () => void; ms: number } | null = null;
        const onFail = vi.fn();
        const guard = new BasemapGuard(onFail, {
            now: () => t,
            setTimer: (fn, ms) => {
                pending = { fn, ms };
                return 1;
            },
            clearTimer: () => {
                pending = null;
            },
            ...opts,
        });
        return {
            guard,
            onFail,
            advance: (ms: number) => (t += ms),
            fireTimer: () => pending?.fn(),
            timer: () => pending,
        };
    }

    it('arms a 5 second timeout and fails when the style never arrives', () => {
        const s = setup();
        s.guard.start();
        expect(s.timer()?.ms).toBe(5000);
        s.fireTimer();
        expect(s.onFail).toHaveBeenCalledWith('timeout');
        expect(s.guard.hasFailed).toBe(true);
    });

    it('does not fail when the style loads in time', () => {
        const s = setup();
        s.guard.start();
        s.guard.styleLoaded();
        expect(s.timer()).toBeNull();
        expect(s.onFail).not.toHaveBeenCalled();
    });

    it('fails immediately on a style error and only reports once', () => {
        const s = setup();
        s.guard.start();
        s.guard.styleError();
        s.guard.styleError();
        s.fireTimer();
        expect(s.onFail).toHaveBeenCalledTimes(1);
        expect(s.onFail).toHaveBeenCalledWith('style-error');
    });

    it('tolerates sporadic tile errors but fails on a burst', () => {
        const s = setup();
        s.guard.sourceError();
        s.advance(11_000);
        s.guard.sourceError();
        s.advance(11_000);
        s.guard.sourceError();
        expect(s.onFail).not.toHaveBeenCalled();
        s.advance(100);
        s.guard.sourceError();
        s.guard.sourceError();
        expect(s.onFail).not.toHaveBeenCalled();
        s.guard.sourceError();
        expect(s.onFail).toHaveBeenCalledWith('source-error');
    });

    it('stops its timer on dispose', () => {
        const s = setup();
        s.guard.start();
        s.guard.dispose();
        expect(s.timer()).toBeNull();
    });
});
