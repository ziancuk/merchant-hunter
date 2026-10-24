<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import Sparkline from '@/Components/charts/Sparkline.vue';
import { formatDuration } from '@/Components/master/duration';
import PageHeader from '@/Components/master/PageHeader.vue';
import Pagination from '@/Components/master/Pagination.vue';
import Panel from '@/Components/master/Panel.vue';
import type { PageMeta, SchedulerHealth, SchedulerRunRow } from '@/Components/master/types';
import Badge, { type BadgeTone } from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { api, normalizeError } from '@/lib/api';
import { formatDateTime, formatNumber, relativeTime } from '@/lib/format';

const props = defineProps<{ runs?: { data: SchedulerRunRow[]; meta: PageMeta } | null; health?: SchedulerHealth | null }>();

const rows = ref<SchedulerRunRow[]>(props.runs?.data ?? []);
const meta = ref<PageMeta>(props.runs?.meta ?? { current_page: 1, last_page: 1, total: 0, per_page: 20 });
const loading = ref(!props.runs);
const error = ref<string | null>(null);

async function load(page = 1): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await api.get<{ data: SchedulerRunRow[]; meta: PageMeta }>('/api/scheduler/runs', { params: { page } });
        rows.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = normalizeError(e).message;
    } finally {
        loading.value = false;
    }
}
onMounted(() => {
    if (!props.runs) void load(1);
});

const TONE: Record<string, BadgeTone> = { success: 'success', completed: 'success', failed: 'danger', partial: 'warm', running: 'info', skipped: 'neutral' };
const tone = (s: string): BadgeTone => TONE[s] ?? 'neutral';

const trend = computed(() => [...rows.value].reverse().map((r) => r.prospects_new));
const health = computed(() => props.health ?? null);
const healthTone = computed(() => tone(health.value?.status ?? ''));

const open = ref(false);
const detail = ref<SchedulerRunRow | null>(null);
const detailLoading = ref(false);
const detailError = ref<string | null>(null);
let detailId = 0;

async function showDetail(run: SchedulerRunRow): Promise<void> {
    detail.value = run;
    open.value = true;
    detailId = run.id;
    detailLoading.value = true;
    detailError.value = null;
    try {
        const { data } = await api.get<SchedulerRunRow>(`/api/scheduler/runs/${run.id}`);
        if (detailId === run.id) detail.value = data;
    } catch (e) {
        if (detailId === run.id) detailError.value = normalizeError(e).message;
    } finally {
        if (detailId === run.id) detailLoading.value = false;
    }
}
</script>

