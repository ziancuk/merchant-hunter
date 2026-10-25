import { INTRO_STORAGE_KEY, MAX_ROUTE_STOPS } from './constants';

export interface MapUrlParams {
    /** `?intro=0` / `?intro=1`; null when absent. */
    intro: boolean | null;
    /** `?motion=0` / `?motion=1`; null when absent. */
    motion: boolean | null;
    /** `?prospect=ID` deep link. */
    prospect: number | null;
    /** `?route=ID,ID,...` deep link (unique positive ids, capped at MAX_ROUTE_STOPS). */
    route: number[];
    /** `?lat=&lng=` camera target (valid ranges only). */
    focus: { lat: number; lng: number } | null;
    /** `?mode=` initial map mode. */
    mode: 'prospects' | 'heatmap' | 'whitespace' | 'coverage' | null;
}

function toCoord(v: string | null, max: number): number | null {
    if (v === null || v.trim() === '') return null;
    const n = Number(v);
    return Number.isFinite(n) && Math.abs(n) <= max ? n : null;
}

function toFlag(v: string | null): boolean | null {
    if (v === '0' || v === 'false') return false;
    if (v === '1' || v === 'true') return true;
    return null;
}

function toId(v: string | null): number | null {
    if (v === null || !/^\d{1,12}$/.test(v.trim())) return null;
    const n = Number(v);
    return Number.isSafeInteger(n) && n > 0 ? n : null;
}

/** Parses the deep-link and behaviour parameters of the map page URL. */
export function parseMapParams(search: string | URLSearchParams): MapUrlParams {
    const sp = typeof search === 'string' ? new URLSearchParams(search.replace(/^\?/, '')) : search;
    const route: number[] = [];
    for (const part of (sp.get('route') ?? '').split(',')) {
        const id = toId(part);
        if (id !== null && !route.includes(id)) route.push(id);
        if (route.length >= MAX_ROUTE_STOPS) break;
    }
    const lat = toCoord(sp.get('lat'), 90);
    const lng = toCoord(sp.get('lng'), 180);
    const m = sp.get('mode');
    const mode = m === 'prospects' || m === 'heatmap' || m === 'whitespace' || m === 'coverage' ? m : null;
    return {
        intro: toFlag(sp.get('intro')),
        motion: toFlag(sp.get('motion')),
        prospect: toId(sp.get('prospect')),
        route,
        focus: lat !== null && lng !== null ? { lat, lng } : null,
        mode,
    };
}

export interface MotionFlags {
    /** Whether the cinematic intro should play. */
    intro: boolean;
    /** Whether decorative animation (pulse, flights, rotation) is allowed. */
    motion: boolean;
}

/**
 * Resolves the intro/motion switches.
 *
 * `?intro=` and `?motion=` are honoured whenever the server enables `app.debug_hooks` (not production), so E2E and
 * demos can disable the intro; in production they are ignored and only the user's stored preference and
 * `prefers-reduced-motion` apply. The URL never *enables* motion for a user who asked the OS for reduced motion.
 */
export function resolveMotionFlags(opts: { params: MapUrlParams; debugHooks: boolean | undefined; introSkipped: boolean; reducedMotion: boolean }): MotionFlags {
    const overridable = opts.debugHooks === true;
    const motionParam = overridable ? opts.params.motion : null;
    const introParam = overridable ? opts.params.intro : null;
    const motion = !opts.reducedMotion && motionParam !== false;
    if (!motion) return { intro: false, motion: false };
    const intro = introParam !== null ? introParam : !opts.introSkipped;
    return { intro, motion };
}

type StorageLike = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

function defaultStorage(): StorageLike | null {
    try {
        return typeof localStorage === 'undefined' ? null : localStorage;
    } catch {
        return null;
    }
}

/** Whether the user chose to skip the intro earlier (never throws: private mode/blocked storage = false). */
export function readIntroSkipped(storage: StorageLike | null = defaultStorage()): boolean {
    try {
        return storage?.getItem(INTRO_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export function writeIntroSkipped(skipped: boolean, storage: StorageLike | null = defaultStorage()): void {
    try {
        if (!storage) return;
        if (skipped) storage.setItem(INTRO_STORAGE_KEY, '1');
        else storage.removeItem(INTRO_STORAGE_KEY);
    } catch {
        /* storage unavailable: preference simply is not remembered */
    }
}

/** Reads a string preference, wrapped in try/catch. */
export function readPref(key: string, storage: StorageLike | null = defaultStorage()): string | null {
    try {
        return storage?.getItem(key) ?? null;
    } catch {
        return null;
    }
}

export function writePref(key: string, value: string, storage: StorageLike | null = defaultStorage()): void {
    try {
        storage?.setItem(key, value);
    } catch {
        /* ignore */
    }
}

/** Builds the page URL with `route` set (comma separated ids) or removed when empty, keeping the other parameters (B-05). */
export function withRouteParam(href: string, ids: number[]): string {
    const url = new URL(href, 'http://localhost');
    if (ids.length === 0) url.searchParams.delete('route');
    else url.searchParams.set('route', ids.join(','));
    const qs = url.searchParams.toString().replace(/%2C/g, ',');
    return `${url.pathname}${qs ? `?${qs}` : ''}${url.hash}`;
}

/** Builds the page URL with `prospect` set/removed, keeping the other parameters (history.replaceState helper). */
export function withProspectParam(href: string, id: number | null): string {
    const url = new URL(href, 'http://localhost');
    if (id === null) url.searchParams.delete('prospect');
    else url.searchParams.set('prospect', String(id));
    const qs = url.searchParams.toString();
    return `${url.pathname}${qs ? `?${qs}` : ''}${url.hash}`;
}
