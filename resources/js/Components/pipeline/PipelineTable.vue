<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ProspectBadges from '@/Components/prospect/ProspectBadges.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { BAND_CLASSES, STATUS_CLASSES } from '@/lib/colors';
import { BAND_LABEL, COLUMN_LABEL } from '@/lib/labels';
import { useI18n } from '@/i18n';
import { formatNumber, relativeTime } from '@/lib/format';
import type { PipelineCard } from '@/types';
import { buildMapUrl, type SortDir, type SortKey, type TableMeta } from './pipeline';

const props = withDefaults(
    defineProps<{
        rows: PipelineCard[];
        meta: TableMeta;
        sort: SortKey;
        dir: SortDir;
        loading?: boolean;
        error?: string;
        selected?: number[];
        activeId?: number | null;
    }>(),
    { loading: false, error: '', selected: () => [], activeId: null },
);
const emit = defineEmits<{
    sort: [key: SortKey];
    page: [page: number];
    'per-page': [perPage: number];
    open: [id: number];
    toggle: [id: number];
    'toggle-all': [checked: boolean];
    retry: [];
    'reset-filter': [];
}>();

const { t } = useI18n();
const showStatus = computed(() => props.rows.some((r) => r.pipeline_status !== undefined));
const allChecked = computed(() => props.rows.length > 0 && props.rows.every((r) => props.selected.includes(r.id)));
const someChecked = computed(() => !allChecked.value && props.rows.some((r) => props.selected.includes(r.id)));

const ariaSort = (key: SortKey): 'ascending' | 'descending' | 'none' => (props.sort !== key ? 'none' : props.dir === 'asc' ? 'ascending' : 'descending');

const from = computed(() => (props.meta.total === 0 ? 0 : (props.meta.current_page - 1) * props.meta.per_page + 1));
const to = computed(() => Math.min(props.meta.total, props.meta.current_page * props.meta.per_page));
</script>

