import { computed, type Ref, type ShallowRef } from 'vue';
import type { CommandItem } from '@/Components/ui/CommandPalette.vue';
import { useShortcuts } from '@/composables/useShortcuts';
import { api } from '@/lib/api';
import type { MapMode } from '@/types';
import { MAP_MODES } from './constants';
import type { MapEngine } from './engine';
import { buildSearchUrl } from './query';
import { searchToItems, type SearchResponse } from './search';

export interface MapCommandsDeps {
    getEngine: () => MapEngine | null;
    mode: Ref<MapMode>;
    filterOpen: Ref<boolean>;
    routeOpen: Ref<boolean>;
    selectedOutlet: ShallowRef<{ lng: number; lat: number; name: string } | null>;
    resetFilter: () => void;
    toggleFilter: () => void;
    toggleRoute: () => void;
    openProspect: (id: number, opts: { lng: number; lat: number; fly: boolean }) => void;
}

/** Command-palette search, palette commands and keyboard shortcuts of the map page. */
export function useMapCommands(d: MapCommandsDeps) {
    async function search(q: string): Promise<CommandItem[]> {
        const { data } = await api.get<SearchResponse>(buildSearchUrl(q));
        return searchToItems(data, {
            onOutlet: (o) => {
                d.selectedOutlet.value = { lng: o.lng, lat: o.lat, name: o.name };
                d.getEngine()?.flyTo({ center: [o.lng, o.lat], zoom: 13, duration: 1600 });
            },
            onProspect: (p) => {
                d.openProspect(p.id, { lng: p.lng, lat: p.lat, fly: true });
                if (d.mode.value === 'whitespace') d.mode.value = 'prospects';
            },
            onProvince: (p) => d.getEngine()?.flyTo({ center: [p.lng, p.lat], zoom: 7, duration: 1800 }),
        });
    }

    const commandItems = computed<CommandItem[]>(() => [
        ...MAP_MODES.map((m) => ({ id: `mode:${m.key}`, label: `Mode peta: ${m.label}`, hint: m.shortcut, group: 'Peta', action: () => (d.mode.value = m.key) })),
        { id: 'act:reset-filter', label: 'Atur ulang filter', group: 'Peta', action: () => d.resetFilter() },
        { id: 'act:filter', label: d.filterOpen.value ? 'Sembunyikan panel filter' : 'Tampilkan panel filter', hint: 'F', group: 'Peta', action: d.toggleFilter },
        { id: 'act:route', label: d.routeOpen.value ? 'Tutup perencana rute' : 'Buka perencana rute', hint: 'R', group: 'Peta', action: d.toggleRoute },
        { id: 'act:reset-view', label: 'Kembali ke tampilan Indonesia', group: 'Peta', action: () => d.getEngine()?.resetView() },
    ]);

    useShortcuts([
        ...MAP_MODES.map((m) => ({ keys: m.shortcut, description: `Mode peta: ${m.label}`, group: 'Peta', handler: () => (d.mode.value = m.key) })),
        { keys: 'r', description: 'Buka/tutup perencana rute', group: 'Peta', handler: d.toggleRoute },
        { keys: 'f', description: 'Tampilkan/sembunyikan filter', group: 'Peta', handler: d.toggleFilter },
    ]);

    return { search, commandItems };
}
