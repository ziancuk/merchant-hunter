import { ColumnLayer, PathLayer, PolygonLayer, ScatterplotLayer, TextLayer } from '@deck.gl/layers';
import { HeatmapLayer } from '@deck.gl/aggregation-layers';
import type { Layer, PickingInfo } from '@deck.gl/core';
import type { MapMode } from '@/types';
import { COLUMN_ZOOM, COVERAGE_RADIUS_M } from './constants';
import { HEAT_COLOR_RANGE, MAP_COLORS, columnRadiusM, discRadiusM, elevationScale, whitespaceColor } from './colors';
import { whitespacePolygon } from './geometry';
import type { HoverKind, MapPick } from './picking';
import type { ColorBy, OutletData, PreparedGrid, PreparedPoints, RouteStop, WhitespaceData } from './types';

export type { HoverKind, MapPick };

export interface LayerHandlers {
    onHover: (pick: MapPick | null) => void;
}

/** Everything the layer stack depends on. Typed arrays are kept by reference so deck.gl can diff cheaply. */
export interface LayerInputs {
    mode: MapMode;
    zoom: number;
    colorBy: ColorBy;
    points: PreparedPoints | null;
    grid: PreparedGrid | null;
    outlets: OutletData | null;
    whitespace: WhitespaceData | null;
    route: { stops: RouteStop[]; path: [number, number][] | null };
    selected: { lng: number; lat: number } | null;
    focusOutlet: { lng: number; lat: number } | null;
    /** 0..1 phase of the "baru buka" pulse; null disables the animation. */
    pulse: number | null;
    handlers: LayerHandlers;
}

/** deck.gl binary `data` object: no per-row JS objects, attributes go straight to the GPU. */
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- deck.gl's generic `data` prop cannot express the binary form
function binary(length: number, attributes: Record<string, { value: ArrayLike<number>; size: number; normalized?: boolean }>): any {
    return { length, attributes };
}

function pickOf(kind: HoverKind, info: PickingInfo): MapPick | null {
    if (info.index === undefined || info.index < 0) return null;
    return { kind, index: info.index, x: info.x, y: info.y };
}

function emit(kind: HoverKind, h: LayerHandlers) {
    return { onHover: (info: PickingInfo) => h.onHover(pickOf(kind, info)) };
}

function ringLayer(id: string, at: { lng: number; lat: number }, radiusM: number, line: number[], fill: number[], width: number): Layer {
    return new ScatterplotLayer({
        id,
        data: [at],
        getPosition: (d: { lng: number; lat: number }) => [d.lng, d.lat],
        radiusUnits: 'meters',
        getRadius: radiusM,
        stroked: true,
        filled: true,
        getFillColor: fill as [number, number, number, number],
        getLineColor: line as [number, number, number, number],
        lineWidthUnits: 'pixels',
        getLineWidth: width,
        pickable: false,
    });
}

