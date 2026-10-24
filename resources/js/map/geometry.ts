import { WHITESPACE_CELL_DEG } from './constants';
import type { Bbox, WhitespaceCell } from './types';

/** Square ring (lng,lat) around a white space cell centre. */
export function whitespacePolygon(cell: Pick<WhitespaceCell, 'lng' | 'lat'>, sizeDeg = WHITESPACE_CELL_DEG): [number, number][] {
    const h = sizeDeg / 2;
    return [
        [cell.lng - h, cell.lat - h],
        [cell.lng + h, cell.lat - h],
        [cell.lng + h, cell.lat + h],
        [cell.lng - h, cell.lat + h],
        [cell.lng - h, cell.lat - h],
    ];
}

/** Bounding box of an interleaved lng,lat array; null when empty. */
export function boundsOfPositions(positions: ArrayLike<number>, count = Math.floor(positions.length / 2)): Bbox | null {
    if (count <= 0) return null;
    let w = Infinity;
    let s = Infinity;
    let e = -Infinity;
    let n = -Infinity;
    for (let i = 0; i < count; i++) {
        const lng = positions[2 * i];
        const lat = positions[2 * i + 1];
        if (lng < w) w = lng;
        if (lng > e) e = lng;
        if (lat < s) s = lat;
        if (lat > n) n = lat;
    }
    return [w, s, e, n];
}

const EARTH_RADIUS_M = 6_371_008.8;

/** Great-circle distance in metres. */
export function haversine(lat1: number, lng1: number, lat2: number, lng2: number): number {
    const rad = Math.PI / 180;
    const dLat = (lat2 - lat1) * rad;
    const dLng = (lng2 - lng1) * rad;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) ** 2;
    return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(a)));
}

/**
 * Index of the point closest to (lng, lat) within `maxMeters`, or -1. Used for tooltip/selection fallbacks
 * when deck.gl picking is not available (tests, e2e helpers).
 */
export function nearestIndex(positions: ArrayLike<number>, count: number, lng: number, lat: number, maxMeters: number): number {
    let best = -1;
    let bestD = maxMeters;
    for (let i = 0; i < count; i++) {
        const d = haversine(lat, lng, positions[2 * i + 1], positions[2 * i]);
        if (d <= bestD) {
            bestD = d;
            best = i;
        }
    }
    return best;
}

/** Offsets a map padding so the point of interest stays clear of the right-hand drawer. */
export function drawerPadding(viewportWidth: number, drawerOpen: boolean): { top: number; bottom: number; left: number; right: number } {
    const right = drawerOpen && viewportWidth >= 640 ? Math.min(480, Math.round(viewportWidth * 0.45)) : 0;
    return { top: 0, bottom: 0, left: 0, right };
}

/** Camera target for an intro fit: zoom adapts to the viewport width so Indonesia always fits. */
export function introTargetZoom(viewportWidth: number): number {
    // ~46 degrees of longitude must fit in ~86% of the width: 512 * 2^z * 46/360 = 0.86 * width (MapLibre uses 512 px tiles)
    const z = Math.log2((0.86 * viewportWidth * 360) / (512 * 46));
    return Math.max(2.2, Math.min(5.4, Math.round(z * 100) / 100));
}
