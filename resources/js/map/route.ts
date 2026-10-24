import type { RoutePlan } from '@/types';
import { MAX_ROUTE_STOPS, MIN_ROUTE_STOPS } from './constants';
import type { Bbox, RouteStop } from './types';

export type AddResult = { list: RouteStop[]; added: boolean; reason?: 'duplicate' | 'limit' };

/** Adds a stop unless it is already there or the 30-stop limit is reached. Never mutates the input. */
export function addStop(list: readonly RouteStop[], stop: RouteStop, max = MAX_ROUTE_STOPS): AddResult {
    if (list.some((s) => s.id === stop.id)) return { list: [...list], added: false, reason: 'duplicate' };
    if (list.length >= max) return { list: [...list], added: false, reason: 'limit' };
    return { list: [...list, stop], added: true };
}

export function removeStop(list: readonly RouteStop[], id: number): RouteStop[] {
    return list.filter((s) => s.id !== id);
}

/** Adds the stop, or removes it when already present (used by the map "pick" mode). */
export function toggleStop(list: readonly RouteStop[], stop: RouteStop, max = MAX_ROUTE_STOPS): AddResult & { removed: boolean } {
    if (list.some((s) => s.id === stop.id)) return { list: removeStop(list, stop.id), added: false, removed: true };
    return { ...addStop(list, stop, max), removed: false };
}

export function moveStop(list: readonly RouteStop[], from: number, to: number): RouteStop[] {
    if (from < 0 || from >= list.length || to < 0 || to >= list.length || from === to) return [...list];
    const next = [...list];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    return next;
}

export function canPlan(list: readonly RouteStop[]): boolean {
    return list.length >= MIN_ROUTE_STOPS && list.length <= MAX_ROUTE_STOPS;
}

/** Why planning is not possible (Indonesian), or null when it is. */
export function planBlocker(list: readonly RouteStop[]): string | null {
    if (list.length < MIN_ROUTE_STOPS) return `Pilih minimal ${MIN_ROUTE_STOPS} prospek.`;
    if (list.length > MAX_ROUTE_STOPS) return `Maksimal ${MAX_ROUTE_STOPS} prospek per rute.`;
    return null;
}

export function planBody(list: readonly RouteStop[]): { prospect_ids: number[] } {
    return { prospect_ids: list.map((s) => s.id) };
}

/** Re-orders (and enriches with server names/coordinates) the selection following the planned order. */
export function applyPlan(list: readonly RouteStop[], plan: RoutePlan): RouteStop[] {
    const byId = new Map(list.map((s) => [s.id, s]));
    const out: RouteStop[] = [];
    for (const stop of plan.stops) {
        const known = byId.get(stop.id);
        out.push({ id: stop.id, name: stop.name ?? known?.name ?? null, lat: stop.lat, lng: stop.lng });
    }
    return out;
}

export function planBounds(plan: Pick<RoutePlan, 'path' | 'stops'>): Bbox | null {
    const pts: [number, number][] = plan.path.length ? plan.path : plan.stops.map((s) => [s.lng, s.lat] as [number, number]);
    if (!pts.length) return null;
    let w = Infinity;
    let s = Infinity;
    let e = -Infinity;
    let n = -Infinity;
    for (const [lng, lat] of pts) {
        if (lng < w) w = lng;
        if (lng > e) e = lng;
        if (lat < s) s = lat;
        if (lat > n) n = lat;
    }
    return [w, s, e, n];
}

/** "Bagian 1 dari 3" for multi-link routes, "Buka di Google Maps" for a single link. */
export function mapsLinkLabel(index: number, total: number): string {
    return total <= 1 ? 'Buka di Google Maps' : `Google Maps, bagian ${index + 1} dari ${total}`;
}

/** Only https Google Maps links are rendered as anchors. */
export function isSafeMapsUrl(url: string): boolean {
    try {
        const u = new URL(url);
        return u.protocol === 'https:' && (u.hostname === 'www.google.com' || u.hostname === 'google.com' || u.hostname === 'maps.google.com');
    } catch {
        return false;
    }
}
