<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ReportTable from '@/Components/report/ReportTable.vue';
import { buildDownloadUrl, buildReportPageUrl } from '@/Components/report/report';
import { useReportTable } from '@/Components/report/useReport';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { countActiveFilters, queryToFilter } from '@/composables/useFilters';
import { useDismissable } from '@/composables/useDismissable';
import AppShell from '@/Layouts/AppShell.vue';
import { formatNumber } from '@/lib/format';
import type { ProspectFilter } from '@/types';

/* eslint-disable vue/prop-name-casing -- Inertia props are snake_case (CONTRACTS §1) */
defineProps<{ filter_options: FilterOptions }>();

const filter = ref<ProspectFilter>(typeof window !== 'undefined' ? queryToFilter(window.location.search) : {});
const table = useReportTable(filter);

const filterOpen = ref(false);
const filterRoot = ref<HTMLElement | null>(null);
useDismissable(filterRoot, () => (filterOpen.value = false), { enabled: filterOpen });

const activeFilters = computed(() => countActiveFilters(filter.value));
const csvUrl = computed(() => buildDownloadUrl('csv', filter.value));
const xlsxUrl = computed(() => buildDownloadUrl('xlsx', filter.value));

function syncUrl(): void {
    if (typeof window === 'undefined') return;
    try {
        window.history.replaceState(window.history.state, '', buildReportPageUrl(filter.value));
    } catch {
        /* history may be unavailable (sandboxed previews) */
    }
}

function onFilter(next: ProspectFilter): void {
    filter.value = next;
    table.resetPage();
    syncUrl();
    void table.fetch();
}

onMounted(() => void table.fetch());

const linkBtn =
    'inline-flex h-10 items-center gap-2 rounded-chip border border-line bg-raised/70 px-4 text-sm font-medium text-fg transition hover:bg-raised';
</script>

<template>
    <AppShell>
        <Head title="Laporan" />
        <div class="mx-auto w-full max-w-[1600px] space-y-4">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="hud-label">Laporan prospek</p>
                    <h1 class="font-display text-2xl font-bold tracking-tight text-fg">Laporan</h1>
                    <p class="num mt-0.5 text-sm text-muted" data-testid="report-total">{{ formatNumber(table.meta.value.total) }} prospek sesuai filter</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div ref="filterRoot" class="relative">
                        <Button variant="secondary" :aria-expanded="filterOpen" aria-controls="report-filter" data-testid="filter-toggle" @click="filterOpen = !filterOpen">
                            <Icon name="filter" :size="16" /> Filter
                            <span v-if="activeFilters" class="num rounded-full bg-hot px-1.5 text-2xs text-on-hot" :aria-label="`${activeFilters} filter aktif`">{{ activeFilters }}</span>
                        </Button>
                        <div v-show="filterOpen" id="report-filter" class="absolute right-0 top-full z-30 mt-2 max-h-[70dvh] w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded-panel shadow-float">
                            <FilterPanel :model-value="filter" :options="filter_options" :collapsible="false" @update:model-value="onFilter" />
                        </div>
                    </div>

                    <a :href="csvUrl" :class="linkBtn" download data-testid="export-csv"><Icon name="download" :size="16" /> Unduh CSV</a>
                    <a :href="xlsxUrl" :class="linkBtn" download data-testid="export-xlsx"><Icon name="download" :size="16" /> Unduh Excel</a>
                </div>
            </header>

            <p class="text-sm text-muted">Unduhan mengikuti filter yang sedang aktif. Telepon berlabel "Simulasi" hanyalah data contoh, bukan nomor nyata.</p>

            <ReportTable
                :rows="table.rows.value"
                :meta="table.meta.value"
                :sort="table.sort.value"
                :dir="table.dir.value"
                :loading="table.loading.value"
                :error="table.error.value"
                @sort="table.setSort"
                @page="table.setPage"
                @per-page="table.setPerPage"
                @retry="table.fetch()"
                @reset-filter="onFilter({})"
            />
        </div>
    </AppShell>
</template>
