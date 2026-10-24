import { MAP_FLAG } from '@/types';
import { buildColors, buildElevations, buildWeights, gridColor, gridRadiusPx } from './colors';
import { pickByFlag } from './payload';
import type { ColorBy, GridData, PointData, PreparedGrid, PreparedPoints } from './types';

/** Derives the GPU attributes (colour, extrusion, heat weight, flagged subsets) from a parsed points payload. */
export function preparePoints(data: PointData, colorBy: ColorBy): PreparedPoints {
    return {
        ...data,
        colors: buildColors(data, colorBy),
        elevations: buildElevations(data),
        weights: buildWeights(data),
        newOpen: pickByFlag(data, MAP_FLAG.NEW_OPEN).positions,
        simulated: pickByFlag(data, MAP_FLAG.SIMULATED).positions,
    };
}

/** Recolours without touching geometry (colour-by toggle). */
export function recolor(p: PreparedPoints, colorBy: ColorBy): PreparedPoints {
    return { ...p, colors: buildColors(p, colorBy) };
}

/** Heatmap weights for the grid payload: square-rooted so dense cities do not wash everything else out. */
export function gridWeights(counts: Uint32Array): Float32Array {
    const out = new Float32Array(counts.length);
    for (let k = 0; k < counts.length; k++) out[k] = Math.sqrt(counts[k]);
    return out;
}

/** Bubble radii (px), colours and heat weights for the grid payload. */
export function prepareGrid(grid: GridData): PreparedGrid {
    let max = 0;
    for (let k = 0; k < grid.count; k++) if (grid.counts[k] > max) max = grid.counts[k];
    const colors = new Uint8Array(grid.count * 4);
    const radii = new Float32Array(grid.count);
    for (let k = 0; k < grid.count; k++) {
        colors.set(gridColor(grid.avgScores[k], grid.hots[k]), k * 4);
        radii[k] = gridRadiusPx(grid.counts[k], max);
    }
    return { ...grid, colors, radii, weights: gridWeights(grid.counts) };
}
