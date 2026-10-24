import type { MapMode, ProspectFilter } from '@/types';

export type Bbox = [number, number, number, number];
export type ColorBy = 'score' | 'status';

export interface Viewport {
    bbox: Bbox;
    zoom: number;
    center: [number, number];
    pitch: number;
    bearing: number;
}

/** GET /api/map/outlets (columnar). */
export interface OutletsResponse {
    count: number;
    ids: number[];
    codes: string[];
    names: string[];
    lng: number[];
    lat: number[];
    province_ids: number[];
}

export interface GridCell {
    lng: number;
    lat: number;
    count: number;
    avg_score: number;
    hot: number;
}

export interface WhitespaceCell {
    id: string;
    lng: number;
    lat: number;
    prospects: number;
    avg_score: number;
    nearest_outlet_m: number;
}
export interface WhitespaceResponse {
    cell_size_m: number;
    cells: WhitespaceCell[];
}

/** Typed-array form of a `points` payload, ready for deck.gl binary attributes. */
export interface PointData {
    count: number;
    truncated: boolean;
    ids: Int32Array;
    /** Interleaved lng,lat (Float64 keeps precision at any longitude). */
    positions: Float64Array;
    scores: Uint8Array;
    status: Uint8Array;
    flags: Uint8Array;
}

export interface GridData {
    cellSizeDeg: number;
    count: number;
    /** Total prospects across all cells. */
    total: number;
    /** Cell centre, interleaved lng,lat. */
    positions: Float64Array;
    counts: Uint32Array;
    avgScores: Float32Array;
    hots: Uint32Array;
}

export interface OutletData {
    count: number;
    ids: Int32Array;
    /** Interleaved lng,lat. */
    positions: Float64Array;
    names: string[];
    codes: string[];
    provinceIds: Int32Array;
}

export interface WhitespaceData {
    count: number;
    cells: WhitespaceCell[];
    total: number;
}

export interface RouteStop {
    id: number;
    name: string | null;
    lat: number;
    lng: number;
}

export interface MapQueryState {
    bbox: Bbox;
    zoom: number;
    mode: MapMode;
    filter: ProspectFilter;
}

/** Point data plus the derived per-point GPU attributes. */
export interface PreparedPoints extends PointData {
    colors: Uint8Array;
    elevations: Float32Array;
    weights: Float32Array;
    /** Interleaved lng,lat of "baru buka" prospects. */
    newOpen: Float64Array;
    /** Interleaved lng,lat of prospects with simulated data. */
    simulated: Float64Array;
}

export interface PreparedGrid extends GridData {
    colors: Uint8Array;
    radii: Float32Array;
    weights: Float32Array;
}
