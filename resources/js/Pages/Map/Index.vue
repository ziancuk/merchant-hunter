<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppShell from '@/Layouts/AppShell.vue';
import Button from '@/Components/ui/Button.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import type { CommandItem } from '@/Components/ui/CommandPalette.vue';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import ProspectDrawer, { type RejectionReason } from '@/Components/prospect/ProspectDrawer.vue';
import { useDebounceFn } from '@/composables/useDebounce';
import { useFilters } from '@/composables/useFilters';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { useShortcuts } from '@/composables/useShortcuts';
import { api, normalizeError } from '@/lib/api';
import { scoreBand } from '@/lib/colors';
import { BAND_LABEL, STATUS_LABEL } from '@/lib/labels';
import { formatNumber } from '@/lib/format';
import type { MapMode, ProspectDetail, RoutePlan, SharedProps } from '@/types';
import { ATTRIBUTION, COLOR_BY_STORAGE_KEY, COLUMN_ZOOM, GRID_ZOOM, MAP_MODES, MOVE_DEBOUNCE_MS } from '@/map/constants';
import { drawerPadding } from '@/map/geometry';
import { isGridPayload, isPointsPayload, parseGrid, parseOutlets, parsePoints, parseWhitespace, decodeFlags, statusFromIndex } from '@/map/payload';
import { prepareGrid, preparePoints, recolor } from '@/map/prepare';
import { parseMapParams, readIntroSkipped, readPref, resolveMotionFlags, withProspectParam, writeIntroSkipped, writePref } from '@/map/params';
import { buildProspectsUrl, buildSearchUrl, buildWhitespaceUrl } from '@/map/query';
import { addStop, applyPlan, planBody, planBounds, removeStop, moveStop, toggleStop } from '@/map/route';
import { searchToItems, type SearchResponse } from '@/map/search';
import type { MapEngine } from '@/map/engine';
import { toMapPick, type MapPick } from '@/map/picking';
import type { ColorBy, OutletData, PreparedGrid, PreparedPoints, RouteStop, Viewport, WhitespaceData } from '@/map/types';
import MapHud, { type HudProps } from '@/map/MapHud.vue';
import MapLegend from '@/map/MapLegend.vue';
import ModeSwitcher from '@/map/ModeSwitcher.vue';
import RoutePanel from '@/map/RoutePanel.vue';

/* eslint-disable vue/prop-name-casing -- Inertia props are snake_case by contract (docs/CONTRACTS.md §1) */
const props = defineProps<{
    filter_options: FilterOptions;
    hud: HudProps;
    provinces_index: { id: number; name: string; lat: number | null; lng: number | null }[];
    rejection_reasons: RejectionReason[];
    contact_rules?: unknown;
}>();
/* eslint-enable vue/prop-name-casing */

const page = usePage();
const app = computed(() => (page.props as unknown as SharedProps).app);
const reducedMotion = useReducedMotion();

// --- non-reactive engine objects (D-14) -------------------------------------------------------------------------
let engine: MapEngine | null = null;
let buildLayersFn: typeof import('@/map/engine').buildLayers | null = null;
let dataController: AbortController | null = null;
let hoverController: AbortController | null = null;
let layerFrame = 0;
let pulseFrame = 0;
let lastPulseTick = 0;
let unmounted = false;
const nameCache = new Map<number, { name: string; category: string }>();

// --- UI state ------------------------------------------------------------------------------------------------------
const container = ref<HTMLElement | null>(null);
const mode = ref<MapMode>('prospects');
const colorBy = ref<ColorBy>(readPref(COLOR_BY_STORAGE_KEY) === 'status' ? 'status' : 'score');
const { filter, activeCount, reset: resetFilter } = useFilters();
const filterOpen = ref(typeof window !== 'undefined' && window.matchMedia?.('(min-width: 1024px)').matches === true);

const ready = ref(false);
const mapError = ref('');
const introActive = ref(false);
const basemapFailed = ref(false);
const loading = ref(false);
const loadError = ref('');
const zoom = ref(4);
const zoomBucket = ref(4);
const tilted = ref(false);
const columnZoom = ref(COLUMN_ZOOM);

const points = shallowRef<PreparedPoints | null>(null);
const grid = shallowRef<PreparedGrid | null>(null);
const outlets = shallowRef<OutletData | null>(null);
const whitespace = shallowRef<WhitespaceData | null>(null);

const hover = shallowRef<MapPick | null>(null);
const hoverMeta = ref<{ name: string; category: string } | null>(null);
const selectedId = ref<number | null>(null);
const selectedPos = shallowRef<{ lng: number; lat: number } | null>(null);
const selectedOutlet = shallowRef<{ lng: number; lat: number; name: string } | null>(null);
let flyOnLoad = false;

