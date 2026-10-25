<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import ProspectDrawer, { type RejectionReason } from '@/Components/prospect/ProspectDrawer.vue';
import { useFilters } from '@/composables/useFilters';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { formatNumber } from '@/lib/format';
import type { MapMode, SharedProps } from '@/types';
import { COLOR_BY_STORAGE_KEY, COLUMN_ZOOM, GRID_ZOOM } from '@/map/constants';
import { parseMapParams, readIntroSkipped, readPref, resolveMotionFlags, withRouteParam, writeIntroSkipped } from '@/map/params';
import { removeStop, moveStop } from '@/map/route';
import type { MapEngine } from '@/map/engine';
import { toMapPick, type MapPick } from '@/map/picking';
import type { ColorBy, RouteStop, Viewport } from '@/map/types';
import MapHud, { type HudProps } from '@/map/MapHud.vue';
import MapAttribution from '@/map/MapAttribution.vue';
import MapLegend from '@/map/MapLegend.vue';
import MapOverlays from '@/map/MapOverlays.vue';
import ModeSwitcher from '@/map/ModeSwitcher.vue';
import RoutePanel from '@/map/RoutePanel.vue';
import { useMapCommands } from '@/map/useMapCommands';
import { useMapData } from '@/map/useMapData';
import { useMapDebugApi } from '@/map/useMapDebugApi';
import { useMapLayers } from '@/map/useMapLayers';
import { useMapSelection } from '@/map/useMapSelection';
import { useMapTooltip } from '@/map/useMapTooltip';
import { useRoutePlanner } from '@/map/useRoutePlanner';

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

// Non-reactive engine object (D-14): composables reach it through this getter.
let engine: MapEngine | null = null;
const getEngine = () => engine;
let unmounted = false;

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
const zoom = ref(4);
const zoomBucket = ref(4);
const tilted = ref(false);
const columnZoom = ref(COLUMN_ZOOM);

const urlParams = typeof window !== 'undefined' ? parseMapParams(window.location.search) : parseMapParams('');
const flags = computed(() => resolveMotionFlags({ params: urlParams, debugHooks: app.value?.debug_hooks, introSkipped: readIntroSkipped(), reducedMotion: reducedMotion.value }));
const hasDeepLink = urlParams.prospect !== null || urlParams.route.length > 0 || urlParams.focus !== null;
const motion = computed(() => flags.value.motion);

// --- composables ---------------------------------------------------------------------------------------------------
const data = useMapData({ mode, colorBy, filter, ready, introActive });
const { points, grid, outlets, whitespace, loading, loadError, count, truncated, isEmpty, loadViewport, loadViewportDebounced } = data;

const planner = useRoutePlanner({ getEngine, zoom, filterOpen, fetchMeta: (id) => tip.fetchMeta(id) });
const { routeOpen, routePicking, routeStops, routePlan, routePlanning, routeError } = planner;

// Keep `?route=` in step with the planner so clearing the route is not undone by a reload (B-05).
watch(
    () => planner.routeStops.value.map((s) => s.id),
    (ids) => {
        if (typeof window === 'undefined') return;
        const current = new URLSearchParams(window.location.search).has('route');
        if (ids.length === 0 && !current) return;
        window.history.replaceState(window.history.state, '', withRouteParam(window.location.pathname + window.location.search + window.location.hash, ids));
    },
);

const tip = useMapTooltip({ container, mode, points, outlets, grid, whitespace, routePicking, setCursor: (c) => engine?.setCursor(c) });
const { tooltip, tooltipStyle, onHover, nameCache } = tip;

const { selectedId, selectedPos, selectedOutlet, openProspect, drawerLoader, closeDrawer, onProspectChanged, locate, selectOutlet } = useMapSelection({
    getEngine,
    zoom,
    motion,
    remember: tip.remember,
    reloadSoon: loadViewportDebounced,
});

const layers = useMapLayers({
    getEngine,
    mode,
    zoomBucket,
    columnZoom,
    colorBy,
    points,
    grid,
    outlets,
    whitespace,
    routeStops,
    routePlan,
    routePath: planner.routeShown,
    selectedPos,
    hoverOutletIndex: tip.hoverOutletIndex,
    selectedOutlet,
    motion,
    introActive,
    onHover,
});

const debugApi = useMapDebugApi({ debugHooks: () => app.value?.debug_hooks, getEngine, getLastLoad: data.getLastLoad, mode, columnZoom, loadAt: data.loadAt, freezeLoads: data.freezeLoads });

watch(
    () => filter.value.province_id,
    (id) => {
        const p = id ? props.provinces_index.find((x) => x.id === id) : null;
        if (p && p.lat !== null && p.lng !== null) engine?.flyTo({ center: [p.lng, p.lat], zoom: 7, duration: 1800 });
    },
);

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
            planner.pickForRoute({ id, name: nameCache.get(id)?.name ?? null, lng, lat });
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

function toggleFilter(): void {
    filterOpen.value = !filterOpen.value;
    if (filterOpen.value) routeOpen.value = false;
}

