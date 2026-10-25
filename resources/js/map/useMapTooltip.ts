import { computed, ref, shallowRef, watch, onScopeDispose, type Ref, type ShallowRef } from 'vue';
import { useDebounceFn } from '@/composables/useDebounce';
import { api } from '@/lib/api';
import { scoreBand } from '@/lib/colors';
import { formatNumber } from '@/lib/format';
import { BAND_LABEL, STATUS_LABEL } from '@/lib/labels';
import type { MapMode, ProspectDetail } from '@/types';
import { decodeFlags, statusFromIndex } from './payload';
import type { MapPick } from './picking';
import type { OutletData, PreparedGrid, PreparedPoints, WhitespaceData } from './types';

export interface ProspectMeta {
    name: string;
    category: string;
}

export interface TooltipContent {
    title: string;
    lines: string[];
    x: number;
    y: number;
}

export interface TooltipSources {
    points: PreparedPoints | null;
    outlets: OutletData | null;
    grid: PreparedGrid | null;
    whitespace: WhitespaceData | null;
    meta: ProspectMeta | null;
    routePicking: boolean;
}

/** Pure: what the hover tooltip shows for a pick (null when the pick no longer matches the loaded data). */
export function buildTooltip(h: MapPick | null, s: TooltipSources): TooltipContent | null {
    if (!h) return null;
    if (h.kind === 'prospect' && s.points) {
        const p = s.points;
        const i = h.index;
        if (i >= p.count) return null;
        const f = decodeFlags(p.flags[i]);
        const extras = [f.newOpen ? 'Baru buka' : '', f.simulated ? 'Data simulasi' : '', f.closed ? 'Tutup' : '', f.optOut ? 'Jangan hubungi' : ''].filter(Boolean);
        return {
            title: s.meta?.name ?? `Prospek #${p.ids[i]}`,
            lines: [
                s.meta?.category ?? '',
                `Skor ${p.scores[i]} (${BAND_LABEL[scoreBand(p.scores[i])]}) · ${STATUS_LABEL[statusFromIndex(p.status[i])]}`,
                extras.join(' · '),
                s.routePicking ? 'Klik untuk tambah/hapus dari rute' : 'Klik untuk detail',
            ].filter(Boolean),
            x: h.x,
            y: h.y,
        };
    }
    if (h.kind === 'outlet' && s.outlets && h.index < s.outlets.count) {
        const o = s.outlets;
        return { title: o.names[h.index], lines: [`Outlet ${o.codes[h.index]}`, 'Cincin 2 km ditampilkan'], x: h.x, y: h.y };
    }
    if (h.kind === 'grid' && s.grid && h.index < s.grid.count) {
        const g = s.grid;
        return {
            title: `${formatNumber(g.counts[h.index])} prospek`,
            lines: [`Skor rata-rata ${formatNumber(g.avgScores[h.index], 1)}`, `${formatNumber(g.hots[h.index])} panas`, 'Klik untuk memperbesar'],
            x: h.x,
            y: h.y,
        };
    }
    if (h.kind === 'whitespace' && s.whitespace && h.index < s.whitespace.count) {
        const c = s.whitespace.cells[h.index];
        return {
            title: `${formatNumber(c.prospects)} prospek tanpa outlet`,
            lines: [`Outlet terdekat ${formatNumber(c.nearest_outlet_m)} m`, `Skor rata-rata ${formatNumber(c.avg_score)}`],
            x: h.x,
            y: h.y,
        };
    }
    return null;
}

/** Pure: absolute position of the tooltip box, flipped to the left of the cursor near the right edge. */
export function tooltipPosition(t: { x: number; y: number } | null, containerWidth: number): Record<string, string | undefined> {
    if (!t) return {};
    const flip = t.x > containerWidth - 260;
    return { left: `${flip ? t.x - 14 : t.x + 14}px`, top: `${t.y + 14}px`, transform: flip ? 'translateX(-100%)' : undefined };
}

export function unwrapDetail(raw: unknown): ProspectDetail {
    const r = raw as { data?: ProspectDetail; prospect?: ProspectDetail } & Partial<ProspectDetail>;
    if (r && r.id === undefined) return (r.data ?? r.prospect) as ProspectDetail;
    return r as ProspectDetail;
}

export interface MapTooltipDeps {
    container: Ref<HTMLElement | null>;
    mode: Ref<MapMode>;
    points: ShallowRef<PreparedPoints | null>;
    outlets: ShallowRef<OutletData | null>;
    grid: ShallowRef<PreparedGrid | null>;
    whitespace: ShallowRef<WhitespaceData | null>;
    routePicking: Ref<boolean>;
    setCursor: (cursor: string) => void;
}

/** Hover state, lazily fetched prospect names (cached) and the tooltip content/position. */
export function useMapTooltip(deps: MapTooltipDeps) {
    const { container, mode, points, outlets, grid, whitespace, routePicking, setCursor } = deps;
    const hover = shallowRef<MapPick | null>(null);
    const hoverMeta = ref<ProspectMeta | null>(null);
    const nameCache = new Map<number, ProspectMeta>();
    let hoverController: AbortController | null = null;

    function onHover(pick: MapPick | null): void {
        hover.value = pick;
        setCursor(pick ? 'pointer' : '');
    }

    function remember(id: number, d: { name: string; category?: { name: string } | null }): void {
        nameCache.set(id, { name: d.name, category: d.category?.name ?? '' });
    }

    async function fetchMeta(id: number): Promise<ProspectMeta | null> {
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

    const fetchMetaDebounced = useDebounceFn((id: number) => {
        void fetchMeta(id).then((m) => {
            const h = hover.value;
            if (m && h?.kind === 'prospect' && points.value && points.value.ids[h.index] === id) hoverMeta.value = m;
        });
    }, 140);

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
    watch(mode, () => {
        hover.value = null;
    });

    const tooltip = computed(() =>
        buildTooltip(hover.value, { points: points.value, outlets: outlets.value, grid: grid.value, whitespace: whitespace.value, meta: hoverMeta.value, routePicking: routePicking.value }),
    );
    const tooltipStyle = computed(() => tooltipPosition(tooltip.value, container.value?.clientWidth ?? 1000));

    /** Index of the outlet under the cursor (-1: none). A number so unrelated mouse moves do not retrigger layer builds. */
    const hoverOutletIndex = computed(() => (hover.value?.kind === 'outlet' && outlets.value && hover.value.index < outlets.value.count ? hover.value.index : -1));

    onScopeDispose(() => {
        hoverController?.abort();
        fetchMetaDebounced.cancel();
        nameCache.clear();
    });

    return { hover, onHover, tooltip, tooltipStyle, hoverOutletIndex, nameCache, fetchMeta, remember };
}
