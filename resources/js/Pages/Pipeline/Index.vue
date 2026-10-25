<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import BulkBar from '@/Components/pipeline/BulkBar.vue';
import KanbanBoard from '@/Components/pipeline/KanbanBoard.vue';
import PipelineTable from '@/Components/pipeline/PipelineTable.vue';
import RejectionDialog from '@/Components/pipeline/RejectionDialog.vue';
import {
    buildExportUrl,
    buildMapUrl,
    buildPageUrl,
    buildRouteUrl,
    MAX_ROUTE_STOPS,
    type BoardColumn,
    type PipelineView,
} from '@/Components/pipeline/pipeline';
import { COLUMN_LABEL, skipReasonLabel } from '@/lib/labels';
import { usePipelineBoard, usePipelineTable } from '@/Components/pipeline/usePipeline';
import FilterPanel, { type FilterOptions } from '@/Components/prospect/FilterPanel.vue';
import ProspectDrawer, { type RejectionReason } from '@/Components/prospect/ProspectDrawer.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import { countActiveFilters, queryToFilter } from '@/composables/useFilters';
import { useToasts } from '@/composables/useToasts';
import AppShell from '@/Layouts/AppShell.vue';
import { api, normalizeError } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import { PIPELINE_STATUSES, type PipelineStatus, type ProspectDetail, type ProspectFilter } from '@/types';

/* eslint-disable vue/prop-name-casing -- Inertia props are snake_case (CONTRACTS §1) */
const props = defineProps<{
    view: PipelineView;
    columns: Partial<Record<PipelineStatus, Partial<BoardColumn>>>;
    filter_options: FilterOptions;
    rejection_reasons: RejectionReason[];
    contact_rules?: { warn_days?: number; min_interval_days?: number; max_overrides?: number };
}>();

const { push } = useToasts();

const view = ref<PipelineView>(props.view === 'table' ? 'table' : 'kanban');
const filter = ref<ProspectFilter>(typeof window !== 'undefined' ? queryToFilter(window.location.search) : {});
const board = usePipelineBoard(props.columns, filter);
const table = usePipelineTable(filter);

const filterOpen = ref(false);
const filterRoot = ref<HTMLElement | null>(null);
const openId = ref<number | null>(null);
const selected = ref<number[]>([]);
const announcement = ref('');
const bulkBusy = ref(false);
const bulkResult = ref<{ updated: number; skipped: { id: number; name: string; reason: string }[] } | null>(null);
/** A move or bulk action waiting for a rejection reason (dialog open). */
const pendingReject = ref<{ kind: 'move'; id: number } | { kind: 'bulk' } | null>(null);

const activeFilters = computed(() => countActiveFilters(filter.value));
const exportUrl = computed(() => buildExportUrl(filter.value));
const boardTotal = computed(() => PIPELINE_STATUSES.reduce((sum, s) => sum + board.columns.value[s].total, 0));
const boardHasCards = computed(() => PIPELINE_STATUSES.some((s) => board.columns.value[s].cards.length > 0));
const total = computed(() => (view.value === 'kanban' ? boardTotal.value : table.meta.value.total));
const rules = computed(() => props.contact_rules ?? {});

function syncUrl(): void {
    if (typeof window === 'undefined') return;
    try {
        window.history.replaceState(window.history.state, '', buildPageUrl(view.value, filter.value));
    } catch {
        /* history may be unavailable (sandboxed previews) */
    }
}

function refresh(): Promise<void> {
    return view.value === 'kanban' ? board.reload() : table.fetch();
}

function setView(next: PipelineView): void {
    if (next === view.value) return;
    view.value = next;
    syncUrl();
    void refresh();
}

function onFilter(next: ProspectFilter): void {
    filter.value = next;
    selected.value = [];
    table.resetPage();
    syncUrl();
    void refresh();
}

function resetFilter(): void {
    onFilter({});
}

onMounted(() => {
    if (view.value === 'table') void table.fetch();
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onDocKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onDocKey);
});

function onDocClick(e: MouseEvent): void {
    if (filterOpen.value && filterRoot.value && !filterRoot.value.contains(e.target as Node)) filterOpen.value = false;
}
function onDocKey(e: KeyboardEvent): void {
    if (e.key === 'Escape' && filterOpen.value) filterOpen.value = false;
}