const { search, commandItems } = useMapCommands({
    getEngine,
    mode,
    filterOpen,
    routeOpen,
    selectedOutlet,
    resetFilter,
    toggleFilter,
    toggleRoute: planner.toggleRoute,
    openProspect,
});

function skipIntro(): void {
    writeIntroSkipped(true);
    engine?.skipIntro();
}

function onViewport(vp: Viewport): void {
    data.setViewport(vp);
    if (introActive.value) return;
    loadViewportDebounced();
}

function onMove(vp: Viewport): void {
    // Camera moved under a stationary cursor (fly-to, zoom buttons): deck.gl does not re-pick, so drop the stale hover.
    if (tip.hover.value) onHover(null);
    zoom.value = vp.zoom;
    const bucket = Math.round(vp.zoom * 4) / 4;
    if (bucket !== zoomBucket.value) zoomBucket.value = bucket;
    tilted.value = vp.pitch > 10;
}

function handleDeepLinks(): void {
    if (urlParams.mode) mode.value = urlParams.mode;
    if (urlParams.focus) engine?.flyTo({ center: [urlParams.focus.lng, urlParams.focus.lat], zoom: 13, duration: flags.value.motion ? 1400 : 0 });
    if (urlParams.prospect !== null) openProspect(urlParams.prospect);
    if (urlParams.route.length > 0) planner.openFromDeepLink(urlParams.route);
}

async function startMap(): Promise<void> {
    if (!container.value) return;
    let EngineCtor: typeof import('@/map/engine').MapEngine;
    try {
        const eng = await import('@/map/engine');
        EngineCtor = eng.MapEngine;
        layers.setBuilder(eng.buildLayers);
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
            void data.loadOutlets();
            layers.scheduleLayers();
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
    debugApi.install();
}

onMounted(() => {
    void nextTick(startMap);
});

onBeforeUnmount(() => {
    unmounted = true;
    engine?.destroy();
    engine = null;
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
            <MapOverlays
                :ready="ready"
                :map-error="mapError"
                :basemap-failed="basemapFailed"
                :tooltip="tooltip"
                :tooltip-style="tooltipStyle"
                :load-error="loadError"
                :loading="loading"
                :is-empty="isEmpty"
                :intro-active="introActive"
                :mode="mode"
                :active-count="activeCount"
                :live-summary="liveSummary"
                @reload="reloadPage"
                @retry="loadViewport"
                @reset-filter="resetFilter()"
                @skip-intro="skipIntro"
            />
        </div>

        <!-- Inside AppShell so the top-right controls (user menu) stack above the drawer. -->
        <ProspectDrawer :prospect-id="selectedId" :loader="drawerLoader" :rejection-reasons="rejection_reasons" @close="closeDrawer" @updated="onProspectChanged" @contacted="onProspectChanged" @locate="locate">
            <template #actions="{ prospect }">
                <Button size="sm" variant="secondary" data-testid="add-to-route" @click="planner.addToRoute(prospect)"><Icon name="pin" :size="14" /> Tambah ke rute</Button>
            </template>
        </ProspectDrawer>

        <template #top-left>
            <section v-if="ready && !introActive" class="flex w-[min(22rem,calc(100vw-1.5rem))] flex-col gap-2" aria-label="Mode, filter, dan rute">
                <ModeSwitcher v-model="mode" />
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="secondary" class="glass !h-9" :aria-expanded="filterOpen" aria-controls="map-filter" data-testid="filter-toggle" @click="toggleFilter">
                        <Icon name="filter" :size="14" /> Filter
                        <span v-if="activeCount" class="num rounded-full bg-hot px-1.5 text-2xs text-on-hot" :aria-label="`${activeCount} filter aktif`">{{ activeCount }}</span>
                    </Button>
                    <Button size="sm" variant="secondary" class="glass !h-9" :aria-expanded="routeOpen" aria-controls="map-route" data-testid="route-toggle" @click="planner.toggleRoute">
                        <Icon name="navigation" :size="14" /> Rute
                        <span v-if="routeStops.length" class="num rounded-full bg-focus px-1.5 text-2xs text-ink">{{ routeStops.length }}</span>
                    </Button>
                    <div class="sm:hidden"><MapHud :hud="hud" :count="count" :mode="mode" :loading="loading" :truncated="truncated" compact /></div>
                </div>
                <div v-show="filterOpen" id="map-filter" class="max-h-[calc(100dvh-21rem)] overflow-y-auto overscroll-contain rounded-panel sm:max-h-[calc(100dvh-15rem)]" data-testid="filter-scroll">
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
                        @focus="(s: RouteStop) => planner.focusStop(s)"
                        @plan="planner.planRoute()"
                        @clear="planner.clearRoute"
                        @close="routeOpen = false"
                    />
                </div>
            </section>
        </template>

        <template #top-right>
            <div v-if="ready && !introActive" class="hidden sm:block"><MapHud :hud="hud" :count="count" :mode="mode" :loading="loading" :truncated="truncated" /></div>
        </template>

        <template #bottom-left>
            <MapAttribution v-if="ready" />
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
</template>