const routeOpen = ref(false);
const routePicking = ref(false);
const routeStops = ref<RouteStop[]>([]);
const routePlan = shallowRef<RoutePlan | null>(null);
const routePlanning = ref(false);
const routeError = ref('');

const urlParams = typeof window !== 'undefined' ? parseMapParams(window.location.search) : parseMapParams('');
const flags = computed(() => resolveMotionFlags({ params: urlParams, env: app.value?.env, introSkipped: readIntroSkipped(), reducedMotion: reducedMotion.value }));
const hasDeepLink = urlParams.prospect !== null || urlParams.route.length > 0 || urlParams.focus !== null;

// --- derived -------------------------------------------------------------------------------------------------------
const count = computed<number | null>(() => {
    if (!ready.value) return null;
    if (mode.value === 'whitespace') return whitespace.value?.total === undefined ? null : whitespace.value.count;
    if (points.value) return points.value.count;
    if (grid.value) return grid.value.total;
    return null;
});
const truncated = computed(() => !!points.value?.truncated && mode.value !== 'whitespace');
const isEmpty = computed(() => ready.value && !introActive.value && !loading.value && !loadError.value && count.value === 0);

const routeShown = computed(() => (routePlan.value ? routePlan.value.path : null));

const tooltip = computed(() => {
    const h = hover.value;
    if (!h) return null;
    if (h.kind === 'prospect' && points.value) {
        const p = points.value;
        const i = h.index;
        if (i >= p.count) return null;
        const f = decodeFlags(p.flags[i]);
        const extras = [f.newOpen ? 'Baru buka' : '', f.simulated ? 'Data simulasi' : '', f.closed ? 'Tutup' : '', f.optOut ? 'Jangan hubungi' : ''].filter(Boolean);
        return {
            title: hoverMeta.value?.name ?? `Prospek #${p.ids[i]}`,
            lines: [
                hoverMeta.value?.category ?? '',
                `Skor ${p.scores[i]} (${BAND_LABEL[scoreBand(p.scores[i])]}) · ${STATUS_LABEL[statusFromIndex(p.status[i])]}`,
                extras.join(' · '),
                routePicking.value ? 'Klik untuk tambah/hapus dari rute' : 'Klik untuk detail',
            ].filter(Boolean),
            x: h.x,
            y: h.y,
        };
    }
    if (h.kind === 'outlet' && outlets.value && h.index < outlets.value.count) {
        const o = outlets.value;
        return { title: o.names[h.index], lines: [`Outlet ${o.codes[h.index]}`, 'Cincin 2 km ditampilkan'], x: h.x, y: h.y };
    }
    if (h.kind === 'grid' && grid.value && h.index < grid.value.count) {
        const g = grid.value;
        return {
            title: `${formatNumber(g.counts[h.index])} prospek`,
            lines: [`Skor rata-rata ${formatNumber(g.avgScores[h.index], 1)}`, `${formatNumber(g.hots[h.index])} panas`, 'Klik untuk memperbesar'],
            x: h.x,
            y: h.y,
        };
    }
    if (h.kind === 'whitespace' && whitespace.value && h.index < whitespace.value.count) {
        const c = whitespace.value.cells[h.index];
        return {
            title: `${formatNumber(c.prospects)} prospek tanpa outlet`,
            lines: [`Outlet terdekat ${formatNumber(c.nearest_outlet_m)} m`, `Skor rata-rata ${formatNumber(c.avg_score)}`],
            x: h.x,
            y: h.y,
        };
    }
    return null;
});

const tooltipStyle = computed(() => {
    const t = tooltip.value;
    if (!t) return {};
    const w = container.value?.clientWidth ?? 1000;
    const flip = t.x > w - 260;
    return { left: `${flip ? t.x - 14 : t.x + 14}px`, top: `${t.y + 14}px`, transform: flip ? 'translateX(-100%)' : undefined };
});

/** Index of the outlet under the cursor (-1: none). A number so unrelated mouse moves do not retrigger layer builds. */
const hoverOutletIndex = computed(() => (hover.value?.kind === 'outlet' && outlets.value && hover.value.index < outlets.value.count ? hover.value.index : -1));

const focusOutlet = computed(() => {
    const i = hoverOutletIndex.value;
    if (i >= 0 && outlets.value) return { lng: outlets.value.positions[2 * i], lat: outlets.value.positions[2 * i + 1] };
    return selectedOutlet.value ? { lng: selectedOutlet.value.lng, lat: selectedOutlet.value.lat } : null;
});

// --- layers --------------------------------------------------------------------------------------------------------
function applyLayers(): void {
    if (!engine || !buildLayersFn) return;
    engine.setLayers(
        buildLayersFn({
            mode: mode.value,
            zoom: zoomBucket.value,
            columnZoom: columnZoom.value,
            colorBy: colorBy.value,
            points: points.value,
            grid: grid.value,
            outlets: outlets.value,
            whitespace: whitespace.value,
            route: { stops: routeStops.value, path: routeShown.value },
            selected: selectedPos.value,
            focusOutlet: focusOutlet.value,
            pulse: pulsePhase,
            handlers: { onHover },
        }),
    );
}

