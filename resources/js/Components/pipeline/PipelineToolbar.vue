<script setup lang="ts">
import { ref } from 'vue';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useDismissable } from '@/composables/useDismissable';
import { formatNumber } from '@/lib/format';
import type { ProspectFilter } from '@/types';
import type { PipelineView } from './pipeline';

defineProps<{
    total: number;
    view: PipelineView;
    filter: ProspectFilter;
    filterOptions: FilterOptions;
    activeFilters: number;
    exportUrl: string;
}>();
const emit = defineEmits<{ view: [next: PipelineView]; filter: [next: ProspectFilter] }>();

const filterOpen = ref(false);
const filterRoot = ref<HTMLElement | null>(null);
useDismissable(filterRoot, () => (filterOpen.value = false), { enabled: filterOpen });
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="hud-label">Pipeline penjualan</p>
            <h1 class="font-display text-2xl font-bold tracking-tight text-fg">Pipeline</h1>
            <p class="num mt-0.5 text-sm text-muted" data-testid="pipeline-total">{{ formatNumber(total) }} prospek sesuai filter</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="glass inline-flex rounded-full p-1" role="group" aria-label="Tampilan pipeline">
                <button
                    v-for="v in (['kanban', 'table'] as const)"
                    :key="v"
                    type="button"
                    class="flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-sm font-medium transition"
                    :class="view === v ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10 hover:text-fg'"
                    :aria-pressed="view === v"
                    :data-testid="`view-toggle-${v}`"
                    @click="emit('view', v)"
                >
                    <Icon v-if="v === 'kanban'" name="kanban" :size="15" />
                    {{ v === 'kanban' ? 'Kanban' : 'Tabel' }}
                </button>
            </div>

            <div ref="filterRoot" class="relative">
                <Button variant="secondary" :aria-expanded="filterOpen" aria-controls="pipeline-filter" data-testid="filter-toggle" @click="filterOpen = !filterOpen">
                    <Icon name="filter" :size="16" /> Filter
                    <span v-if="activeFilters" class="num rounded-full bg-hot px-1.5 text-2xs text-on-hot" :aria-label="`${activeFilters} filter aktif`">{{ activeFilters }}</span>
                </Button>
                <div v-show="filterOpen" id="pipeline-filter" class="absolute right-0 top-full z-30 mt-2 max-h-[70dvh] w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded-panel shadow-float">
                    <FilterPanel :model-value="filter" :options="filterOptions" :collapsible="false" @update:model-value="(f: ProspectFilter) => emit('filter', f)" />
                </div>
            </div>

            <a
                :href="exportUrl"
                class="inline-flex h-10 items-center gap-2 rounded-chip border border-line bg-raised/70 px-4 text-sm font-medium text-fg transition hover:bg-raised"
                download
                data-testid="export-csv"
            >
                <Icon name="note" :size="16" /> Ekspor CSV
            </a>
        </div>
    </header>
</template>
