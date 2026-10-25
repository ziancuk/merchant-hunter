<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SimulasiBadge from '@/Components/prospect/SimulasiBadge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { BAND_CLASSES, STATUS_CLASSES } from '@/lib/colors';
import { formatDistance, formatNumber, relativeTime } from '@/lib/format';
import { BAND_LABEL, STATUS_LABEL } from '@/lib/labels';
import { buildMapUrl, REPORT_COLUMNS, type ReportMeta, type ReportRow, type ReportSortKey, type SortDir } from './report';

const props = withDefaults(
    defineProps<{
        rows: ReportRow[];
        meta: ReportMeta;
        sort: ReportSortKey;
        dir: SortDir;
        loading?: boolean;
        error?: string;
    }>(),
    { loading: false, error: '' },
);
const emit = defineEmits<{
    sort: [key: ReportSortKey];
    page: [page: number];
    'per-page': [perPage: number];
    retry: [];
    'reset-filter': [];
}>();

const ariaSort = (key: ReportSortKey | undefined): 'ascending' | 'descending' | 'none' | undefined =>
    key === undefined ? undefined : props.sort !== key ? 'none' : props.dir === 'asc' ? 'ascending' : 'descending';

const from = computed(() => (props.meta.total === 0 ? 0 : (props.meta.current_page - 1) * props.meta.per_page + 1));
const to = computed(() => Math.min(props.meta.total, props.meta.current_page * props.meta.per_page));
</script>

<template>
    <div data-testid="report-table-wrap">
        <ErrorState v-if="error && rows.length === 0" title="Gagal memuat laporan" :message="error" :retrying="loading" @retry="emit('retry')" />

        <div v-else-if="loading && rows.length === 0" class="space-y-2 rounded-panel border border-line bg-surface/60 p-4" data-testid="report-skeleton">
            <Skeleton v-for="n in 8" :key="n" height="2.25rem" />
        </div>

        <EmptyState v-else-if="rows.length === 0" title="Tidak ada prospek yang cocok" description="Longgarkan filter untuk melihat lebih banyak prospek." icon="filter" data-testid="report-empty">
            <Button size="sm" variant="secondary" @click="emit('reset-filter')">Atur ulang filter</Button>
        </EmptyState>

        <template v-else>
            <p v-if="error" class="mb-2 rounded-chip border border-danger/40 bg-danger/10 p-2 text-sm text-danger" role="alert">{{ error }}</p>
            <div class="overflow-x-auto rounded-panel border border-line bg-surface/60" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                <table class="w-full min-w-[80rem] border-collapse text-left text-sm" data-testid="report-table">
                    <caption class="sr-only">Laporan prospek</caption>
                    <thead>
                        <tr class="border-b border-line text-xs text-muted">
                            <th
                                v-for="c in REPORT_COLUMNS"
                                :key="c.key"
                                scope="col"
                                class="px-3 py-2"
                                :class="c.align === 'right' ? 'text-right' : ''"
                                :aria-sort="ariaSort(c.sortKey)"
                            >
                                <button
                                    v-if="c.sortKey"
                                    type="button"
                                    class="hud-label inline-flex cursor-pointer items-center gap-1 hover:text-fg"
                                    :data-testid="`sort-${c.sortKey}`"
                                    @click="emit('sort', c.sortKey)"
                                >
                                    {{ c.label }} <span aria-hidden="true">{{ sort === c.sortKey ? (dir === 'asc' ? '↑' : '↓') : '' }}</span>
                                </button>
                                <span v-else class="hud-label">{{ c.label }}</span>
                            </th>
                            <th scope="col" class="w-10 px-3 py-2"><span class="sr-only">Peta</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id" class="border-b border-line/60 last:border-0 hover:bg-fg/5" :data-testid="`report-row-${r.id}`">
                            <td class="max-w-[16rem] truncate px-3 py-2 font-display font-semibold text-fg" :title="r.name">{{ r.name }}</td>
                            <td class="px-3 py-2 text-muted">{{ r.category ?? '–' }}</td>
                            <td class="px-3 py-2 text-muted">{{ r.city ?? '–' }}</td>
                            <td class="px-3 py-2 text-muted">{{ r.province ?? '–' }}</td>
                            <td class="max-w-[16rem] truncate px-3 py-2 text-muted" :title="r.address ?? undefined">{{ r.address ?? 'Belum tersedia' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span v-if="r.phone" class="num inline-flex items-center gap-1.5 text-fg">{{ r.phone }} <SimulasiBadge v-if="r.phone_simulated" field="Telepon" /></span>
                                <span v-else class="text-muted">Tidak ada</span>
                            </td>
                            <td class="num px-3 py-2 text-right text-muted">{{ r.rating === null ? '–' : formatNumber(r.rating, 1) }}</td>
                            <td class="num px-3 py-2 text-right text-muted">{{ formatNumber(r.review_count) }}</td>
                            <td class="px-3 py-2 text-right">
                                <span class="num font-semibold" :class="BAND_CLASSES[r.score_band].text" :aria-label="`Skor ${r.score}, ${BAND_LABEL[r.score_band]}`">{{ r.score }}</span>
                                <span class="ml-1.5 text-2xs text-muted" aria-hidden="true">{{ BAND_LABEL[r.score_band] }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="rounded-full border px-2 py-0.5 text-xs" :class="[STATUS_CLASSES[r.pipeline_status].soft, STATUS_CLASSES[r.pipeline_status].text, STATUS_CLASSES[r.pipeline_status].border]">
                                    {{ STATUS_LABEL[r.pipeline_status] }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-muted">{{ r.handled_by ?? '–' }}</td>
                            <td class="num whitespace-nowrap px-3 py-2 text-xs text-muted">{{ r.last_contacted_at ? relativeTime(r.last_contacted_at) : 'Belum pernah' }}</td>
                            <td class="num whitespace-nowrap px-3 py-2 text-right text-muted">{{ formatDistance(r.distance_m) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="buildMapUrl(r.id)" class="grid h-8 w-8 place-items-center rounded-full text-muted hover:bg-fg/10 hover:text-fg" :aria-label="`Lihat ${r.name} di peta`" :data-testid="`report-map-link-${r.id}`">
                                    <Icon name="navigation" :size="16" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Paginasi laporan" data-testid="report-pagination">
                <p class="num text-muted" role="status">{{ formatNumber(from) }}–{{ formatNumber(to) }} dari {{ formatNumber(meta.total) }} prospek</p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-muted">
                        Per halaman
                        <select
                            class="h-9 rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg"
                            :value="meta.per_page"
                            data-testid="report-per-page"
                            @change="emit('per-page', Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="n in [25, 50, 100]" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </label>
                    <Button size="sm" variant="secondary" :disabled="meta.current_page <= 1 || loading" data-testid="report-prev" @click="emit('page', meta.current_page - 1)">Sebelumnya</Button>
                    <span class="num text-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <Button size="sm" variant="secondary" :disabled="meta.current_page >= meta.last_page || loading" data-testid="report-next" @click="emit('page', meta.current_page + 1)">Berikutnya</Button>
                </div>
            </nav>
        </template>
    </div>
</template>