// Inertia revisits (e.g. clicking "Pipeline" in the dock) hand us fresh props.
watch(
    () => props.columns,
    (cols) => {
        if (view.value === 'kanban') board.reset(cols);
    },
);

// ---- Kanban moves -----------------------------------------------------------------------------

async function onMove(id: number, from: PipelineStatus, to: PipelineStatus): Promise<void> {
    if (from === to) return;
    if (to === 'lost') {
        pendingReject.value = { kind: 'move', id };
        return;
    }
    const name = board.find(id)?.name ?? 'Prospek';
    if (await board.move(id, to)) announcement.value = `${name} dipindahkan ke ${COLUMN_LABEL[to]}.`;
}

async function onRejectionConfirm(reasonId: number): Promise<void> {
    const pending = pendingReject.value;
    if (!pending) return;
    if (pending.kind === 'move') {
        pendingReject.value = null;
        const name = board.find(pending.id)?.name ?? 'Prospek';
        if (await board.move(pending.id, 'lost', reasonId)) announcement.value = `${name} dipindahkan ke ${COLUMN_LABEL.lost}.`;
        return;
    }
    await runBulk({ action: 'status', status: 'lost', rejection_reason_id: reasonId });
    pendingReject.value = null;
}

// ---- Selection + bulk -------------------------------------------------------------------------

function toggleSelect(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}

function toggleAll(checked: boolean): void {
    const ids = table.rows.value.map((r) => r.id);
    selected.value = checked ? [...new Set([...selected.value, ...ids])] : selected.value.filter((id) => !ids.includes(id));
}

function nameOf(id: number): string {
    return board.find(id)?.name ?? table.rows.value.find((r) => r.id === id)?.name ?? `Prospek #${id}`;
}

interface BulkBody {
    action: 'status' | 'opt_out';
    status?: PipelineStatus;
    rejection_reason_id?: number;
}

async function runBulk(body: BulkBody): Promise<void> {
    const ids = [...selected.value];
    if (ids.length === 0) return;
    bulkBusy.value = true;
    try {
        const res = await api.post<{ updated: number; skipped: { id: number; reason: string }[] }>('/api/prospects/bulk', { ids, ...body });
        bulkResult.value = {
            updated: res.data.updated,
            skipped: res.data.skipped.map((s) => ({ id: s.id, name: nameOf(s.id), reason: s.reason })),
        };
        push(`${res.data.updated} prospek diperbarui${res.data.skipped.length ? `, ${res.data.skipped.length} dilewati` : ''}.`, res.data.updated > 0 ? 'success' : 'info');
        selected.value = [];
        await refresh();
    } catch (e) {
        push(normalizeError(e).message, 'error', 7000);
    } finally {
        bulkBusy.value = false;
    }
}

function onBulkStatus(status: PipelineStatus): void {
    bulkResult.value = null;
    if (status === 'lost') pendingReject.value = { kind: 'bulk' };
    else void runBulk({ action: 'status', status });
}

function onBulkOptOut(): void {
    bulkResult.value = null;
    void runBulk({ action: 'opt_out' });
}

function planRoute(): void {
    if (selected.value.length < 2 || selected.value.length > MAX_ROUTE_STOPS) return;
    router.visit(buildRouteUrl(selected.value));
}

// ---- Drawer -----------------------------------------------------------------------------------

function onDrawerUpdated(detail: ProspectDetail): void {
    board.applyDetail(detail);
    table.applyDetail(detail);
}

function onLocate(): void {
    if (openId.value !== null) router.visit(buildMapUrl(openId.value));
}

const boardLoading = computed(() => board.loading.value && !boardHasCards.value);
const boardRefreshing = computed(() => board.loading.value && boardHasCards.value);
</script>

