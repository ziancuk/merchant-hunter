import { BASEMAP_TIMEOUT_MS, OPENFREEMAP_STYLE_URL } from './constants';

export type BasemapKind = 'openfreemap' | 'local';

/** Minimal structural type for a MapLibre style (this file must not import maplibre-gl: it is unit-tested in jsdom). */
export interface LocalStyle {
    version: 8;
    name: string;
    sources: Record<string, unknown>;
    layers: Record<string, unknown>[];
}

/** Lat/lng graticule (every 10 deg) as GeoJSON; gives the offline basemap a "radar grid" look. */
export function graticule(stepDeg = 10): GeoJSON.FeatureCollection {
    const features: GeoJSON.Feature[] = [];
    for (let lng = -180; lng <= 180; lng += stepDeg) {
        const coords: [number, number][] = [];
        for (let lat = -80; lat <= 80; lat += 5) coords.push([lng, lat]);
        features.push({ type: 'Feature', properties: { kind: 'meridian' }, geometry: { type: 'LineString', coordinates: coords } });
    }
    for (let lat = -80; lat <= 80; lat += stepDeg) {
        const coords: [number, number][] = [];
        for (let lng = -180; lng <= 180; lng += 5) coords.push([lng, lat]);
        features.push({ type: 'Feature', properties: { kind: 'parallel' }, geometry: { type: 'LineString', coordinates: coords } });
    }
    return { type: 'FeatureCollection', features };
}

/** Local style: light background + faint graticule, no network access (D-23). */
export function createLocalStyle(): LocalStyle {
    return {
        version: 8,
        name: 'hunter-local-light',
        sources: { graticule: { type: 'geojson', data: graticule(5) } },
        layers: [
            { id: 'bg', type: 'background', paint: { 'background-color': '#e9efe9' } },
            { id: 'graticule', type: 'line', source: 'graticule', paint: { 'line-color': '#0a7a66', 'line-opacity': 0.22, 'line-width': 0.6 } },
        ],
    };
}

/** Style to hand to MapLibre: the OpenFreeMap URL, or the local style object. */
export function resolveStyle(kind: BasemapKind, failed = false): string | LocalStyle {
    return kind === 'openfreemap' && !failed ? OPENFREEMAP_STYLE_URL : createLocalStyle();
}

export interface GuardOptions {
    timeoutMs?: number;
    /** Source (tile) errors tolerated inside `errorWindowMs` before giving up. */
    maxSourceErrors?: number;
    errorWindowMs?: number;
    now?: () => number;
    setTimer?: (fn: () => void, ms: number) => unknown;
    clearTimer?: (handle: unknown) => void;
}

/**
 * Decides when the remote basemap should be abandoned for the local style:
 * the style does not arrive within 5 s, the style request errors, or tile sources keep failing.
 * Pure (no maplibre): the engine feeds it events; it calls `onFail` once.
 */
export class BasemapGuard {
    private failed = false;
    private handle: unknown = null;
    private errors: number[] = [];
    private readonly o: Required<GuardOptions>;

    constructor(
        private readonly onFail: (reason: string) => void,
        options: GuardOptions = {},
    ) {
        this.o = {
            timeoutMs: options.timeoutMs ?? BASEMAP_TIMEOUT_MS,
            maxSourceErrors: options.maxSourceErrors ?? 4,
            errorWindowMs: options.errorWindowMs ?? 10_000,
            now: options.now ?? (() => Date.now()),
            setTimer: options.setTimer ?? ((fn, ms) => setTimeout(fn, ms)),
            clearTimer: options.clearTimer ?? ((h) => clearTimeout(h as ReturnType<typeof setTimeout>)),
        };
    }

    get hasFailed(): boolean {
        return this.failed;
    }

    /** Starts the style-load countdown. */
    start(): void {
        this.stopTimer();
        this.handle = this.o.setTimer(() => this.fail('timeout'), this.o.timeoutMs);
    }

    /** The style arrived (MapLibre `load`): the countdown is over. */
    styleLoaded(): void {
        this.stopTimer();
    }

    /** The style request itself failed. */
    styleError(): void {
        this.fail('style-error');
    }

    /** A vector/raster source failed to load a tile. */
    sourceError(): void {
        if (this.failed) return;
        const t = this.o.now();
        this.errors = this.errors.filter((x) => t - x <= this.o.errorWindowMs);
        this.errors.push(t);
        if (this.errors.length >= this.o.maxSourceErrors) this.fail('source-error');
    }

    dispose(): void {
        this.stopTimer();
    }

    private stopTimer(): void {
        if (this.handle !== null) this.o.clearTimer(this.handle);
        this.handle = null;
    }

    private fail(reason: string): void {
        if (this.failed) return;
        this.failed = true;
        this.stopTimer();
        this.onFail(reason);
    }
}
