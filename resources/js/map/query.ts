import { filterToQuery } from '@/composables/useFilters';
import type { MapMode, ProspectFilter } from '@/types';
import type { Bbox } from './types';

const round = (n: number, d = 4): number => Math.round(n * 10 ** d) / 10 ** d;

/** Clamps a bbox to valid Web Mercator extents and orders its corners. Returns [w, s, e, n]. */
export function clampBbox(b: readonly [number, number, number, number]): Bbox {
    const w = Math.max(-180, Math.min(180, Math.min(b[0], b[2])));
    const e = Math.max(-180, Math.min(180, Math.max(b[0], b[2])));
    const s = Math.max(-85, Math.min(85, Math.min(b[1], b[3])));
    const n = Math.max(-85, Math.min(85, Math.max(b[1], b[3])));
    return [round(w), round(s), round(e), round(n)];
}

export function bboxToParam(b: Bbox): string {
    return b.map((v) => round(v)).join(',');
}

/** The API wants an integer zoom (0-22). */
export function zoomParam(zoom: number): number {
    if (!Number.isFinite(zoom)) return 0;
    return Math.max(0, Math.min(22, Math.floor(zoom)));
}

/** True when the server answers with the aggregated grid for this zoom. */
export function isGridZoom(zoom: number, gridZoom = 6): boolean {
    return zoomParam(zoom) < gridZoom;
}

function join(base: string, parts: string[]): string {
    const qs = parts.filter(Boolean).join('&');
    return qs ? `${base}?${qs}` : base;
}

/** GET /api/map/prospects?bbox=…&zoom=…&mode=…&<filters> */
export function buildProspectsUrl(args: { bbox: Bbox; zoom: number; mode: MapMode; filter: ProspectFilter }): string {
    const mode = args.mode === 'heatmap' ? 'heatmap' : 'prospects';
    return join('/api/map/prospects', [`bbox=${bboxToParam(clampBbox(args.bbox))}`, `zoom=${zoomParam(args.zoom)}`, `mode=${mode}`, filterToQuery(args.filter)]);
}

/** GET /api/map/whitespace: only the category filter applies (CONTRACTS §3). */
export function buildWhitespaceUrl(args: { bbox: Bbox; filter: ProspectFilter }): string {
    const cats = (args.filter.category_id ?? []).map((id) => `category_id[]=${encodeURIComponent(String(id))}`);
    return join('/api/map/whitespace', [`bbox=${bboxToParam(clampBbox(args.bbox))}`, ...cats]);
}

export function buildSearchUrl(q: string): string {
    return `/api/search?q=${encodeURIComponent(q.trim())}`;
}

/** Deep link for a prospect on the map page. */
export function prospectLink(id: number): string {
    return `/?prospect=${id}`;
}

/** Deep link that opens the route planner with these prospects. */
export function routeLink(ids: number[]): string {
    return `/?route=${ids.join(',')}`;
}
