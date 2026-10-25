import { computed, onScopeDispose, watch, type Ref, type ShallowRef } from 'vue';
import type { CommandItem } from '@/Components/ui/CommandPalette.vue';
import { registerShortcut, type ShortcutDef } from '@/composables/useShortcuts';
import { currentLocale, t } from '@/i18n';
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
        ...MAP_MODES.map((m) => ({ id: `mode:${m.key}`, label: t('map.commands.mode', { label: m.label }), hint: m.shortcut, group: t('map.commands.group'), action: () => (d.mode.value = m.key) })),
        { id: 'act:reset-filter', label: t('map.commands.reset_filter'), group: t('map.commands.group'), action: () => d.resetFilter() },
        { id: 'act:filter', label: d.filterOpen.value ? t('map.commands.hide_filter') : t('map.commands.show_filter'), hint: 'F', group: t('map.commands.group'), action: d.toggleFilter },
        { id: 'act:route', label: d.routeOpen.value ? t('map.commands.close_route') : t('map.commands.open_route'), hint: 'R', group: t('map.commands.group'), action: d.toggleRoute },
        { id: 'act:reset-view', label: t('map.commands.reset_view'), group: t('map.commands.group'), action: () => d.getEngine()?.resetView() },
    ]);

    // The shortcut registry copies the definitions, so re-register them when the language changes to keep the `?` help list translated.
    const shortcutDefs = (): ShortcutDef[] => [
        ...MAP_MODES.map((m) => ({ keys: m.shortcut, description: t('map.commands.mode', { label: m.label }), group: t('map.commands.group'), handler: () => (d.mode.value = m.key) })),
        { keys: 'r', description: t('map.commands.kb_route'), group: t('map.commands.group'), handler: d.toggleRoute },
        { keys: 'f', description: t('map.commands.kb_filter'), group: t('map.commands.group'), handler: d.toggleFilter },
    ];
    let offs: (() => void)[] = [];
    const registerAll = () => {
        offs.forEach((off) => off());
        offs = shortcutDefs().map(registerShortcut);
    };
    registerAll();
    watch(currentLocale, registerAll);
    onScopeDispose(() => offs.forEach((off) => off()));

    return { search, commandItems };
}
