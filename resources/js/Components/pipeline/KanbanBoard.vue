<script setup lang="ts">
import { ref } from 'vue';
import ProspectCard from '@/Components/prospect/ProspectCard.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { formatNumber } from '@/lib/format';
import { STATUS_CLASSES } from '@/lib/colors';
import { PIPELINE_STATUSES, type PipelineStatus } from '@/types';
import MoveMenu from './MoveMenu.vue';
import { COLUMN_LABEL } from '@/lib/labels';
import type { BoardColumns } from './pipeline';

const props = withDefaults(
    defineProps<{
        columns: BoardColumns;
        /** Fetching with nothing to show yet → skeleton cards. */
        loading?: boolean;
        /** Refetching over existing data → dimmed, aria-busy. */
        refreshing?: boolean;
        loadingMore?: Partial<Record<PipelineStatus, boolean>>;
        selected?: number[];
        activeId?: number | null;
    }>(),
    { loading: false, refreshing: false, loadingMore: () => ({}), selected: () => [], activeId: null },
);
const emit = defineEmits<{
    open: [id: number];
    move: [id: number, from: PipelineStatus, to: PipelineStatus];
    'load-more': [status: PipelineStatus];
    'toggle-select': [id: number];
}>();

const dragging = ref<{ id: number; from: PipelineStatus } | null>(null);
const overStatus = ref<PipelineStatus | null>(null);

function onDragStart(e: DragEvent, id: number, from: PipelineStatus): void {
    dragging.value = { id, from };
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(id));
    }
}

function onDragEnd(): void {
    dragging.value = null;
    overStatus.value = null;
}

function onDragOver(e: DragEvent, status: PipelineStatus): void {
    if (!dragging.value) return;
    e.preventDefault(); // allow drop
    if (e.dataTransfer) e.dataTransfer.dropEffect = dragging.value.from === status ? 'none' : 'move';
    overStatus.value = status;
}

function onDragLeave(e: DragEvent, status: PipelineStatus): void {
    const next = e.relatedTarget as Node | null;
    if (next && (e.currentTarget as HTMLElement).contains(next)) return;
    if (overStatus.value === status) overStatus.value = null;
}

function onDrop(e: DragEvent, to: PipelineStatus): void {
    e.preventDefault();
    const d = dragging.value;
    onDragEnd();
    if (d && d.from !== to) emit('move', d.id, d.from, to);
}

const remaining = (s: PipelineStatus): number => Math.max(0, props.columns[s].total - props.columns[s].cards.length);
</script>

<template>
    <div class="flex gap-3 overflow-x-auto pb-3 xl:grid xl:grid-cols-5 xl:overflow-visible" :class="refreshing ? 'opacity-60' : ''" :aria-busy="loading || refreshing" data-testid="kanban-board">
        <section
            v-for="s in PIPELINE_STATUSES"
            :key="s"
            class="flex max-h-[calc(100dvh-13rem)] min-h-48 w-[17rem] shrink-0 flex-col rounded-panel border bg-surface/60 transition-colors xl:w-auto"
            :class="overStatus === s && dragging?.from !== s ? 'border-focus bg-focus/10' : 'border-line'"
            :aria-labelledby="`kanban-title-${s}`"
            :data-testid="`kanban-col-${s}`"
            @dragover="onDragOver($event, s)"
            @dragleave="onDragLeave($event, s)"
            @drop="onDrop($event, s)"
        >
            <header class="flex items-center justify-between gap-2 border-b border-line px-3 py-2.5">
                <h2 :id="`kanban-title-${s}`" class="flex items-center gap-2 font-display text-sm font-semibold text-fg">
                    <span class="h-2.5 w-2.5 rounded-full" :class="STATUS_CLASSES[s].bg" aria-hidden="true" />
                    {{ COLUMN_LABEL[s] }}
                </h2>
                <span class="num rounded-full bg-fg/10 px-2 py-0.5 text-xs text-fg" :aria-label="`${columns[s].total} prospek`" :data-testid="`kanban-count-${s}`">{{ formatNumber(columns[s].total) }}</span>
            </header>

            <ul class="flex-1 space-y-2 overflow-y-auto p-2" :aria-label="`Kartu ${COLUMN_LABEL[s]}`" role="list">
                <template v-if="loading && columns[s].cards.length === 0">
                    <li v-for="n in 3" :key="n" class="rounded-panel border border-line p-3" data-testid="kanban-skeleton">
                        <Skeleton :lines="3" height="0.9rem" />
                    </li>
                </template>
                <li v-else-if="columns[s].cards.length === 0" class="rounded-panel border border-dashed border-line px-3 py-6 text-center text-xs text-muted" :data-testid="`kanban-empty-${s}`">
                    Tidak ada prospek. Seret kartu ke sini.
                </li>
                <li
                    v-for="card in columns[s].cards"
                    :key="card.id"
                    class="group relative"
                    :class="dragging?.id === card.id ? 'opacity-40' : ''"
                    draggable="true"
                    :data-testid="`kanban-card-${card.id}`"
                    @dragstart="onDragStart($event, card.id, s)"
                    @dragend="onDragEnd"
                >
                    <ProspectCard class="[&[data-testid=prospect-card]]:pr-20" :prospect="card" :selected="activeId === card.id" @select="emit('open', $event)" />
                    <div class="absolute right-1.5 top-1.5 flex items-center">
                        <label class="grid h-8 w-8 cursor-pointer place-items-center" @click.stop>
                            <input
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer accent-[var(--c-focus)]"
                                :checked="selected.includes(card.id)"
                                :aria-label="`Pilih ${card.name}`"
                                :data-testid="`select-${card.id}`"
                                @change="emit('toggle-select', card.id)"
                            />
                        </label>
                        <MoveMenu :prospect-id="card.id" :name="card.name" :current="s" @move="(to) => emit('move', card.id, s, to)" />
                    </div>
                </li>
                <li v-if="columns[s].next_cursor" class="pt-1">
                    <Button
                        variant="secondary"
                        size="sm"
                        class="w-full"
                        :loading="!!loadingMore[s]"
                        :data-testid="`kanban-more-${s}`"
                        @click="emit('load-more', s)"
                    >
                        Muat lagi<template v-if="remaining(s) > 0"> ({{ formatNumber(remaining(s)) }} lagi)</template>
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