<template>
    <div data-testid="pipeline-table-wrap">
        <ErrorState v-if="error && rows.length === 0" :title="t('pipeline.table.loadFailed')" :message="error" :retrying="loading" @retry="emit('retry')" />

        <div v-else-if="loading && rows.length === 0" class="space-y-2 rounded-panel border border-line bg-surface/60 p-4" data-testid="table-skeleton">
            <Skeleton v-for="n in 8" :key="n" height="2.25rem" />
        </div>

        <EmptyState v-else-if="rows.length === 0" :title="t('pipeline.table.emptyTitle')" :description="t('pipeline.table.emptyDescription')" icon="filter" data-testid="table-empty">
            <Button size="sm" variant="secondary" @click="emit('reset-filter')">{{ t('pipeline.board.resetFilter') }}</Button>
        </EmptyState>

        <template v-else>
            <p v-if="error" class="mb-2 rounded-chip border border-danger/40 bg-danger/10 p-2 text-sm text-danger" role="alert">{{ error }}</p>
            <div class="overflow-x-auto rounded-panel border border-line bg-surface/60" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                <table class="w-full min-w-[56rem] border-collapse text-left text-sm" data-testid="pipeline-table">
                    <caption class="sr-only">{{ t('pipeline.table.caption') }}</caption>
                    <thead>
                        <tr class="border-b border-line text-xs text-muted">
                            <th scope="col" class="w-10 px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer accent-[var(--c-focus)]"
                                    :checked="allChecked"
                                    :indeterminate="someChecked"
                                    :aria-label="t('pipeline.table.selectAll')"
                                    data-testid="select-all"
                                    @change="emit('toggle-all', ($event.target as HTMLInputElement).checked)"
                                />
                            </th>
                            <th scope="col" class="px-3 py-2" :aria-sort="ariaSort('name')">
                                <button type="button" class="hud-label inline-flex cursor-pointer items-center gap-1 hover:text-fg" data-testid="sort-name" @click="emit('sort', 'name')">
                                    {{ t('pipeline.table.name') }} <span aria-hidden="true">{{ sort === 'name' ? (dir === 'asc' ? '↑' : '↓') : '' }}</span>
                                </button>
                            </th>
                            <th scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.category') }}</th>
                            <th scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.city') }}</th>
                            <th scope="col" class="px-3 py-2" :aria-sort="ariaSort('score')">
                                <button type="button" class="hud-label inline-flex cursor-pointer items-center gap-1 hover:text-fg" data-testid="sort-score" @click="emit('sort', 'score')">
                                    {{ t('pipeline.table.score') }} <span aria-hidden="true">{{ sort === 'score' ? (dir === 'asc' ? '↑' : '↓') : '' }}</span>
                                </button>
                            </th>
                            <th v-if="showStatus" scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.status') }}</th>
                            <th scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.phone') }}</th>
                            <th scope="col" class="px-3 py-2" :aria-sort="ariaSort('last_contacted_at')">
                                <button type="button" class="hud-label inline-flex cursor-pointer items-center gap-1 hover:text-fg" data-testid="sort-last_contacted_at" @click="emit('sort', 'last_contacted_at')">
                                    {{ t('pipeline.table.lastContacted') }} <span aria-hidden="true">{{ sort === 'last_contacted_at' ? (dir === 'asc' ? '↑' : '↓') : '' }}</span>
                                </button>
                            </th>
                            <th scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.handledBy') }}</th>
                            <th scope="col" class="hud-label px-3 py-2">{{ t('pipeline.table.badges') }}</th>
                            <th scope="col" class="w-10 px-3 py-2"><span class="sr-only">{{ t('pipeline.table.map') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in rows"
                            :key="r.id"
                            class="border-b border-line/60 last:border-0 hover:bg-fg/5"
                            :class="[activeId === r.id ? 'bg-focus/10' : '', r.do_not_contact ? 'opacity-70' : '']"
                            :data-testid="`table-row-${r.id}`"
                        >
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer accent-[var(--c-focus)]"
                                    :checked="selected.includes(r.id)"
                                    :aria-label="t('pipeline.board.select', { name: r.name })"
                                    :data-testid="`select-${r.id}`"
                                    @change="emit('toggle', r.id)"
                                />
                            </td>
                            <td class="max-w-[16rem] px-3 py-2">
                                <button type="button" class="block max-w-full cursor-pointer truncate text-left font-display font-semibold text-fg hover:underline" :data-testid="`open-${r.id}`" @click="emit('open', r.id)">
                                    {{ r.name }}
                                </button>
                            </td>
                            <td class="px-3 py-2 text-muted">{{ r.category }}</td>
                            <td class="px-3 py-2 text-muted">{{ r.city ?? '–' }}</td>
                            <td class="px-3 py-2">
                                <span class="num font-semibold" :class="BAND_CLASSES[r.score_band].text" :aria-label="t('pipeline.table.scoreAria', { score: r.score, band: BAND_LABEL[r.score_band] })">{{ r.score }}</span>
                                <span class="ml-1.5 text-2xs text-muted" aria-hidden="true">{{ BAND_LABEL[r.score_band] }}</span>
                            </td>
                            <td v-if="showStatus" class="px-3 py-2">
                                <span v-if="r.pipeline_status" class="rounded-full border px-2 py-0.5 text-xs" :class="[STATUS_CLASSES[r.pipeline_status].soft, STATUS_CLASSES[r.pipeline_status].text, STATUS_CLASSES[r.pipeline_status].border]">
                                    {{ COLUMN_LABEL[r.pipeline_status] }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span v-if="r.phone_present" class="inline-flex items-center gap-1 text-fg"><Icon name="phone" :size="12" /> {{ t('pipeline.table.hasPhone') }}</span>
                                <span v-else class="text-muted">{{ t('pipeline.table.noPhone') }}</span>
                            </td>
                            <td class="num px-3 py-2 text-xs text-muted">{{ r.last_contacted_at ? relativeTime(r.last_contacted_at) : t('pipeline.table.never') }}</td>
                            <td class="px-3 py-2 text-muted">{{ r.handled_by ?? '–' }}</td>
                            <td class="px-3 py-2"><ProspectBadges :badges="r.badges" /></td>
                            <td class="px-3 py-2">
                                <Link :href="buildMapUrl(r.id)" class="grid h-8 w-8 place-items-center rounded-full text-muted hover:bg-fg/10 hover:text-fg" :aria-label="t('pipeline.table.viewOnMap', { name: r.name })" :data-testid="`map-link-${r.id}`">
                                    <Icon name="navigation" :size="16" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm" :aria-label="t('pipeline.table.pagination')" data-testid="table-pagination">
                <p class="num text-muted" role="status">{{ t('pipeline.table.range', { from: formatNumber(from), to: formatNumber(to), total: formatNumber(meta.total) }) }}</p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-muted">
                        {{ t('pipeline.table.perPage') }}
                        <select
                            class="h-9 rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg"
                            :value="meta.per_page"
                            data-testid="per-page"
                            @change="emit('per-page', Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="n in [25, 50, 100]" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </label>
                    <Button size="sm" variant="secondary" :disabled="meta.current_page <= 1 || loading" data-testid="page-prev" @click="emit('page', meta.current_page - 1)">{{ t('pipeline.table.prev') }}</Button>
                    <span class="num text-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <Button size="sm" variant="secondary" :disabled="meta.current_page >= meta.last_page || loading" data-testid="page-next" @click="emit('page', meta.current_page + 1)">{{ t('pipeline.table.next') }}</Button>
                </div>
            </nav>
        </template>
    </div>
</template>