let pulsePhase: number | null = null;

function scheduleLayers(): void {
    if (layerFrame || unmounted) return;
    layerFrame = requestAnimationFrame(() => {
        layerFrame = 0;
        applyLayers();
    });
}

function pulseTick(now: number): void {
    pulseFrame = 0;
    if (unmounted) return;
    const wanted = flags.value.motion && !introActive.value && !document.hidden && (mode.value === 'prospects' || mode.value === 'coverage') && (points.value?.newOpen.length ?? 0) > 0;
    if (!wanted) {
        if (pulsePhase !== null) {
            pulsePhase = null;
            scheduleLayers();
        }
        pulseFrame = window.setTimeout(() => pulseTick(performance.now()), 600) as unknown as number;
        return;
    }
    if (now - lastPulseTick >= 45) {
        lastPulseTick = now;
        pulsePhase = (now % 1800) / 1800;
        scheduleLayers();
    }
    pulseFrame = requestAnimationFrame(pulseTick);
}

watch([points, grid, outlets, whitespace, mode, zoomBucket, columnZoom, routeStops, routePlan, selectedPos, focusOutlet], scheduleLayers);
watch(colorBy, (v) => {
    writePref(COLOR_BY_STORAGE_KEY, v);
    if (points.value) points.value = recolor(points.value, v);
});

// --- data ----------------------------------------------------------------------------------------------------------
let currentViewport: Viewport | null = null;

interface LoadStats {
    kind: string;
    ms: number;
    parseMs: number;
    bytes: number;
    count: number;
    url: string;
}
let lastLoad: LoadStats | null = null;

async function getJson<T>(url: string, signal: AbortSignal): Promise<{ data: T; bytes: number; ms: number; parseMs: number }> {
    const t0 = performance.now();
    const res = await api.get<string>(url, { signal, responseType: 'text', transformResponse: (r: string) => r });
    const t1 = performance.now();
    const data = JSON.parse(res.data) as T;
    return { data, bytes: res.data.length, ms: t1 - t0, parseMs: performance.now() - t1 };
}