/** Builds the full layer stack (bottom to top). Pure with respect to its inputs: ids are stable so deck.gl diffs. */
export function buildLayers(i: LayerInputs): Layer[] {
    const layers: Layer[] = [];
    const showProspects = i.mode === 'prospects' || i.mode === 'coverage';
    const dim = i.mode === 'coverage' ? 0.5 : 1;

    // --- white space (drawn after the outlets so the dots are not hidden behind their halos at far zooms) -----------
    const whitespaceLayers: Layer[] = [];
    if (i.mode === 'whitespace' && i.whitespace && i.whitespace.count > 0) {
        const max = i.whitespace.cells.reduce((m, c) => Math.max(m, c.prospects), 0);
        whitespaceLayers.push(
            new PolygonLayer({
                id: 'whitespace-cells',
                data: i.whitespace.cells,
                getPolygon: (c: { lng: number; lat: number }) => whitespacePolygon(c),
                getFillColor: (c: { prospects: number }) => whitespaceColor(c.prospects, max),
                getLineColor: [125, 227, 196, 220],
                lineWidthUnits: 'pixels',
                getLineWidth: 1,
                stroked: true,
                filled: true,
                pickable: false,
            }),
            new ScatterplotLayer({
                id: 'whitespace-dots',
                data: i.whitespace.cells,
                getPosition: (c: { lng: number; lat: number }) => [c.lng, c.lat],
                radiusUnits: 'pixels',
                getRadius: 8,
                getFillColor: (c: { prospects: number }) => whitespaceColor(c.prospects, max),
                stroked: true,
                getLineColor: [232, 240, 235, 230],
                lineWidthUnits: 'pixels',
                getLineWidth: 1.5,
                pickable: true,
                autoHighlight: true,
                highlightColor: [255, 255, 255, 120],
                ...emit('whitespace', i.handlers),
            }),
        );
    }

    // --- coverage rings ----------------------------------------------------------------------------------------
    if (i.mode === 'coverage' && i.outlets && i.outlets.count > 0) {
        layers.push(
            new ScatterplotLayer({
                id: 'coverage-rings',
                data: binary(i.outlets.count, { getPosition: { value: i.outlets.positions, size: 2 } }),
                radiusUnits: 'meters',
                getRadius: COVERAGE_RADIUS_M,
                stroked: true,
                filled: true,
                getFillColor: MAP_COLORS.coverageFill,
                getLineColor: MAP_COLORS.coverageLine,
                lineWidthUnits: 'pixels',
                getLineWidth: 1.5,
                pickable: false,
            }),
        );
    }

    // --- prospects: heatmap ------------------------------------------------------------------------------------
    if (i.mode === 'heatmap') {
        if (i.points && i.points.count > 0) {
            layers.push(
                new HeatmapLayer({
                    id: 'heatmap',
                    data: binary(i.points.count, { getPosition: { value: i.points.positions, size: 2 }, getWeight: { value: i.points.weights, size: 1 } }),
                    radiusPixels: i.zoom < 8 ? 30 : i.zoom < 12 ? 48 : 64,
                    intensity: 2.6,
                    threshold: 0.02,
                    colorRange: HEAT_COLOR_RANGE,
                    aggregation: 'SUM',
                    pickable: false,
                } as never),
            );
        } else if (i.grid && i.grid.count > 0) {
            layers.push(
                new HeatmapLayer({
                    id: 'heatmap',
                    data: binary(i.grid.count, { getPosition: { value: i.grid.positions, size: 2 }, getWeight: { value: i.grid.weights, size: 1 } }),
                    radiusPixels: 56,
                    intensity: 3,
                    threshold: 0.02,
                    colorRange: HEAT_COLOR_RANGE,
                    aggregation: 'SUM',
                    pickable: false,
                } as never),
            );
        }
    }

    // --- prospects: grid / points ------------------------------------------------------------------------------
    if (showProspects) {
        if (i.grid && i.grid.count > 0) {
            layers.push(
                new ScatterplotLayer({
                    id: 'grid-bubbles',
                    data: binary(i.grid.count, {
                        getPosition: { value: i.grid.positions, size: 2 },
                        getRadius: { value: i.grid.radii, size: 1 },
                        getFillColor: { value: i.grid.colors, size: 4, normalized: true },
                    }),
                    radiusUnits: 'pixels',
                    opacity: dim,
                    stroked: true,
                    getLineColor: [7, 13, 12, 160],
                    lineWidthUnits: 'pixels',
                    getLineWidth: 1,
                    pickable: true,
                    autoHighlight: true,
                    highlightColor: [255, 255, 255, 90],
                    ...emit('grid', i.handlers),
                }),
            );
        } else if (i.points && i.points.count > 0) {
            const data = binary(i.points.count, {
                getPosition: { value: i.points.positions, size: 2 },
                getFillColor: { value: i.points.colors, size: 4, normalized: true },
                getElevation: { value: i.points.elevations, size: 1 },
            });
            if (i.zoom >= COLUMN_ZOOM) {
                layers.push(
                    new ColumnLayer({
                        id: 'prospect-columns',
                        data,
                        radius: columnRadiusM(i.zoom),
                        diskResolution: 8,
                        extruded: true,
                        elevationScale: elevationScale(i.zoom),
                        opacity: dim,
                        pickable: true,
                        autoHighlight: true,
                        highlightColor: [255, 255, 255, 110],
                        material: { ambient: 0.65, diffuse: 0.55, shininess: 24, specularColor: [90, 100, 96] },
                        ...emit('prospect', i.handlers),
                    } as never),
                );
            } else {
                layers.push(
                    new ScatterplotLayer({
                        id: 'prospect-discs',
                        data: binary(i.points.count, { getPosition: { value: i.points.positions, size: 2 }, getFillColor: { value: i.points.colors, size: 4, normalized: true } }),
                        radiusUnits: 'meters',
                        getRadius: discRadiusM(i.zoom),
                        radiusMinPixels: 2.2,
                        radiusMaxPixels: 9,
                        opacity: dim,
                        pickable: true,
                        autoHighlight: true,
                        highlightColor: [255, 255, 255, 140],
                        ...emit('prospect', i.handlers),
                    }),
                );
            }
            // "Baru buka" pulse: expanding ring around flagged prospects.
            if (i.points.newOpen.length > 0 && i.pulse !== null) {
                layers.push(
                    new ScatterplotLayer({
                        id: 'newopen-pulse',
                        data: binary(i.points.newOpen.length / 2, { getPosition: { value: i.points.newOpen, size: 2 } }),
                        radiusUnits: 'pixels',
                        getRadius: 7 + 20 * i.pulse,
                        filled: false,
                        stroked: true,
                        getLineColor: MAP_COLORS.newOpen,
                        lineWidthUnits: 'pixels',
                        getLineWidth: 2,
                        opacity: Math.max(0, 1 - i.pulse) * dim,
                        pickable: false,
                    }),
                );
            } else if (i.points.newOpen.length > 0) {
                layers.push(
                    new ScatterplotLayer({
                        id: 'newopen-static',
                        data: binary(i.points.newOpen.length / 2, { getPosition: { value: i.points.newOpen, size: 2 } }),
                        radiusUnits: 'pixels',
                        getRadius: 9,
                        filled: false,
                        stroked: true,
                        getLineColor: MAP_COLORS.newOpen,
                        lineWidthUnits: 'pixels',
                        getLineWidth: 2,
                        pickable: false,
                    }),
                );
            }
            // Simulated data marker: thin pale ring once zoomed in enough to be legible.
            if (i.zoom >= 10 && i.points.simulated.length > 0) {
                layers.push(
                    new ScatterplotLayer({
                        id: 'simulated-ring',
                        data: binary(i.points.simulated.length / 2, { getPosition: { value: i.points.simulated, size: 2 } }),
                        radiusUnits: 'pixels',
                        getRadius: 6,
                        filled: false,
                        stroked: true,
                        getLineColor: MAP_COLORS.simulated,
                        lineWidthUnits: 'pixels',
                        getLineWidth: 1,
                        opacity: dim,
                        pickable: false,
                    }),
                );
            }
        }
    }

    // --- route -------------------------------------------------------------------------------------------------
    const { stops, path } = i.route;
    if (path && path.length > 1) {
        layers.push(
            new PathLayer({
                id: 'route-glow',
                data: [{ path }],
                getPath: (d: { path: [number, number][] }) => d.path,
                getColor: [125, 227, 196, 70],
                widthUnits: 'pixels',
                getWidth: 12,
                capRounded: true,
                jointRounded: true,
                pickable: false,
            }),
            new PathLayer({
                id: 'route-path',
                data: [{ path }],
                getPath: (d: { path: [number, number][] }) => d.path,
                getColor: MAP_COLORS.route,
                widthUnits: 'pixels',
                getWidth: 3.5,
                capRounded: true,
                jointRounded: true,
                pickable: false,
            }),
        );
    }
    if (stops.length > 0) {
        const labelled = stops.map((s, n) => ({ ...s, label: String(n + 1) }));
        layers.push(
            new ScatterplotLayer({
                id: 'route-stops',
                data: labelled,
                getPosition: (s: RouteStop) => [s.lng, s.lat],
                radiusUnits: 'pixels',
                getRadius: 11,
                getFillColor: [125, 227, 196, 255],
                stroked: true,
                getLineColor: [7, 13, 12, 255],
                lineWidthUnits: 'pixels',
                getLineWidth: 2,
                pickable: false,
            }),
            new TextLayer({
                id: 'route-labels',
                data: labelled,
                getPosition: (s: RouteStop) => [s.lng, s.lat],
                getText: (s: { label: string }) => s.label,
                getSize: 12,
                getColor: [7, 13, 12, 255],
                fontFamily: "'JetBrains Mono', ui-monospace, monospace",
                fontWeight: 700,
                getTextAnchor: 'middle',
                getAlignmentBaseline: 'center',
                pickable: false,
            }),
        );
    }

    // --- selection ---------------------------------------------------------------------------------------------
    if (i.selected) {
        layers.push(
            new ScatterplotLayer({
                id: 'selected-ring',
                data: [i.selected],
                getPosition: (d: { lng: number; lat: number }) => [d.lng, d.lat],
                radiusUnits: 'pixels',
                getRadius: 15,
                filled: false,
                stroked: true,
                getLineColor: MAP_COLORS.selection,
                lineWidthUnits: 'pixels',
                getLineWidth: 2.5,
                pickable: false,
            }),
        );
    }

    // --- outlets (always on top: glowing markers, clearly different from prospects) ---------------------------------
    if (i.focusOutlet) {
        layers.push(ringLayer('outlet-focus-ring', i.focusOutlet, COVERAGE_RADIUS_M, [125, 227, 196, 255], [125, 227, 196, 26], 2));
    }
    if (i.outlets && i.outlets.count > 0) {
        const od = binary(i.outlets.count, { getPosition: { value: i.outlets.positions, size: 2 } });
        layers.push(
            new ScatterplotLayer({ id: 'outlet-halo', data: od, radiusUnits: 'pixels', getRadius: 17, getFillColor: [125, 227, 196, 34], pickable: false }),
            new ScatterplotLayer({ id: 'outlet-glow', data: od, radiusUnits: 'pixels', getRadius: 10, getFillColor: [125, 227, 196, 90], pickable: false }),
            new ScatterplotLayer({
                id: 'outlet-core',
                data: od,
                radiusUnits: 'pixels',
                getRadius: 5,
                getFillColor: MAP_COLORS.outletCore,
                stroked: true,
                getLineColor: [7, 13, 12, 255],
                lineWidthUnits: 'pixels',
                getLineWidth: 1.5,
                pickable: true,
                autoHighlight: true,
                highlightColor: [125, 227, 196, 255],
                ...emit('outlet', i.handlers),
            }),
        );
    }

    layers.push(...whitespaceLayers);
    return layers;
}
