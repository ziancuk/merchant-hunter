<script lang="ts">
export interface CommandItem {
    id: string | number;
    label: string;
    hint?: string;
    group: string;
    action?: () => void;
}
</script>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import Icon from './Icon.vue';
import { useDebounceFn } from '@/composables/useDebounce';
import { useShortcuts } from '@/composables/useShortcuts';
import { t } from '@/i18n';


const uidStr = `cmdk-${useId()}`;

const props = withDefaults(
    defineProps<{
        open: boolean;
        items: CommandItem[];
        /** Async remote search (e.g. GET /api/search). Called debounced for queries of 2+ characters. */
        search?: (query: string) => Promise<CommandItem[]>;
        placeholder?: string;
        debounceMs?: number;
        /** Register the global Ctrl/Cmd+K toggle. */
        hotkey?: boolean;
    }>(),
    { search: undefined, placeholder: undefined, debounceMs: 250, hotkey: true },
);

const emit = defineEmits<{ 'update:open': [value: boolean]; select: [item: CommandItem] }>();

const query = ref('');
const active = ref(0);
const remote = ref<CommandItem[]>([]);
const remoteLoading = ref(false);
const remoteError = ref(false);
const input = ref<HTMLInputElement | null>(null);
let previouslyFocused: HTMLElement | null = null;
let searchToken = 0;

if (props.hotkey) {
    useShortcuts([
        {
            keys: 'mod+k',
            description: () => t('ui.shortcuts.openPalette'),
            group: () => t('ui.shortcuts.general'),
            allowInInput: true,
            handler: () => emit('update:open', !props.open),
        },
    ]);
}

const localMatches = computed(() => {
    const tokens = query.value.toLowerCase().split(/\s+/).filter(Boolean);
    if (!tokens.length) return props.items;
    return props.items.filter((it) => {
        const hay = `${it.label} ${it.hint ?? ''} ${it.group}`.toLowerCase();
        return tokens.every((tok) => hay.includes(tok));
    });
});

const flat = computed<CommandItem[]>(() => {
    const seen = new Set(localMatches.value.map((i) => String(i.id)));
    return [...localMatches.value, ...remote.value.filter((i) => !seen.has(String(i.id)))];
});

interface Row {
    kind: 'group' | 'item';
    key: string;
    label?: string;
    item?: CommandItem;
    index?: number;
}

const rows = computed<Row[]>(() => {
    const out: Row[] = [];
    let last = '';
    flat.value.forEach((item, index) => {
        if (item.group !== last) {
            out.push({ kind: 'group', key: `g-${item.group}-${index}`, label: item.group });
            last = item.group;
        }
        out.push({ kind: 'item', key: `i-${item.id}`, item, index });
    });
    return out;
});

const runSearch = useDebounceFn(async (q: string) => {
    if (!props.search) return;
    const token = ++searchToken;
    remoteLoading.value = true;
    remoteError.value = false;
    try {
        const result = await props.search(q);
        if (token === searchToken) remote.value = result;
    } catch {
        if (token === searchToken) {
            remote.value = [];
            remoteError.value = true;
        }
    } finally {
        if (token === searchToken) remoteLoading.value = false;
    }
}, props.debounceMs);

watch(query, (q) => {
    active.value = 0;
    if (!props.search) return;
    if (q.trim().length >= 2) {
        remoteLoading.value = true;
        runSearch(q.trim());
    } else {
        searchToken++;
        runSearch.cancel();
        remote.value = [];
        remoteLoading.value = false;
        remoteError.value = false;
    }
});

watch(
    () => props.open,
    async (open) => {
        if (open) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            query.value = '';
            active.value = 0;
            await nextTick();
            input.value?.focus();
        } else {
            await nextTick();
            if (previouslyFocused && document.contains(previouslyFocused)) previouslyFocused.focus();
            previouslyFocused = null;
        }
    },
    { flush: 'post' },
);

watch(flat, (list) => {
    if (active.value >= list.length) active.value = Math.max(0, list.length - 1);
});

function close(): void {
    emit('update:open', false);
}