<template>
    <Head title="Riwayat penjadwal" />
    <AppShell>
        <div class="mx-auto w-full max-w-7xl">
            <PageHeader eyebrow="Operasional" title="Riwayat penjadwal" description="Setiap run sinkronisasi prospek: berapa outlet diproses, prospek baru, dan galat bila ada.">
                <Button size="sm" :loading="loading" data-testid="scheduler-refresh" @click="load(meta.current_page)">
                    <Icon name="refresh" :size="14" />
                    Muat ulang
                </Button>
            </PageHeader>

            <div class="mb-4 grid gap-4 md:grid-cols-3">
                <GlassPanel data-testid="scheduler-health">
                    <p class="hud-label">Run sinkronisasi terakhir</p>
                    <div v-if="health" class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge :tone="healthTone" dot size="md">{{ health.status ?? 'Belum pernah' }}</Badge>
                        <span class="text-sm text-muted">{{ health.last_run_at ? relativeTime(health.last_run_at) : '–' }}</span>
                    </div>
                    <p v-else class="mt-2 text-sm text-muted">Belum ada data.</p>
                </GlassPanel>
                <GlassPanel>
                    <p class="hud-label">Umur · gagal 7 hari</p>
                    <p class="num mt-2 text-xl font-semibold">
                        {{ health?.age_hours != null ? `${formatNumber(health.age_hours, 1)} jam` : '–' }}
                        <span class="text-muted">·</span>
                        <span :class="(health?.failed_last_7d ?? 0) > 0 ? 'text-danger' : ''">{{ formatNumber(health?.failed_last_7d ?? 0) }}</span>
                    </p>
                </GlassPanel>
                <GlassPanel>
                    <p class="hud-label">Prospek baru per run</p>
                    <div class="mt-2">
                        <Sparkline :values="trend" label="Prospek baru per run, run lama ke terbaru" :width="220" :height="40" />
                    </div>
                </GlassPanel>
            </div>

            <Panel title="Run">
                <Skeleton v-if="loading && rows.length === 0" :lines="6" />
                <ErrorState v-else-if="error" :message="error" :retrying="loading" @retry="load(meta.current_page)" />
                <EmptyState v-else-if="rows.length === 0" title="Belum ada run" description="Riwayat muncul setelah penjadwal atau perintah hunter:sync berjalan." icon="clock" />
                <template v-else>
                    <div class="overflow-x-auto" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                        <table class="w-full min-w-[56rem] border-collapse text-left text-sm" data-testid="scheduler-table">
                            <caption class="sr-only">Riwayat run penjadwal</caption>
                            <thead>
                                <tr class="hud-label border-b border-line">
                                    <th scope="col" class="py-2 pr-3 font-normal">Mulai</th>
                                    <th scope="col" class="py-2 pr-3 font-normal">Perintah</th>
                                    <th scope="col" class="py-2 pr-3 font-normal">Status</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">Durasi</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">Outlet ok/total</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">Baru</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">Diperbarui</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">Ditutup</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-normal">API</th>
                                    <th scope="col" class="py-2 font-normal"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in rows" :key="r.id" class="border-b border-line/60 align-top" :data-testid="`scheduler-row-${r.id}`">
                                    <td class="py-2.5 pr-3">
                                        <span class="num block">{{ formatDateTime(r.started_at) }}</span>
                                        <span v-if="r.error" class="block max-w-[16rem] truncate text-2xs text-danger" :title="r.error">{{ r.error }}</span>
                                    </td>
                                    <td class="num py-2.5 pr-3 text-xs">{{ r.command }}</td>
                                    <td class="py-2.5 pr-3"><Badge :tone="tone(r.status)">{{ r.status }}</Badge></td>
                                    <td class="num py-2.5 pr-3 text-right">{{ formatDuration(r.duration_ms) }}</td>
                                    <td class="num py-2.5 pr-3 text-right">
                                        {{ r.outlets_ok }}/{{ r.outlets_total }}<span v-if="r.outlets_failed" class="text-danger"> ({{ r.outlets_failed }} gagal)</span>
                                    </td>
                                    <td class="num py-2.5 pr-3 text-right">{{ formatNumber(r.prospects_new) }}</td>
                                    <td class="num py-2.5 pr-3 text-right">{{ formatNumber(r.prospects_updated) }}</td>
                                    <td class="num py-2.5 pr-3 text-right">{{ formatNumber(r.prospects_closed) }}</td>
                                    <td class="num py-2.5 pr-3 text-right">{{ formatNumber(r.api_calls) }}</td>
                                    <td class="py-2.5 text-right"><Button size="sm" variant="ghost" :aria-label="`Detail run ${r.id}`" :data-testid="`scheduler-detail-${r.id}`" @click="showDetail(r)">Detail</Button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="meta" :busy="loading" @page="load" />
                </template>
            </Panel>
        </div>

        <Drawer v-model="open" :title="detail ? `Run #${detail.id}` : 'Detail run'" :description="detail ? `${detail.command} · ${formatDateTime(detail.started_at)}` : undefined" width-class="sm:max-w-[560px]">
            <div class="grid gap-4 p-4" data-testid="scheduler-drawer">
                <p v-if="detail?.error" class="num rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-xs text-danger">{{ detail.error }}</p>
                <Skeleton v-if="detailLoading" :lines="5" />
                <ErrorState v-else-if="detailError" :message="detailError" @retry="detail && showDetail(detail)" />
                <EmptyState v-else-if="!detail?.outlets?.length" title="Tidak ada rincian outlet" description="Run ini tidak mencatat hasil per outlet." icon="store" />
                <ul v-else class="m-0 flex list-none flex-col p-0" data-testid="scheduler-outlets">
                    <li v-for="o in detail.outlets" :key="o.id" class="border-b border-line/60 py-2.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate font-medium">{{ o.outlet ?? `Outlet #${o.outlet_id}` }}</span>
                            <Badge :tone="tone(o.status)">{{ o.status }}</Badge>
                        </div>
                        <p class="num mt-0.5 text-2xs text-muted">
                            {{ o.provider ?? '–' }} · ambil {{ o.fetched_count }} · baru {{ o.new_count }} · diperbarui {{ o.updated_count }} · ditutup {{ o.closed_count }} · {{ formatDuration(o.duration_ms) }}
                        </p>
                        <p v-if="o.error" class="num mt-1 text-xs text-danger">{{ o.error }}</p>
                    </li>
                </ul>
            </div>
        </Drawer>
    </AppShell>
</template>
