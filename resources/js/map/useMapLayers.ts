import { computed, onScopeDispose, ref, watch, type Ref, type ShallowRef } from 'vue';
import type { MapMode } from '@/types';
import type { MapEngine } from './engine';
import type { buildLayers as BuildLayers } from './layers';
import type { MapPick } from './picking';
import type { ColorBy, OutletData, PreparedGrid, PreparedPoints, RouteStop, WhitespaceData } from './types';

/** The "baru buka" pulse is throttled to ~12 fps: it is an ambient hint, not an animation worth a 60 fps WebGL rebuild. */
export const PULSE_INTERVAL_MS = 83;
export const PULSE_PERIOD_MS = 1800;

export interface PulseConditions {
    motion: boolean;
    introActive: boolean;
    mode: MapMode;
    newOpenCount: number;
    visible: boolean;
}

/** Pure: the pulse runs only while a newly-opened prospect can actually be seen and animation is allowed. */
export function pulseWanted(c: PulseConditions): boolean {
    return c.motion && !c.introActive && c.visible && (c.mode === 'prospects' || c.mode === 'coverage') && c.newOpenCount > 0;
}

export interface MapLayersDeps {
    getEngine: () => MapEngine | null;
    mode: Ref<MapMode>;
    zoomBucket: Ref<number>;
    columnZoom: Ref<number>;
    colorBy: Ref<ColorBy>;
    points: ShallowRef<PreparedPoints | null>;
    grid: ShallowRef<PreparedGrid | null>;
    outlets: ShallowRef<OutletData | null>;
    whitespace: ShallowRef<WhitespaceData | null>;
    routeStops: Ref<RouteStop[]>;
    routePlan: Ref<unknown>;
    routePath: Ref<[number, number][] | null>;
    selectedPos: ShallowRef<{ lng: number; lat: number } | null>;
    hoverOutletIndex: Ref<number>;
    selectedOutlet: ShallowRef<{ lng: number; lat: number } | null>;
    motion: Ref<boolean>;
    introActive: Ref<boolean>;
    onHover: (pick: MapPick | null) => void;
}

/** Builds the deck.gl layer stack on demand (rAF-coalesced) and drives the on-demand "baru buka" pulse. */
export function useMapLayers(deps: MapLayersDeps) {
    let builder: typeof BuildLayers | null = null;
    let layerFrame = 0;
    let pulseRaf = 0;
    let pulseTimer = 0;
    let pulsePhase: number | null = null;
    let disposed = false;

    const focusOutlet = computed(() => {
        const i = deps.hoverOutletIndex.value;
        const o = deps.outlets.value;
        if (i >= 0 && o) return { lng: o.positions[2 * i], lat: o.positions[2 * i + 1] };
        return deps.selectedOutlet.value ? { lng: deps.selectedOutlet.value.lng, lat: deps.selectedOutlet.value.lat } : null;
    });

    function applyLayers(): void {
        const engine = deps.getEngine();
        if (!engine || !builder) return;
        engine.setLayers(
            builder({
                mode: deps.mode.value,
                zoom: deps.zoomBucket.value,
                columnZoom: deps.columnZoom.value,
                colorBy: deps.colorBy.value,
                points: deps.points.value,
                grid: deps.grid.value,
                outlets: deps.outlets.value,
                whitespace: deps.whitespace.value,
                route: { stops: deps.routeStops.value, path: deps.routePath.value },
                selected: deps.selectedPos.value,
                focusOutlet: focusOutlet.value,
                pulse: pulsePhase,
                handlers: { onHover: deps.onHover },
            }),
        );
    }

    function scheduleLayers(): void {
        if (layerFrame || disposed) return;
        layerFrame = requestAnimationFrame(() => {
            layerFrame = 0;
            applyLayers();
        });
    }

    watch([deps.points, deps.grid, deps.outlets, deps.whitespace, deps.mode, deps.zoomBucket, deps.columnZoom, deps.routeStops, deps.routePlan, deps.selectedPos, focusOutlet], scheduleLayers);

    // --- pulse: runs only while wanted (no idle polling) ------------------------------------------------------------
    const tabVisible = ref(typeof document === 'undefined' ? true : !document.hidden);
    const onVisibility = () => (tabVisible.value = !document.hidden);
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisibility);

    const wanted = computed(() =>
        pulseWanted({
            motion: deps.motion.value,
            introActive: deps.introActive.value,
            mode: deps.mode.value,
            newOpenCount: deps.points.value?.newOpen.length ?? 0,
            visible: tabVisible.value,
        }),
    );

    function armPulse(): void {
        pulseTimer = window.setTimeout(() => {
            pulseTimer = 0;
            pulseRaf = requestAnimationFrame(pulseFrame);
        }, PULSE_INTERVAL_MS);
    }

    function pulseFrame(now: number): void {
        pulseRaf = 0;
        if (disposed || !wanted.value) return;
        pulsePhase = (now % PULSE_PERIOD_MS) / PULSE_PERIOD_MS;
        scheduleLayers();
        armPulse();
    }

    function stopPulse(): void {
        if (pulseRaf) cancelAnimationFrame(pulseRaf);
        if (pulseTimer) clearTimeout(pulseTimer);
        pulseRaf = 0;
        pulseTimer = 0;
        if (pulsePhase !== null) {
            pulsePhase = null;
            scheduleLayers();
        }
    }

    watch(
        wanted,
        (w) => {
            stopPulse();
            if (w) pulseRaf = requestAnimationFrame(pulseFrame);
        },
        { immediate: true },
    );

    onScopeDispose(() => {
        disposed = true;
        if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisibility);
        if (layerFrame) cancelAnimationFrame(layerFrame);
        layerFrame = 0;
        stopPulse();
        builder = null;
    });

    return {
        scheduleLayers,
        setBuilder: (fn: typeof BuildLayers) => {
            builder = fn;
        },
        /** True while the pulse loop is scheduled (diagnostics/tests). */
        isPulsing: () => pulseRaf !== 0 || pulseTimer !== 0,
    };
}
