import { computed, ref, shallowRef, watch, type Ref } from 'vue';
import { t } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import type { ProspectDetail, RoutePlan } from '@/types';
import { MAX_ROUTE_STOPS } from './constants';
import { addStop, applyPlan, planBody, planBounds, toggleStop } from './route';
import type { MapEngine } from './engine';
import type { ProspectMeta } from './useMapTooltip';
import { unwrapDetail } from './useMapTooltip';
import type { RouteStop } from './types';

export interface RoutePlannerDeps {
    getEngine: () => MapEngine | null;
    zoom: Ref<number>;
    /** Panel exclusivity: opening the route panel closes the filter panel. */
    filterOpen: Ref<boolean>;
    fetchMeta: (id: number) => Promise<ProspectMeta | null>;
}

/** Route planner state: stops, optimised plan, panel visibility and the API calls that build them. */
export function useRoutePlanner(deps: RoutePlannerDeps) {
    const { getEngine, zoom, filterOpen, fetchMeta } = deps;
    const routeOpen = ref(false);
    const routePicking = ref(false);
    const routeStops = ref<RouteStop[]>([]);
    const routePlan = shallowRef<RoutePlan | null>(null);
    const routePlanning = ref(false);
    const routeError = ref('');
    const routeShown = computed(() => (routePlan.value ? routePlan.value.path : null));

    function pickForRoute(stop: RouteStop): void {
        const r = toggleStop(routeStops.value, stop);
        routeStops.value = r.list;
        if (!r.added && !r.removed && r.reason === 'limit') routeError.value = t('map.route.max_stops', { max: MAX_ROUTE_STOPS });
        else routeError.value = '';
        if (r.added && stop.name === null) void fillStopName(stop.id);
    }

    function addToRoute(p: ProspectDetail): void {
        routeOpen.value = true;
        filterOpen.value = false;
        const r = addStop(routeStops.value, { id: p.id, name: p.name, lat: p.lat, lng: p.lng });
        routeStops.value = r.list;
        routeError.value = r.reason === 'limit' ? t('map.route.max_stops', { max: MAX_ROUTE_STOPS }) : '';
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
            if (b) getEngine()?.fitBbox(b, { padding: { top: 110, bottom: 130, left: 120, right: 120 }, maxZoom: 15 });
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
        if (s.lat !== 0 || s.lng !== 0) getEngine()?.flyTo({ center: [s.lng, s.lat], zoom: Math.max(zoom.value, 15), duration: 1000 });
    }

    function toggleRoute(): void {
        routeOpen.value = !routeOpen.value;
        if (routeOpen.value) filterOpen.value = false;
    }

    watch(routeOpen, (open) => {
        if (!open) routePicking.value = false;
    });

    async function fetchOneStop(id: number): Promise<void> {
        try {
            const d = unwrapDetail((await api.get(`/api/prospects/${id}`)).data);
            routeStops.value = addStop(routeStops.value, { id: d.id, name: d.name, lat: d.lat, lng: d.lng }).list;
        } catch (e) {
            routeError.value = normalizeError(e).message;
        }
    }

    /** Deep link `?route=1,2,3`: open the panel and plan (2+ stops) or load the single stop. */
    function openFromDeepLink(ids: number[]): void {
        routeOpen.value = true;
        filterOpen.value = false;
        if (ids.length >= 2) void planRoute(ids);
        else void fetchOneStop(ids[0]);
    }

    return { routeOpen, routePicking, routeStops, routePlan, routePlanning, routeError, routeShown, pickForRoute, addToRoute, planRoute, clearRoute, focusStop, toggleRoute, openFromDeepLink };
}
