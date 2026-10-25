import { MAP_FLAG, PIPELINE_STATUSES, type MapPointsResponse, type PipelineStatus } from '@/types';
import type { GridData, OutletData, OutletsResponse, PointData, WhitespaceData, WhitespaceResponse } from './types';

type PointsPayload = Extract<MapPointsResponse, { type: 'points' }>;
type GridPayload = Extract<MapPointsResponse, { type: 'grid' }>;

export function isPointsPayload(p: MapPointsResponse): p is PointsPayload {
    return p.type === 'points';
}

export function isGridPayload(p: MapPointsResponse): p is GridPayload {
    return p.type === 'grid';
}

/** Interleaves two columns into one `[x0, y0, x1, y1, …]` Float64Array. */
export function interleave(xs: ArrayLike<number>, ys: ArrayLike<number>, count = Math.min(xs.length, ys.length)): Float64Array {
    const out = new Float64Array(count * 2);
    for (let i = 0; i < count; i++) {
        out[2 * i] = xs[i];
        out[2 * i + 1] = ys[i];
    }
    return out;
}

/** First `count` entries; skips the copy when the column already has exactly that length. */
export function cut<T>(a: T[], count: number): T[] {
    return a.length === count ? a : a.slice(0, count);
}

/** Columnar `points` payload → typed arrays. The shortest column bounds the count so corrupt payloads cannot overrun. */
export function parsePoints(p: PointsPayload): PointData {
    const count = Math.min(p.ids.length, p.lng.length, p.lat.length, p.score.length, p.status.length, p.flags.length);
    return {
        count,
        truncated: !!p.truncated,
        ids: Int32Array.from(cut(p.ids, count)),
        positions: interleave(p.lng, p.lat, count),
        scores: Uint8Array.from(cut(p.score, count)),
        status: Uint8Array.from(cut(p.status, count)),
        flags: Uint8Array.from(cut(p.flags, count)),
    };
}

/** Grid payload → typed arrays. Cell `lng/lat` is the cell origin (south-west corner); positions hold the centre. */
export function parseGrid(p: GridPayload): GridData {
    const size = p.cell_size_deg;
    const n = p.cells.length;
    const positions = new Float64Array(n * 2);
    const counts = new Uint32Array(n);
    const avgScores = new Float32Array(n);
    const hots = new Uint32Array(n);
    let total = 0;
    for (let i = 0; i < n; i++) {
        const c = p.cells[i];
        positions[2 * i] = c.lng + size / 2;
        positions[2 * i + 1] = c.lat + size / 2;
        counts[i] = c.count;
        avgScores[i] = c.avg_score;
        hots[i] = c.hot;
        total += c.count;
    }
    return { cellSizeDeg: size, count: n, total, positions, counts, avgScores, hots };
}

export function parseOutlets(p: OutletsResponse): OutletData {
    const count = Math.min(p.ids.length, p.lng.length, p.lat.length);
    return {
        count,
        ids: Int32Array.from(cut(p.ids, count)),
        positions: interleave(p.lng, p.lat, count),
        names: cut(p.names, count),
        codes: cut(p.codes, count),
        provinceIds: Int32Array.from(cut(p.province_ids ?? [], count)),
    };
}

export function parseWhitespace(p: WhitespaceResponse): WhitespaceData {
    const cells = Array.isArray(p.cells) ? p.cells : [];
    return { count: cells.length, cells, total: cells.reduce((s, c) => s + c.prospects, 0) };
}

export function statusFromIndex(i: number): PipelineStatus {
    return PIPELINE_STATUSES[i] ?? 'new';
}

export interface FlagInfo {
    newOpen: boolean;
    simulated: boolean;
    closed: boolean;
    optOut: boolean;
}

export function decodeFlags(flags: number): FlagInfo {
    return {
        newOpen: (flags & MAP_FLAG.NEW_OPEN) !== 0,
        simulated: (flags & MAP_FLAG.SIMULATED) !== 0,
        closed: (flags & MAP_FLAG.CLOSED) !== 0,
        optOut: (flags & MAP_FLAG.OPT_OUT) !== 0,
    };
}

/** Compact interleaved positions of the points whose flag bit is set (for the pulse / simulation overlay layers). */
export function pickByFlag(data: PointData, flag: number): { count: number; positions: Float64Array; indices: Int32Array } {
    let n = 0;
    for (let i = 0; i < data.count; i++) if (data.flags[i] & flag) n++;
    const positions = new Float64Array(n * 2);
    const indices = new Int32Array(n);
    let k = 0;
    for (let i = 0; i < data.count; i++) {
        if (data.flags[i] & flag) {
            positions[2 * k] = data.positions[2 * i];
            positions[2 * k + 1] = data.positions[2 * i + 1];
            indices[k++] = i;
        }
    }
    return { count: n, positions, indices };
}

/** Index of the point with this prospect id, or -1. */
export function indexOfId(ids: Int32Array, id: number): number {
    for (let i = 0; i < ids.length; i++) if (ids[i] === id) return i;
    return -1;
}

export const EMPTY_POINTS: PointData = {
    count: 0,
    truncated: false,
    ids: new Int32Array(0),
    positions: new Float64Array(0),
    scores: new Uint8Array(0),
    status: new Uint8Array(0),
    flags: new Uint8Array(0),
};
