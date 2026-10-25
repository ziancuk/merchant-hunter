import type { CommandItem } from '@/Components/ui/CommandPalette.vue';
import { t } from '@/i18n';

export interface SearchResponse {
    outlets: { id: number; name: string; city: string | null; lng: number; lat: number }[];
    prospects: { id: number; name: string; category: string; city: string | null; score: number; lng: number; lat: number }[];
    provinces: { id: number; name: string; lng: number; lat: number }[];
}

export interface SearchHandlers {
    onOutlet: (o: SearchResponse['outlets'][number]) => void;
    onProspect: (p: SearchResponse['prospects'][number]) => void;
    onProvince: (p: SearchResponse['provinces'][number]) => void;
}

/** GET /api/search → command palette items (max 8 per group is enforced server-side; capped again here). */
export function searchToItems(res: Partial<SearchResponse> | null | undefined, h: SearchHandlers): CommandItem[] {
    const items: CommandItem[] = [];
    for (const p of (res?.provinces ?? []).slice(0, 8)) {
        items.push({ id: `province:${p.id}`, label: p.name, hint: t('map.search.province'), group: t('map.search.province'), action: () => h.onProvince(p) });
    }
    for (const o of (res?.outlets ?? []).slice(0, 8)) {
        items.push({ id: `outlet:${o.id}`, label: o.name, hint: o.city ?? t('map.search.outlet'), group: t('map.search.outlet'), action: () => h.onOutlet(o) });
    }
    for (const p of (res?.prospects ?? []).slice(0, 8)) {
        const hint = [p.category, p.city, t('map.search.score', { score: p.score })].filter(Boolean).join(' · ');
        items.push({ id: `prospect:${p.id}`, label: p.name, hint, group: t('map.search.prospect'), action: () => h.onProspect(p) });
    }
    return items;
}