function choose(item: CommandItem | undefined): void {
    if (!item) return;
    close();
    item.action?.();
    emit('select', item);
}

function move(delta: number): void {
    const n = flat.value.length;
    if (!n) return;
    active.value = (active.value + delta + n) % n;
    nextTick(() => document.getElementById(`${uidStr}-opt-${active.value}`)?.scrollIntoView?.({ block: 'nearest' }));
}

function onKeydown(e: KeyboardEvent): void {
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            e.preventDefault();
            move(-1);
            break;
        case 'Home':
            e.preventDefault();
            active.value = 0;
            break;
        case 'End':
            e.preventDefault();
            active.value = Math.max(0, flat.value.length - 1);
            break;
        case 'Enter':
            e.preventDefault();
            choose(flat.value[active.value]);
            break;
        case 'Escape':
            e.preventDefault();
            e.stopPropagation();
            close();
            break;
        case 'Tab':
            e.preventDefault();
            break;
    }
}

onBeforeUnmount(() => runSearch.cancel());
</script>

<template>
    <Transition name="fade">
        <div v-if="open" class="fixed inset-0 z-[60] flex items-start justify-center bg-black/50 px-3 pt-[12vh]" @mousedown.self="close">
            <div
                class="glass w-full max-w-xl overflow-hidden rounded-panel text-fg"
                role="dialog"
                aria-modal="true"
                :aria-label="t('ui.palette.dialog')"
                @keydown="onKeydown"
            >
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Icon name="search" class="text-muted" />
                    <input
                        ref="input"
                        v-model="query"
                        type="text"
                        role="combobox"
                        class="h-12 w-full bg-transparent text-base text-fg outline-none placeholder:text-muted"
                        autocomplete="off"
                        spellcheck="false"
                        aria-autocomplete="list"
                        aria-expanded="true"
                        :aria-controls="`${uidStr}-list`"
                        :aria-activedescendant="flat.length ? `${uidStr}-opt-${active}` : undefined"
                        :aria-label="t('ui.palette.search')"
                        :placeholder="placeholder ?? t('ui.palette.placeholder')"
                    />
                    <kbd class="num rounded border border-line px-1.5 py-0.5 text-2xs text-muted">Esc</kbd>
                </div>
                <ul :id="`${uidStr}-list`" role="listbox" :aria-label="t('ui.palette.results')" class="max-h-[50vh] overflow-y-auto p-2">
                    <template v-for="row in rows" :key="row.key">
                        <li v-if="row.kind === 'group'" role="presentation" class="hud-label px-2 pb-1 pt-3 first:pt-1">{{ row.label }}</li>
                        <li
                            v-else
                            :id="`${uidStr}-opt-${row.index}`"
                            role="option"
                            :aria-selected="row.index === active"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-chip px-3 py-2 text-sm"
                            :class="row.index === active ? 'bg-fg/10 text-fg' : 'text-fg/90'"
                            @mousemove="active = row.index!"
                            @click="choose(row.item)"
                        >
                            <span class="truncate">{{ row.item!.label }}</span>
                            <span v-if="row.item!.hint" class="shrink-0 truncate text-xs text-muted">{{ row.item!.hint }}</span>
                        </li>
                    </template>
                </ul>
                <p v-if="remoteLoading" class="px-4 pb-3 text-xs text-muted" role="status">{{ t('ui.palette.searching') }}</p>
                <p v-else-if="remoteError" class="px-4 pb-3 text-xs text-danger" role="alert">{{ t('ui.palette.failed') }}</p>
                <p v-else-if="!flat.length" class="px-4 pb-4 text-sm text-muted" role="status">{{ t('ui.palette.empty', { query }) }}</p>
                <div class="flex items-center gap-4 border-t border-line px-4 py-2 text-2xs text-muted">
                    <span><kbd class="num">↑↓</kbd> {{ t('ui.palette.hintSelect') }}</span>
                    <span><kbd class="num">Enter</kbd> {{ t('ui.palette.hintOpen') }}</span>
                    <span><kbd class="num">Esc</kbd> {{ t('ui.palette.hintClose') }}</span>
                </div>
            </div>
        </div>
    </Transition>
</template>
