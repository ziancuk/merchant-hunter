import { BAND_HEX, STATUS_HEX, scoreBand } from '@/lib/colors';
import { MAP_FLAG, PIPELINE_STATUSES } from '@/types';
import type { ColorBy, PointData } from './types';

export type Rgba = [number, number, number, number];

export function hexToRgb(hex: string): [number, number, number] {
    const h = hex.replace('#', '');
    const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
    const n = parseInt(full, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

const BAND_RGB = Object.fromEntries(Object.entries(BAND_HEX.dark).map(([k, v]) => [k, hexToRgb(v)])) as Record<keyof typeof BAND_HEX.dark, [number, number, number]>;
const STATUS_RGB = Object.fromEntries(Object.entries(STATUS_HEX.dark).map(([k, v]) => [k, hexToRgb(v)])) as Record<keyof typeof STATUS_HEX.dark, [number, number, number]>;

/** Palette used on the canvas (the basemap is always dark, so the dark tokens apply in both themes). */
export const MAP_COLORS = {
    outletCore: [232, 240, 235, 255] as Rgba,
    outletHalo: [125, 227, 196, 70] as Rgba,
    outletRing: [125, 227, 196, 200] as Rgba,
    coverageFill: [125, 227, 196, 14] as Rgba,
    coverageLine: [125, 227, 196, 110] as Rgba,
    route: [125, 227, 196, 255] as Rgba,
    selection: [255, 255, 255, 255] as Rgba,
    routePick: [125, 227, 196, 255] as Rgba,
    newOpen: [255, 106, 43, 255] as Rgba,
    simulated: [232, 240, 235, 170] as Rgba,
    whitespace: [125, 227, 196] as [number, number, number],
};

/** RGBA for one point. Closed / opted-out prospects are dimmed so they never look like live leads. */
export function pointColor(score: number, status: number, flags: number, colorBy: ColorBy): Rgba {
    const base = colorBy === 'status' ? STATUS_RGB[PIPELINE_STATUSES[status] ?? 'new'] : BAND_RGB[scoreBand(score)];
    if (flags & MAP_FLAG.CLOSED) return [140, 150, 150, 90];
    if (flags & MAP_FLAG.OPT_OUT) return [base[0], base[1], base[2], 110];
    return [base[0], base[1], base[2], colorBy === 'score' && score < 35 ? 190 : 235];
}

/** Packs the per-point RGBA into one Uint8Array (deck.gl binary attribute, size 4). */
export function buildColors(data: PointData, colorBy: ColorBy): Uint8Array {
    const out = new Uint8Array(data.count * 4);
    for (let i = 0; i < data.count; i++) {
        const c = pointColor(data.scores[i], data.status[i], data.flags[i], colorBy);
        out.set(c, i * 4);
    }
    return out;
}

/** Base column height in "score units"; scaled to metres per zoom by `elevationScale`. */
export function buildElevations(data: PointData): Float32Array {
    const out = new Float32Array(data.count);
    for (let i = 0; i < data.count; i++) out[i] = data.scores[i] + 25;
    return out;
}

/** Heatmap weights: hotter prospects weigh more, but cold ones still count. */
export function buildWeights(data: PointData): Float32Array {
    const out = new Float32Array(data.count);
    for (let i = 0; i < data.count; i++) out[i] = 0.4 + data.scores[i] / 100;
    return out;
}

/** Metres per screen pixel at the equator. */
export function metersPerPixel(zoom: number, latitude = 0): number {
    return (156543.03392 * Math.cos((latitude * Math.PI) / 180)) / 2 ** zoom;
}

/** Column radius in metres: ~3.5 px wide on screen, but never thinner than 20 m or fatter than 800 m. */
export function columnRadiusM(zoom: number, latitude = -3): number {
    return Math.max(20, Math.min(800, metersPerPixel(zoom, latitude) * 3.5));
}

/** Metres of extrusion per score unit: a 100-point prospect stands ~44 px tall on screen. */
export function elevationScale(zoom: number, latitude = -3): number {
    return Math.max(0.5, metersPerPixel(zoom, latitude) * 0.35);
}

/** Flat-disc radius for far zooms (metres, with a pixel floor applied by the layer). */
export function discRadiusM(zoom: number, latitude = -3): number {
    return Math.max(15, metersPerPixel(zoom, latitude) * 2);
}

/** Aggregate grid bubble: radius in px grows with sqrt(count). */
export function gridRadiusPx(count: number, maxCount: number): number {
    if (maxCount <= 0) return 6;
    return 6 + 26 * Math.sqrt(Math.max(0, count) / maxCount);
}

export function gridColor(avgScore: number, hot: number): Rgba {
    const c = hot > 0 ? BAND_RGB.hot : BAND_RGB[scoreBand(avgScore)];
    return [c[0], c[1], c[2], 190];
}

/** Heatmap colour ramp: transparent teal to amber to flare orange (deck.gl `colorRange`). */
export const HEAT_COLOR_RANGE: [number, number, number, number][] = [
    [79, 195, 169, 0],
    [79, 195, 169, 110],
    [242, 182, 58, 170],
    [255, 140, 50, 220],
    [255, 106, 43, 255],
];

/** White space cell colour: more prospects, stronger teal. */
export function whitespaceColor(prospects: number, maxProspects: number): Rgba {
    const t = maxProspects > 0 ? Math.min(1, prospects / maxProspects) : 0;
    const c = MAP_COLORS.whitespace;
    return [c[0], c[1], c[2], Math.round(70 + 120 * t)];
}
