import type { MapMode } from '@/types';

/** Below this zoom the server returns aggregated grid cells instead of points (CONTRACTS §3, D-15). */
export const GRID_ZOOM = 6;
/** Zoom from which prospects are drawn as extruded 3D columns (below: flat discs). */
export const COLUMN_ZOOM = 9;
/** Radius of the outlet coverage ring (prospects are only discovered within 2 km of an outlet). */
export const COVERAGE_RADIUS_M = 2000;
/** White space cells are ~1 km wide (0.01 deg, D-86). */
export const WHITESPACE_CELL_DEG = 0.01;

export const MIN_ROUTE_STOPS = 2;
export const MAX_ROUTE_STOPS = 30;

/** [west, south, east, north] covering the archipelago. */
export const INDONESIA_BOUNDS: [number, number, number, number] = [94.8, -11.3, 141.3, 6.3];
export const INDONESIA_CENTER: [number, number] = [117.5, -2.5];
/** Camera of the globe at the start of the intro. */
export const INTRO_START = { center: [100, 8] as [number, number], zoom: 1.4 };

export const MOVE_DEBOUNCE_MS = 250;
/** Basemap style fetch/source error budget before the local fallback style takes over (D-23). */
export const BASEMAP_TIMEOUT_MS = 5000;

export const OPENFREEMAP_STYLE_URL = 'https://tiles.openfreemap.org/styles/dark';

export const INTRO_STORAGE_KEY = 'hunter.map.intro-skipped';
export const COLOR_BY_STORAGE_KEY = 'hunter.map.color-by';

export const MAP_MODES: { key: MapMode; label: string; hint: string; shortcut: string }[] = [
    { key: 'prospects', label: 'Prospek', hint: 'Titik dan kolom prospek menurut skor', shortcut: '1' },
    { key: 'heatmap', label: 'Heatmap', hint: 'Kepadatan prospek', shortcut: '2' },
    { key: 'whitespace', label: 'White Space', hint: 'Area padat prospek tanpa outlet dalam 500 m', shortcut: '3' },
    { key: 'coverage', label: 'Cakupan', hint: 'Cincin 2 km di sekitar setiap outlet', shortcut: '4' },
];

export const ATTRIBUTION = {
    text: 'OpenFreeMap © OpenMapTiles Data from OpenStreetMap',
    links: [
        { label: 'OpenFreeMap', href: 'https://openfreemap.org' },
        { label: 'OpenMapTiles', href: 'https://openmaptiles.org/' },
        { label: 'OpenStreetMap', href: 'https://www.openstreetmap.org/copyright' },
    ],
};
