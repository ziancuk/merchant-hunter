import { ref, shallowRef, type Ref } from 'vue';
import { api } from '@/lib/api';
import type { ProspectDetail } from '@/types';
import { drawerPadding } from './geometry';
import type { MapEngine } from './engine';
import { withProspectParam } from './params';
import { unwrapDetail } from './useMapTooltip';

export interface MapSelectionDeps {
    getEngine: () => MapEngine | null;
    zoom: Ref<number>;
    motion: Ref<boolean>;
    remember: (id: number, d: { name: string; category?: { name: string } | null }) => void;
    reloadSoon: () => void;
}

/** Selected prospect/outlet state, URL mirroring (`?prospect=`) and the drawer loader that frames the prospect. */
export function useMapSelection(deps: MapSelectionDeps) {
    const { getEngine, zoom, motion, remember, reloadSoon } = deps;
    const selectedId = ref<number | null>(null);
    const selectedPos = shallowRef<{ lng: number; lat: number } | null>(null);
    const selectedOutlet = shallowRef<{ lng: number; lat: number; name: string } | null>(null);
    let flyOnLoad = false;

    function syncUrl(id: number | null): void {
        try {
            window.history.replaceState(window.history.state, '', withProspectParam(window.location.pathname + window.location.search + window.location.hash, id));
        } catch {
            /* history API unavailable: deep link just is not mirrored */
        }
    }

    function flyToProspect(lng: number, lat: number): void {
        getEngine()?.flyTo({ center: [lng, lat], zoom: Math.max(zoom.value, 14), pitch: motion.value ? 45 : undefined, padding: drawerPadding(window.innerWidth, true), duration: 1300 });
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

    /** Drawer loader: GET /api/prospects/{id}, then frame the prospect when it was opened without coordinates (deep link/search). */
    async function drawerLoader(id: number): Promise<ProspectDetail> {
        const d = unwrapDetail((await api.get(`/api/prospects/${id}`)).data);
        remember(id, d);
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
        remember(p.id, p);
        reloadSoon();
    }

    function locate(c: { lat: number; lng: number }): void {
        getEngine()?.flyTo({ center: [c.lng, c.lat], zoom: 16, padding: drawerPadding(window.innerWidth, true), duration: 1200 });
    }

    function selectOutlet(lng: number, lat: number, name: string): void {
        selectedOutlet.value = { lng, lat, name };
        getEngine()?.flyTo({ center: [lng, lat], zoom: Math.max(zoom.value, 12), duration: 1400 });
    }

    return { selectedId, selectedPos, selectedOutlet, openProspect, drawerLoader, closeDrawer, onProspectChanged, locate, selectOutlet };
}
