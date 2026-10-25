import { computed, ref, shallowRef, watch, onScopeDispose, type Ref } from 'vue';
import axios from 'axios';
import { useDebounceFn } from '@/composables/useDebounce';
import { api, normalizeError } from '@/lib/api';
import type { MapMode, MapPointsResponse, ProspectFilter } from '@/types';
import { COLOR_BY_STORAGE_KEY, MOVE_DEBOUNCE_MS } from './constants';
import { writePref } from './params';
import { isGridPayload, isPointsPayload, parseGrid, parseOutlets, parsePoints, parseWhitespace } from './payload';
import { prepareGrid, preparePoints, recolor } from './prepare';
import { buildProspectsUrl, buildWhitespaceUrl } from './query';
import type { ColorBy, OutletData, OutletsResponse, PreparedGrid, PreparedPoints, Viewport, WhitespaceData, WhitespaceResponse } from './types';

export interface LoadStats {
    kind: string;
    ms: number;
    parseMs: number;
    bytes: number;
    count: number;
    url: string;
}

export interface MapDataDeps {
    mode: Ref<MapMode>;
    colorBy: Ref<ColorBy>;
    filter: Ref<ProspectFilter>;
    ready: Ref<boolean>;
    introActive: Ref<boolean>;
}

async function getJson<T>(url: string, signal: AbortSignal): Promise<{ data: T; bytes: number; ms: number; parseMs: number }> {
    const t0 = performance.now();
    const res = await api.get<string>(url, { signal, responseType: 'text', transformResponse: (r: string) => r });
    const t1 = performance.now();
    const data = JSON.parse(res.data) as T;
    return { data, bytes: res.data.length, ms: t1 - t0, parseMs: performance.now() - t1 };
}

/** Viewport-driven data loading for the map: points/grid/white space/outlets, cancellation, debounce and derived counts. */
export function useMapData(deps: MapDataDeps) {
    const { mode, colorBy, filter, ready, introActive } = deps;

    const points = shallowRef<PreparedPoints | null>(null);
    const grid = shallowRef<PreparedGrid | null>(null);
    const outlets = shallowRef<OutletData | null>(null);
    const whitespace = shallowRef<WhitespaceData | null>(null);
    const loading = ref(false);
    const loadError = ref('');

    let controller: AbortController | null = null;
    let viewport: Viewport | null = null;
    let lastLoad: LoadStats | null = null;
    let frozen = false; // performance harness only (window.__hunterMap.freezeLoads)

    async function loadViewport(): Promise<void> {
        const vp = viewport;
        if (!vp || !ready.value || introActive.value) return;
        controller?.abort();
        const ctrl = new AbortController();
        controller = ctrl;
        loading.value = true;
        loadError.value = '';
        try {
            if (mode.value === 'whitespace') {
                const url = buildWhitespaceUrl({ bbox: vp.bbox, filter: filter.value });
                const r = await getJson<WhitespaceResponse>(url, ctrl.signal);
                whitespace.value = parseWhitespace(r.data);
                lastLoad = { kind: 'whitespace', ms: r.ms, parseMs: r.parseMs, bytes: r.bytes, count: whitespace.value.count, url };
            } else {
                const url = buildProspectsUrl({ bbox: vp.bbox, zoom: vp.zoom, mode: mode.value, filter: filter.value });
                const r = await getJson<MapPointsResponse>(url, ctrl.signal);
                const t = performance.now();
                if (isPointsPayload(r.data)) {
                    points.value = preparePoints(parsePoints(r.data), colorBy.value);
                    grid.value = null;
                } else if (isGridPayload(r.data)) {
                    grid.value = prepareGrid(parseGrid(r.data));
                    points.value = null;
                }
                lastLoad = { kind: r.data.type, ms: r.ms, parseMs: r.parseMs + (performance.now() - t), bytes: r.bytes, count: points.value?.count ?? grid.value?.total ?? 0, url };
            }
        } catch (e) {
            if (axios.isCancel(e) || (e as { code?: string })?.code === 'ERR_CANCELED') return;
            loadError.value = normalizeError(e).message;
        } finally {
            if (controller === ctrl) loading.value = false;
        }
    }

    const loadViewportDebounced = useDebounceFn(() => {
        if (!frozen) void loadViewport();
    }, MOVE_DEBOUNCE_MS);

    async function loadOutlets(): Promise<void> {
        try {
            const ctrl = new AbortController();
            const r = await getJson<OutletsResponse>('/api/map/outlets', ctrl.signal);
            outlets.value = parseOutlets(r.data);
        } catch (e) {
            if (!axios.isCancel(e)) loadError.value = normalizeError(e).message;
        }
    }

    watch(filter, () => loadViewportDebounced(), { deep: true });
    watch(mode, () => loadViewportDebounced());
    watch(colorBy, (v) => {
        writePref(COLOR_BY_STORAGE_KEY, v);
        if (points.value) points.value = recolor(points.value, v);
    });

    const count = computed<number | null>(() => {
        if (!ready.value) return null;
        if (mode.value === 'whitespace') return whitespace.value?.total === undefined ? null : whitespace.value.count;
        if (points.value) return points.value.count;
        if (grid.value) return grid.value.total;
        return null;
    });
    const truncated = computed(() => !!points.value?.truncated && mode.value !== 'whitespace');
    const isEmpty = computed(() => ready.value && !introActive.value && !loading.value && !loadError.value && count.value === 0);

    onScopeDispose(() => {
        controller?.abort();
        loadViewportDebounced.cancel();
    });

    return {
        points,
        grid,
        outlets,
        whitespace,
        loading,
        loadError,
        count,
        truncated,
        isEmpty,
        loadViewport,
        loadViewportDebounced,
        loadOutlets,
        setViewport: (vp: Viewport) => {
            viewport = vp;
        },
        /** Debug/perf harness: request an arbitrary viewport without moving the camera. */
        loadAt: async (bbox: [number, number, number, number], zoom: number) => {
            if (viewport) viewport = { ...viewport, bbox, zoom };
            await loadViewport();
        },
        freezeLoads: (v: boolean) => {
            frozen = v;
        },
        getLastLoad: () => lastLoad,
    };
}