async function loadViewport(): Promise<void> {
    const vp = currentViewport;
    if (!vp || !ready.value || introActive.value) return;
    dataController?.abort();
    const ctrl = new AbortController();
    dataController = ctrl;
    loading.value = true;
    loadError.value = '';
    try {
        if (mode.value === 'whitespace') {
            const url = buildWhitespaceUrl({ bbox: vp.bbox, filter: filter.value });
            const r = await getJson<import('@/map/types').WhitespaceResponse>(url, ctrl.signal);
            whitespace.value = parseWhitespace(r.data);
            lastLoad = { kind: 'whitespace', ms: r.ms, parseMs: r.parseMs, bytes: r.bytes, count: whitespace.value.count, url };
        } else {
            const url = buildProspectsUrl({ bbox: vp.bbox, zoom: vp.zoom, mode: mode.value, filter: filter.value });
            const r = await getJson<import('@/types').MapPointsResponse>(url, ctrl.signal);
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
        if (dataController === ctrl) loading.value = false;
    }
}

let loadsFrozen = false; // performance harness only (window.__hunterMap.freezeLoads)
const loadViewportDebounced = useDebounceFn(() => {
    if (!loadsFrozen) void loadViewport();
}, MOVE_DEBOUNCE_MS);

async function loadOutlets(): Promise<void> {
    try {
        const ctrl = new AbortController();
        const r = await getJson<import('@/map/types').OutletsResponse>('/api/map/outlets', ctrl.signal);
        outlets.value = parseOutlets(r.data);
    } catch (e) {
        if (!axios.isCancel(e)) loadError.value = normalizeError(e).message;
    }
}

watch(
    filter,
    () => {
        loadViewportDebounced();
    },
    { deep: true },
);
watch(mode, () => {
    hover.value = null;
    loadViewportDebounced();
});
watch(
    () => filter.value.province_id,
    (id) => {
        const p = id ? props.provinces_index.find((x) => x.id === id) : null;
        if (p && p.lat !== null && p.lng !== null) engine?.flyTo({ center: [p.lng, p.lat], zoom: 7, duration: 1800 });
    },
);

// --- hover / click -------------------------------------------------------------------------------------------------
function onHover(pick: MapPick | null): void {
    hover.value = pick;
    engine?.setCursor(pick ? 'pointer' : '');
}

/** Identity of the hovered object: stable while the cursor moves over the same prospect/outlet/cell. */
const hoverKey = computed(() => (hover.value ? `${hover.value.kind}:${hover.value.index}` : ''));

watch(hoverKey, () => {
    const h = hover.value;
    hoverController?.abort();
    hoverMeta.value = null;
    if (!h || h.kind !== 'prospect' || !points.value || h.index >= points.value.count) return;
    const id = points.value.ids[h.index];
    const cached = nameCache.get(id);
    if (cached) {
        hoverMeta.value = cached;
        return;
    }
    fetchMetaDebounced(id);
});

const fetchMetaDebounced = useDebounceFn((id: number) => {
    void fetchMeta(id).then((m) => {
        const h = hover.value;
        if (m && h?.kind === 'prospect' && points.value && points.value.ids[h.index] === id) hoverMeta.value = m;
    });
}, 140);

function unwrapDetail(raw: unknown): ProspectDetail {
    const r = raw as { data?: ProspectDetail; prospect?: ProspectDetail } & Partial<ProspectDetail>;
    if (r && r.id === undefined) return (r.data ?? r.prospect) as ProspectDetail;
    return r as ProspectDetail;
}

async function fetchMeta(id: number): Promise<{ name: string; category: string } | null> {
    const cached = nameCache.get(id);
    if (cached) return cached;
    hoverController?.abort();
    const ctrl = new AbortController();
    hoverController = ctrl;
    try {
        const d = unwrapDetail((await api.get(`/api/prospects/${id}`, { signal: ctrl.signal })).data);
        const meta = { name: d.name, category: d.category?.name ?? '' };
        if (nameCache.size > 600) nameCache.clear();
        nameCache.set(id, meta);
        return meta;
    } catch {
        return null;
    }
}

function onMapPick(p: { layerId: string; index: number; x: number; y: number } | null): void {
    const pick = toMapPick(p);
    if (pick) onClick(pick);
}

function onClick(pick: MapPick): void {
    if (pick.kind === 'prospect' && points.value) {
        const i = pick.index;
        const id = points.value.ids[i];
        const lng = points.value.positions[2 * i];
        const lat = points.value.positions[2 * i + 1];
        if (routePicking.value) {
            pickForRoute({ id, name: nameCache.get(id)?.name ?? null, lng, lat });
            return;
        }
        openProspect(id, { lng, lat, fly: true });
    } else if (pick.kind === 'outlet' && outlets.value) {
        const o = outlets.value;
        selectOutlet(o.positions[2 * pick.index], o.positions[2 * pick.index + 1], o.names[pick.index]);
    } else if (pick.kind === 'grid' && grid.value) {
        const g = grid.value;
        engine?.flyTo({ center: [g.positions[2 * pick.index], g.positions[2 * pick.index + 1]], zoom: Math.max(GRID_ZOOM + 1, zoom.value + 2), duration: 1200 });
    } else if (pick.kind === 'whitespace' && whitespace.value) {
        const c = whitespace.value.cells[pick.index];
        engine?.flyTo({ center: [c.lng, c.lat], zoom: 14, duration: 1200 });
    }
}

function selectOutlet(lng: number, lat: number, name: string): void {
    selectedOutlet.value = { lng, lat, name };
    engine?.flyTo({ center: [lng, lat], zoom: Math.max(zoom.value, 12), duration: 1400 });
}

// --- prospect drawer -----------------------------------------------------------------------------------------------
function syncUrl(id: number | null): void {
    try {
        window.history.replaceState(window.history.state, '', withProspectParam(window.location.pathname + window.location.search + window.location.hash, id));
    } catch {
        /* history API unavailable: deep link just is not mirrored */
    }
}

function openProspect(id: number, opts: { lng?: number; lat?: number; fly?: boolean } = {}): void {
    selectedId.value = id;
    syncUrl(id);
    if (opts.lng !== undefined && opts.lat !== undefined) {
        selectedPos.value = { lng: opts.lng, lat: opts.lat };
        if (opts.fly) flyToProspect(opts.lng, opts.lat);
        flyOnLoad = false;
    } else {
        selectedPos.value = null;
        flyOnLoad = true;
    }
}

function flyToProspect(lng: number, lat: number): void {
    engine?.flyTo({ center: [lng, lat], zoom: Math.max(zoom.value, 14), pitch: flags.value.motion ? 45 : undefined, padding: drawerPadding(window.innerWidth, true), duration: 1300 });
}

/** Drawer loader: GET /api/prospects/{id}, then frame the prospect when it was opened without coordinates (deep link/search). */
async function drawerLoader(id: number): Promise<ProspectDetail> {
    const d = unwrapDetail((await api.get(`/api/prospects/${id}`)).data);
    nameCache.set(id, { name: d.name, category: d.category?.name ?? '' });
    if (selectedId.value === id) {
        selectedPos.value = { lng: d.lng, lat: d.lat };
        if (flyOnLoad) {
            flyOnLoad = false;
            flyToProspect(d.lng, d.lat);
        }
    }
    return d;
}

function closeDrawer(): void {
    selectedId.value = null;
    selectedPos.value = null;
    syncUrl(null);
}

function onProspectChanged(p: ProspectDetail): void {
    nameCache.set(p.id, { name: p.name, category: p.category?.name ?? '' });
    loadViewportDebounced();
}

function locate(c: { lat: number; lng: number }): void {
    engine?.flyTo({ center: [c.lng, c.lat], zoom: 16, padding: drawerPadding(window.innerWidth, true), duration: 1200 });
}

// --- route planner -------------------------------------------------------------------------------------------------
function pickForRoute(stop: RouteStop): void {
    const r = toggleStop(routeStops.value, stop);
    routeStops.value = r.list;
    if (!r.added && !r.removed && r.reason === 'limit') routeError.value = 'Maksimal 30 prospek per rute.';
    else routeError.value = '';
    if (r.added && stop.name === null) void fillStopName(stop.id);
}

function addToRoute(p: ProspectDetail): void {
    routeOpen.value = true;
    filterOpen.value = false;
    const r = addStop(routeStops.value, { id: p.id, name: p.name, lat: p.lat, lng: p.lng });
    routeStops.value = r.list;
    routeError.value = r.reason === 'limit' ? 'Maksimal 30 prospek per rute.' : '';
}

async function fillStopName(id: number): Promise<void> {
    const meta = await fetchMeta(id);
    if (meta) routeStops.value = routeStops.value.map((s) => (s.id === id ? { ...s, name: meta.name } : s));
}

watch(routeStops, (stops) => {
    const plan = routePlan.value;
    if (plan && plan.order.join(',') !== stops.map((s) => s.id).join(',')) routePlan.value = null;
});

async function planRoute(ids?: number[]): Promise<void> {
    routePlanning.value = true;
    routeError.value = '';
    try {
        const body = ids ? { prospect_ids: ids } : planBody(routeStops.value);
        const plan = (await api.post<RoutePlan>('/api/routes/plan', body)).data;
        routeStops.value = applyPlan(routeStops.value.length ? routeStops.value : ids!.map((id) => ({ id, name: null, lat: 0, lng: 0 })), plan);
        routePlan.value = plan;
        const b = planBounds(plan);
        if (b) engine?.fitBbox(b, { padding: { top: 110, bottom: 130, left: 120, right: 120 }, maxZoom: 15 });
    } catch (e) {
        const err = normalizeError(e);
        routeError.value = Object.values(err.errors)[0]?.[0] ?? err.message;
    } finally {
        routePlanning.value = false;
    }
}

function clearRoute(): void {
    routeStops.value = [];
    routePlan.value = null;
    routeError.value = '';
}

function focusStop(s: RouteStop): void {
    if (s.lat !== 0 || s.lng !== 0) engine?.flyTo({ center: [s.lng, s.lat], zoom: Math.max(zoom.value, 15), duration: 1000 });
}

function toggleFilter(): void {
    filterOpen.value = !filterOpen.value;
    if (filterOpen.value) routeOpen.value = false;
}

function toggleRoute(): void {
    routeOpen.value = !routeOpen.value;
    if (routeOpen.value) filterOpen.value = false;
}

watch(routeOpen, (open) => {
    if (!open) routePicking.value = false;
});

// --- search / command palette ----------------------------------------------------------------------------------------
async function search(q: string): Promise<CommandItem[]> {
    const { data } = await api.get<SearchResponse>(buildSearchUrl(q));
    return searchToItems(data, {
        onOutlet: (o) => {
            selectedOutlet.value = { lng: o.lng, lat: o.lat, name: o.name };
            engine?.flyTo({ center: [o.lng, o.lat], zoom: 13, duration: 1600 });
        },
        onProspect: (p) => {
            openProspect(p.id, { lng: p.lng, lat: p.lat, fly: true });
            if (mode.value === 'whitespace') mode.value = 'prospects';
        },
        onProvince: (p) => engine?.flyTo({ center: [p.lng, p.lat], zoom: 7, duration: 1800 }),
    });
}

const commandItems = computed<CommandItem[]>(() => [
    ...MAP_MODES.map((m) => ({ id: `mode:${m.key}`, label: `Mode peta: ${m.label}`, hint: m.shortcut, group: 'Peta', action: () => (mode.value = m.key) })),
    { id: 'act:reset-filter', label: 'Atur ulang filter', group: 'Peta', action: () => resetFilter() },
    { id: 'act:filter', label: filterOpen.value ? 'Sembunyikan panel filter' : 'Tampilkan panel filter', hint: 'F', group: 'Peta', action: toggleFilter },
    { id: 'act:route', label: routeOpen.value ? 'Tutup perencana rute' : 'Buka perencana rute', hint: 'R', group: 'Peta', action: toggleRoute },
    { id: 'act:reset-view', label: 'Kembali ke tampilan Indonesia', group: 'Peta', action: () => engine?.resetView() },
]);

useShortcuts([
    ...MAP_MODES.map((m) => ({ keys: m.shortcut, description: `Mode peta: ${m.label}`, group: 'Peta', handler: () => (mode.value = m.key) })),
    { keys: 'r', description: 'Buka/tutup perencana rute', group: 'Peta', handler: toggleRoute },
    { keys: 'f', description: 'Tampilkan/sembunyikan filter', group: 'Peta', handler: toggleFilter },
]);

// --- intro / lifecycle ---------------------------------------------------------------------------------------------
function skipIntro(): void {
    writeIntroSkipped(true);
    engine?.skipIntro();
}

function onViewport(vp: Viewport): void {
    currentViewport = vp;
    if (introActive.value) return;
    loadViewportDebounced();
}

function onMove(vp: Viewport): void {
    // Camera moved under a stationary cursor (fly-to, zoom buttons): deck.gl does not re-pick, so drop the stale hover.
    if (hover.value) onHover(null);
    zoom.value = vp.zoom;
    const bucket = Math.round(vp.zoom * 4) / 4;
    if (bucket !== zoomBucket.value) zoomBucket.value = bucket;
    tilted.value = vp.pitch > 10;
}

function handleDeepLinks(): void {
    if (urlParams.mode) mode.value = urlParams.mode;
    if (urlParams.focus) engine?.flyTo({ center: [urlParams.focus.lng, urlParams.focus.lat], zoom: 13, duration: flags.value.motion ? 1400 : 0 });
    if (urlParams.prospect !== null) openProspect(urlParams.prospect);
    if (urlParams.route.length > 0) {
        routeOpen.value = true;
        filterOpen.value = false;
        if (urlParams.route.length >= 2) void planRoute(urlParams.route);
        else void fetchOneStop(urlParams.route[0]);
    }
}

async function fetchOneStop(id: number): Promise<void> {
    try {
        const d = unwrapDetail((await api.get(`/api/prospects/${id}`)).data);
        routeStops.value = addStop(routeStops.value, { id: d.id, name: d.name, lat: d.lat, lng: d.lng }).list;
    } catch (e) {
        routeError.value = normalizeError(e).message;
    }
}

async function startMap(): Promise<void> {
    if (!container.value) return;
    let EngineCtor: typeof import('@/map/engine').MapEngine;
    try {
        const eng = await import('@/map/engine');
        EngineCtor = eng.MapEngine;
        buildLayersFn = eng.buildLayers;
    } catch (e) {
        mapError.value = e instanceof Error ? e.message : 'Modul peta gagal dimuat.';
        return;
    }
    if (unmounted) return;
    const playIntro = flags.value.intro && !hasDeepLink;
    engine = new EngineCtor({
        container: container.value,
        basemap: app.value?.basemap ?? 'local',
        globe: playIntro,
        motion: flags.value.motion,
        onMove,
        onMoveEnd: onViewport,
        onPick: onMapPick,
        onBasemapFallback: () => (basemapFailed.value = true),
        onError: (m) => (mapError.value = m),
        onReady: () => {
            ready.value = true;
            void loadOutlets();
            scheduleLayers();
            if (playIntro) {
                introActive.value = true;
                void engine!.runIntro().then(() => {
                    introActive.value = false;
                    handleDeepLinks();
                    loadViewportDebounced.flush();
                    void loadViewport();
                });
            } else {
                handleDeepLinks();
            }
        },
    });
    if (!engine.map) engine = null;
    exposeDebugHooks();
}

function exposeDebugHooks(): void {
    if (app.value?.env === 'production') return;
    (window as unknown as Record<string, unknown>).__hunterMap = {
        get map() {
            return engine?.map ?? null;
        },
        get lastLoad() {
            return lastLoad;
        },
        get layerIds() {
            return engine?.overlay ? ((engine.overlay as unknown as { _deck?: { props?: { layers?: { id: string }[] } } })._deck?.props?.layers ?? []).map((l) => l.id) : [];
        },
        flyTo: (lng: number, lat: number, z = 12) => engine?.flyTo({ center: [lng, lat], zoom: z, duration: 0 }),
        setMode: (m: MapMode) => (mode.value = m),
        /** Performance harness: request an arbitrary viewport (e.g. the whole country at zoom 6) without moving the camera. */
        loadAt: async (bbox: [number, number, number, number], z: number) => {
            if (currentViewport) currentViewport = { ...currentViewport, bbox, zoom: z };
            await loadViewport();
        },
        setColumnZoom: (z: number) => (columnZoom.value = z),
        freezeLoads: (v: boolean) => (loadsFrozen = v),
    };
}

onMounted(() => {
    void nextTick(startMap);
    pulseFrame = requestAnimationFrame(pulseTick);
});

onBeforeUnmount(() => {
    unmounted = true;
    dataController?.abort();
    hoverController?.abort();
    loadViewportDebounced.cancel();
    fetchMetaDebounced.cancel();
    if (layerFrame) cancelAnimationFrame(layerFrame);
    if (pulseFrame) {
        cancelAnimationFrame(pulseFrame);
        clearTimeout(pulseFrame);
    }
    engine?.destroy();
    engine = null;
    buildLayersFn = null;
    nameCache.clear();
    delete (window as unknown as Record<string, unknown>).__hunterMap;
});

function reloadPage(): void {
    window.location.reload();
}

const liveSummary = computed(() => (count.value === null ? '' : `${formatNumber(count.value)} ${mode.value === 'whitespace' ? 'sel white space' : 'prospek'} ditampilkan di peta.`));
</script>

<template>
    <Head title="Peta" />
    <AppShell full-bleed :command-items="commandItems" :search="search">
        <div class="absolute inset-0 bg-ink">
            <div class="absolute inset-0">
                <div ref="container" class="h-full w-full" role="region" aria-label="Peta prospek" data-testid="map-canvas" :data-ready="ready" :data-intro="introActive" :data-mode="mode" />
            </div>

            <p class="sr-only" role="status" aria-live="polite">{{ liveSummary }}</p>

            <!-- Initial loading skeleton -->
            <div v-if="!ready && !mapError" class="absolute inset-0 grid place-items-center" data-testid="map-loading">
                <GlassPanel class="flex items-center gap-3" role="status">
                    <span class="h-3 w-3 animate-pulse-ring rounded-full bg-hot" aria-hidden="true" />
                    <span class="text-sm">Memuat peta…</span>
                </GlassPanel>
            </div>

            <!-- WebGL / module failure -->
            <div v-if="mapError" class="absolute inset-0 grid place-items-center p-4" data-testid="map-error">
                <GlassPanel>
                    <ErrorState title="Peta tidak dapat ditampilkan" :message="`${mapError} Pastikan browser mendukung WebGL2, lalu muat ulang halaman.`" retry-label="Muat ulang" @retry="reloadPage" />
                </GlassPanel>
            </div>

            <!-- Basemap fallback banner -->
            <div v-if="basemapFailed" class="absolute left-1/2 top-[4.5rem] z-10 -translate-x-1/2 px-3" data-testid="basemap-banner">
                <GlassPanel class="flex items-center gap-2 !px-3 !py-2 text-sm" role="status">
                    <Icon name="alert" :size="16" class="text-warm" />
                    Basemap tidak tersedia. Menampilkan peta dasar lokal.
                </GlassPanel>
            </div>

            <!-- Tooltip -->
            <div v-if="tooltip" class="pointer-events-none absolute z-10 max-w-[16rem]" :style="tooltipStyle" role="tooltip" data-testid="map-tooltip">
                <GlassPanel class="!px-3 !py-2" rounded="chip">
                    <p class="text-sm font-medium leading-snug">{{ tooltip.title }}</p>
                    <p v-for="l in tooltip.lines" :key="l" class="text-xs text-muted">{{ l }}</p>
                </GlassPanel>
            </div>

            <!-- Data error -->
            <div v-if="loadError && ready" class="absolute left-1/2 top-[4.5rem] z-10 -translate-x-1/2 px-3" data-testid="map-load-error">
                <GlassPanel :padded="false">
                    <ErrorState title="Gagal memuat data peta" :message="loadError" :retrying="loading" @retry="loadViewport" />
                </GlassPanel>
            </div>

            <!-- Empty -->
            <div v-if="isEmpty" class="absolute inset-x-0 bottom-28 z-10 flex justify-center px-3 md:bottom-16" data-testid="map-empty">
                <GlassPanel :padded="false" class="flex items-center gap-3 !px-4 !py-3" role="status">
                    <Icon name="search" :size="18" class="text-muted" />
                    <div class="text-sm">
                        <p class="font-medium">Tidak ada {{ mode === 'whitespace' ? 'area white space' : 'prospek' }} di tampilan ini</p>
                        <p class="text-xs text-muted">Geser atau perkecil peta{{ activeCount ? ', atau longgarkan filter' : '' }}.</p>
                    </div>
                    <Button v-if="activeCount" size="sm" variant="secondary" @click="resetFilter()">Atur ulang filter</Button>
                </GlassPanel>
            </div>

            <!-- Intro caption + skip -->
            <div v-if="introActive" class="absolute inset-x-0 bottom-28 z-10 flex flex-col items-center gap-3 px-4 text-center md:bottom-14" data-testid="map-intro">
                <p class="font-display text-xl font-semibold tracking-tight text-fg drop-shadow md:text-3xl">Memindai peluang merchant di seluruh Indonesia</p>
                <Button variant="secondary" size="md" data-testid="skip-intro" @click="skipIntro">Lewati intro</Button>
            </div>
        </div>

        <template #top-left>
            <section v-if="ready && !introActive" class="flex w-[min(22rem,calc(100vw-1.5rem))] flex-col gap-2" aria-label="Mode, filter, dan rute">
                <ModeSwitcher v-model="mode" />
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="secondary" class="glass !h-9" :aria-expanded="filterOpen" aria-controls="map-filter" data-testid="filter-toggle" @click="toggleFilter">
                        <Icon name="filter" :size="14" /> Filter
                        <span v-if="activeCount" class="num rounded-full bg-hot px-1.5 text-2xs text-on-hot" :aria-label="`${activeCount} filter aktif`">{{ activeCount }}</span>
                    </Button>
                    <Button size="sm" variant="secondary" class="glass !h-9" :aria-expanded="routeOpen" aria-controls="map-route" data-testid="route-toggle" @click="toggleRoute">
                        <Icon name="navigation" :size="14" /> Rute
                        <span v-if="routeStops.length" class="num rounded-full bg-focus px-1.5 text-2xs text-ink">{{ routeStops.length }}</span>
                    </Button>
                    <div class="sm:hidden"><MapHud :hud="hud" :count="count" :mode="mode" :loading="loading" :truncated="truncated" compact /></div>
                </div>
                <div v-show="filterOpen" id="map-filter" class="max-h-[calc(100dvh-15rem)] overflow-y-auto rounded-panel">
                    <FilterPanel v-model="filter" :options="filter_options" :collapsible="false" />
                </div>
                <div v-if="routeOpen" id="map-route">
                    <RoutePanel
                        v-model:picking="routePicking"
                        :stops="routeStops"
                        :plan="routePlan"
                        :planning="routePlanning"
                        :error="routeError"
                        @remove="(id) => (routeStops = removeStop(routeStops, id))"
                        @move="(from, to) => (routeStops = moveStop(routeStops, from, to))"
                        @focus="focusStop"
                        @plan="planRoute()"
                        @clear="clearRoute"
                        @close="routeOpen = false"
                    />
                </div>
            </section>
        </template>

        <template #top-right>
            <div v-if="ready && !introActive" class="hidden sm:block"><MapHud :hud="hud" :count="count" :mode="mode" :loading="loading" :truncated="truncated" /></div>
        </template>

        <template #bottom-left>
            <section v-if="ready" class="flex flex-col items-start gap-2" aria-label="Atribusi peta">
                <p class="glass max-w-[calc(100vw-5.5rem)] rounded-chip px-2 py-1 text-2xs text-muted sm:max-w-[calc(100vw-21rem)]" data-testid="map-attribution">
                    {{ ATTRIBUTION.text }} ·
                    <template v-for="(l, i) in ATTRIBUTION.links" :key="l.href">
                        <a :href="l.href" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2 hover:text-fg">{{ l.label }}</a
                        ><span v-if="i < ATTRIBUTION.links.length - 1">, </span>
                    </template>
                </p>
            </section>
        </template>

        <template #bottom-right>
            <div v-if="ready && !introActive" class="flex items-end gap-2">
            <MapLegend v-model:color-by="colorBy" />
            <section class="glass flex flex-col gap-1 rounded-full p-1" aria-label="Kontrol peta">
                <Button icon-only variant="ghost" aria-label="Perbesar" data-testid="zoom-in" @click="engine?.zoomBy(1)"><span aria-hidden="true" class="text-lg leading-none">+</span></Button>
                <Button icon-only variant="ghost" aria-label="Perkecil" data-testid="zoom-out" @click="engine?.zoomBy(-1)"><span aria-hidden="true" class="text-lg leading-none">−</span></Button>
                <Button icon-only variant="ghost" aria-label="Tampilan 3D miring" :aria-pressed="tilted" data-testid="tilt" @click="engine?.toggleTilt()"><span aria-hidden="true" class="text-2xs font-semibold">3D</span></Button>
                <Button icon-only variant="ghost" aria-label="Kembali ke Indonesia" data-testid="reset-view" @click="engine?.resetView()"><Icon name="globe" :size="16" /></Button>
            </section>
            </div>
        </template>
    </AppShell>

    <ProspectDrawer :prospect-id="selectedId" :loader="drawerLoader" :rejection-reasons="rejection_reasons" @close="closeDrawer" @updated="onProspectChanged" @contacted="onProspectChanged" @locate="locate">
        <template #actions="{ prospect }">
            <Button size="sm" variant="secondary" data-testid="add-to-route" @click="addToRoute(prospect)"><Icon name="pin" :size="14" /> Tambah ke rute</Button>
        </template>
    </ProspectDrawer>
</template>