<template>
    <AppShell>
        <Head title="Pipeline" />
        <div class="mx-auto w-full max-w-[1600px] space-y-4">
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
                            @click="setView(v)"
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
                            <FilterPanel :model-value="filter" :options="filter_options" :collapsible="false" @update:model-value="onFilter" />
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

            <p class="sr-only" role="status" aria-live="polite" data-testid="announcer">{{ announcement }}</p>

            <section
                v-if="bulkResult"
                class="rounded-panel border border-line bg-surface/70 p-3 text-sm"
                role="status"
                data-testid="bulk-result"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-fg">
                        <strong class="num">{{ bulkResult.updated }}</strong> prospek diperbarui<template v-if="bulkResult.skipped.length">, <strong class="num">{{ bulkResult.skipped.length }}</strong> dilewati</template>.
                    </p>
                    <Button size="sm" variant="ghost" aria-label="Tutup hasil aksi massal" icon-only @click="bulkResult = null"><Icon name="close" :size="14" /></Button>
                </div>
                <ul v-if="bulkResult.skipped.length" class="mt-2 space-y-1 text-muted" data-testid="bulk-skipped">
                    <li v-for="s in bulkResult.skipped" :key="s.id"><span class="text-fg">{{ s.name }}</span>: {{ skipReasonLabel(s.reason) }}</li>
                </ul>
            </section>

            <!-- Kanban -->
            <template v-if="view === 'kanban'">
                <ErrorState v-if="board.error.value && !boardHasCards" title="Gagal memuat pipeline" :message="board.error.value" :retrying="board.loading.value" @retry="board.reload()" />
                <EmptyState
                    v-else-if="!board.loading.value && boardTotal === 0"
                    title="Belum ada prospek di pipeline"
                    :description="activeFilters ? 'Tidak ada prospek yang cocok dengan filter ini.' : 'Prospek akan muncul setelah sinkronisasi pertama selesai.'"
                    icon="kanban"
                    data-testid="board-empty"
                >
                    <Button v-if="activeFilters" size="sm" variant="secondary" @click="resetFilter">Atur ulang filter</Button>
                </EmptyState>
                <template v-else>
                    <p v-if="board.error.value" class="rounded-chip border border-danger/40 bg-danger/10 p-2 text-sm text-danger" role="alert">{{ board.error.value }}</p>
                    <KanbanBoard
                        :columns="board.columns.value"
                        :loading="boardLoading"
                        :refreshing="boardRefreshing"
                        :loading-more="board.loadingMore.value"
                        :selected="selected"
                        :active-id="openId"
                        @open="openId = $event"
                        @move="onMove"
                        @load-more="board.loadMore"
                        @toggle-select="toggleSelect"
                    />
                </template>
            </template>

            <!-- Table -->
            <PipelineTable
                v-else
                :rows="table.rows.value"
                :meta="table.meta.value"
                :sort="table.sort.value"
                :dir="table.dir.value"
                :loading="table.loading.value"
                :error="table.error.value"
                :selected="selected"
                :active-id="openId"
                @sort="table.setSort"
                @page="table.setPage"
                @per-page="table.setPerPage"
                @open="openId = $event"
                @toggle="toggleSelect"
                @toggle-all="toggleAll"
                @retry="table.fetch()"
                @reset-filter="resetFilter"
            />

            <p v-if="rules.min_interval_days" class="hud-label text-center normal-case tracking-normal" data-testid="contact-rules">
                Aturan kontak: peringatan bila dihubungi dalam {{ rules.warn_days }} hari · jeda minimum {{ rules.min_interval_days }} hari · override maks. {{ rules.max_overrides }}x per prospek.
            </p>
        </div>

        <BulkBar
            v-if="selected.length > 0"
            :count="selected.length"
            :busy="bulkBusy"
            @status="onBulkStatus"
            @opt-out="onBulkOptOut"
            @route="planRoute"
            @clear="selected = []"
        />

        <RejectionDialog
            :open="pendingReject !== null"
            :reasons="rejection_reasons"
            :title="pendingReject?.kind === 'bulk' ? `Tolak ${selected.length} prospek` : 'Alasan penolakan'"
            :busy="bulkBusy"
            @confirm="onRejectionConfirm"
            @cancel="pendingReject = null"
        />

        <ProspectDrawer
            :prospect-id="openId"
            :rejection-reasons="rejection_reasons"
            @close="openId = null"
            @updated="onDrawerUpdated"
            @contacted="onDrawerUpdated"
            @locate="onLocate"
        />
    </AppShell>
</template>
