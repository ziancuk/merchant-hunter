import { onScopeDispose, type Ref } from 'vue';
import type { MapMode } from '@/types';
import type { MapEngine } from './engine';
import type { LoadStats } from './useMapData';

export interface DebugApiDeps {
    debugHooks: () => boolean | undefined;
    getEngine: () => MapEngine | null;
    getLastLoad: () => LoadStats | null;
    mode: Ref<MapMode>;
    columnZoom: Ref<number>;
    loadAt: (bbox: [number, number, number, number], zoom: number) => Promise<void>;
    freezeLoads: (v: boolean) => void;
}

/** Pure: the debug surface exists everywhere except production (D-93). */
export function debugApiEnabled(debugHooks: boolean | undefined): boolean {
    return debugHooks === true;
}

/** `window.__hunterMap`: hooks for the E2E/performance harness. Installed only outside production; removed on unmount. */
export function useMapDebugApi(deps: DebugApiDeps) {
    function install(): void {
        if (!debugApiEnabled(deps.debugHooks())) return;
        (window as unknown as Record<string, unknown>).__hunterMap = {
            get map() {
                return deps.getEngine()?.map ?? null;
            },
            get lastLoad() {
                return deps.getLastLoad();
            },
            get layerIds() {
                return deps.getEngine()?.getLayerIds() ?? [];
            },
            flyTo: (lng: number, lat: number, z = 12) => deps.getEngine()?.flyTo({ center: [lng, lat], zoom: z, duration: 0 }),
            setMode: (m: MapMode) => (deps.mode.value = m),
            /** Performance harness: request an arbitrary viewport (e.g. the whole country at zoom 6) without moving the camera. */
            loadAt: deps.loadAt,
            setColumnZoom: (z: number) => (deps.columnZoom.value = z),
            freezeLoads: deps.freezeLoads,
        };
    }

    function uninstall(): void {
        delete (window as unknown as Record<string, unknown>).__hunterMap;
    }

    onScopeDispose(uninstall);
    return { install, uninstall };
}
