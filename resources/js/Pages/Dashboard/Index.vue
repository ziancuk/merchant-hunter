<script setup lang="ts">
/* eslint-disable vue/prop-name-casing -- Inertia props mirror the snake_case JSON contract (CONTRACTS §1) */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import BarList from '@/Components/charts/BarList.vue';
import Cartogram, { type CartogramItem } from '@/Components/charts/Cartogram.vue';
import FunnelChart from '@/Components/charts/FunnelChart.vue';
import { provinceShort } from '@/Components/charts/provinceShort';
import DigestPanel from '@/Components/master/DigestPanel.vue';
import LinkButton from '@/Components/master/LinkButton.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import Panel from '@/Components/master/Panel.vue';
import type { DashboardKpis, Digest, Funnel, PenetrationRow, SchedulerHealth, TopCategory, UserActivity, WhitespaceTop } from '@/Components/master/types';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import KpiCounter from '@/Components/ui/KpiCounter.vue';
import { formatDistance, formatNumber, formatPercent, relativeTime } from '@/lib/format';
import { STATUS_VAR } from '@/lib/colors';
import { STATUS_LABEL } from '@/lib/labels';

const props = defineProps<{
    kpis: DashboardKpis;
    funnel: Funnel;
    penetration: PenetrationRow[];
    top_categories: TopCategory[];
    whitespace_top: WhitespaceTop[];
    activity_by_user: UserActivity[];
    scheduler_health: SchedulerHealth;
    digest: Digest;
}>();

const kpiTiles = computed(() => [
    { id: 'kpi-outlets', label: 'Outlet aktif', value: props.kpis?.outlets ?? 0, decimals: 0, suffix: '' },
    { id: 'kpi-prospects-active', label: 'Prospek aktif', value: props.kpis?.prospects_active ?? 0, decimals: 0, suffix: '' },
    { id: 'kpi-new-7d', label: 'Baru 7 hari', value: props.kpis?.new_7d ?? 0, decimals: 0, suffix: '' },
    { id: 'kpi-conversion', label: 'Tingkat konversi', value: (props.kpis?.conversion_rate ?? 0) * 100, decimals: 1, suffix: '%' },
    { id: 'kpi-deals', label: 'Deal bulan ini', value: props.kpis?.deals_this_month ?? 0, decimals: 0, suffix: '' },
]);

const stages = computed(() => [
    { key: 'found', label: 'Ditemukan', value: props.funnel?.found ?? 0, color: STATUS_VAR.new },
    { key: 'contacted', label: 'Dihubungi', value: props.funnel?.contacted ?? 0, color: STATUS_VAR.contacted },
    { key: 'demo', label: 'Demo', value: props.funnel?.demo ?? 0, color: STATUS_VAR.demo },
    { key: 'won', label: STATUS_LABEL.won, value: props.funnel?.won ?? 0, color: STATUS_VAR.won },
]);
const lost = computed(() => ({ label: STATUS_LABEL.lost, value: props.funnel?.lost ?? 0, color: STATUS_VAR.lost }));

type SortKey = 'penetration' | 'prospects' | 'name';
const sortKey = ref<SortKey>('penetration');
const SORTS: { key: SortKey; label: string }[] = [
    { key: 'penetration', label: 'Penetrasi' },
    { key: 'prospects', label: 'Prospek' },
    { key: 'name', label: 'Nama' },
];
const tiles = computed<CartogramItem[]>(() => {
    const rows = [...(props.penetration ?? [])];
    rows.sort((a, b) => {
        if (sortKey.value === 'name') return a.province.localeCompare(b.province, 'id');
        if (sortKey.value === 'prospects') return b.prospects - a.prospects || a.province.localeCompare(b.province, 'id');
        return b.penetration - a.penetration || b.prospects - a.prospects || a.province.localeCompare(b.province, 'id');
    });
    return rows.map((r) => ({
        id: r.province_id,
        label: r.province,
        short: provinceShort(r.province),
        value: r.penetration,
        details: [`Outlet: ${formatNumber(r.outlets)}`, `Prospek: ${formatNumber(r.prospects)}`, `Deal: ${formatNumber(r.won)}`],
    }));
});
const fmtPenetration = (n: number) => formatPercent(n, 2);

const categoryBars = computed(() =>
    (props.top_categories ?? []).map((c) => ({ label: c.category, value: c.count, hint: `skor rata-rata ${c.avg_score}` })),
);

const health = computed(() => props.scheduler_health);
const healthTone = computed(() => {
    const s = health.value?.status;
    if (!s) return 'neutral' as const;
    if (s === 'failed') return 'danger' as const;
    if (s === 'success' || s === 'completed') return (health.value.age_hours ?? 0) > 36 ? ('warm' as const) : ('success' as const);
    return 'info' as const;
});
const healthLabel = computed(() => {
    const s = health.value?.status;
    if (!s) return 'Belum pernah jalan';
    if (s === 'success' || s === 'completed') return (health.value.age_hours ?? 0) > 36 ? 'Terlambat' : 'Sehat';
    if (s === 'failed') return 'Gagal';
    return s;
});

const maxActivity = computed(() => Math.max(1, ...(props.activity_by_user ?? []).map((u) => u.contacted)));
const whitespaceHref = (w: WhitespaceTop) => `/?lat=${w.lat}&lng=${w.lng}&mode=whitespace`;
</script>

<template>
    <Head title="Dasbor" />
    <AppShell>
        <div class="mx-auto w-full max-w-7xl">
            <PageHeader eyebrow="Pusat komando · ringkasan" title="Dasbor" description="Ikhtisar prospek, corong penjualan, dan kesehatan sinkronisasi.">
                <LinkButton href="/dashboard/export.csv" download data-testid="export-csv">
                    <Icon name="send" :size="14" class="rotate-90" />
                    Ekspor CSV
                </LinkButton>
            </PageHeader>

            <!-- KPI strip -->
            <ul class="m-0 mb-4 grid list-none grid-cols-2 gap-3 p-0 md:grid-cols-3 xl:grid-cols-5" aria-label="Indikator utama">
                <li v-for="k in kpiTiles" :key="k.id" :class="k.id === 'kpi-deals' ? 'col-span-2 md:col-span-1' : ''">
                    <GlassPanel :data-testid="k.id" class="h-full">
                        <KpiCounter :value="k.value" :label="k.label" :decimals="k.decimals" :suffix="k.suffix" />
                    </GlassPanel>
                </li>
            </ul>

            <div class="grid gap-4 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <Panel title="Corong penjualan" hint="Kumulatif: setiap tahap menghitung prospek yang pernah mencapainya." class="h-full">
                        <FunnelChart v-if="(funnel?.found ?? 0) > 0" label="Corong penjualan kumulatif" :stages="stages" :lost="lost" />
                        <EmptyState v-else title="Belum ada prospek" description="Corong terisi setelah sinkronisasi pertama berhasil." icon="chart" />
                    </Panel>
                </div>

                <div class="lg:col-span-5">
                    <Panel title="Kesehatan penjadwal" class="h-full">
                        <template #actions>
                            <Link href="/scheduler" class="text-xs text-focus underline-offset-2 hover:underline">Riwayat</Link>
                        </template>
                        <div class="flex items-center gap-3" data-testid="scheduler-health">
                            <Badge :tone="healthTone" dot size="md">{{ healthLabel }}</Badge>
                            <span class="text-sm text-muted">{{ health?.last_run_at ? relativeTime(health.last_run_at) : '–' }}</span>
                        </div>
                        <dl class="m-0 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="hud-label">Umur run terakhir</dt>
                                <dd class="num m-0 mt-0.5">{{ health?.age_hours != null ? `${formatNumber(health.age_hours, 1)} jam` : '–' }}</dd>
                            </div>
                            <div>
                                <dt class="hud-label">Gagal 7 hari</dt>
                                <dd class="num m-0 mt-0.5" :class="(health?.failed_last_7d ?? 0) > 0 ? 'text-danger' : ''">{{ formatNumber(health?.failed_last_7d ?? 0) }}</dd>
                            </div>
                        </dl>
                    </Panel>
                </div>

                <div class="lg:col-span-12">
                    <Panel title="Penetrasi per provinsi" hint="Kartogram petak: satu petak per provinsi, warna menurut penetrasi (deal ÷ prospek aktif).">
                        <template #actions>
                            <div role="group" aria-label="Urutkan petak" class="flex gap-1">
                                <button
                                    v-for="s in SORTS"
                                    :key="s.key"
                                    type="button"
                                    :aria-pressed="sortKey === s.key"
                                    :data-testid="`sort-${s.key}`"
                                    class="h-7 cursor-pointer rounded-full px-2.5 text-xs transition"
                                    :class="sortKey === s.key ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10'"
                                    @click="sortKey = s.key"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                        </template>
                        <Cartogram v-if="tiles.length" :items="tiles" metric="Penetrasi" :format="fmtPenetration" label="Penetrasi per provinsi" />
                        <EmptyState v-else title="Data provinsi belum tersedia" icon="map" />
                    </Panel>
                </div>

                <div class="lg:col-span-4">
                    <Panel title="Kategori teratas" class="h-full">
                        <BarList v-if="categoryBars.length" :items="categoryBars" label="Jumlah prospek per kategori" />
                        <EmptyState v-else title="Belum ada kategori" icon="store" />
                    </Panel>
                </div>

                <div class="lg:col-span-4">
                    <Panel title="Area white space teratas" hint="Sel 1 km dengan banyak prospek dan tanpa outlet dalam 500 m." class="h-full">
                        <ul v-if="whitespace_top?.length" class="m-0 flex list-none flex-col p-0" data-testid="whitespace-list">
                            <li v-for="w in whitespace_top" :key="w.cell_id" class="border-b border-line/60 last:border-0">
                                <a :href="whitespaceHref(w)" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-focus" data-testid="whitespace-row">
                                    <span class="min-w-0">
                                        <span class="block truncate">{{ w.province ?? 'Tanpa provinsi' }}</span>
                                        <span class="num text-2xs text-muted">{{ w.cell_id }} · outlet terdekat {{ formatDistance(w.nearest_outlet_m) }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <Badge tone="hot" class="num">{{ formatNumber(w.prospects) }}</Badge>
                                        <Icon name="navigation" :size="14" class="text-muted" />
                                    </span>
                                </a>
                            </li>
                        </ul>
                        <EmptyState v-else title="Tidak ada white space" description="Semua area padat prospek sudah dekat dengan outlet." icon="pin" />
                    </Panel>
                </div>

                <div class="lg:col-span-4">
                    <Panel title="Aktivitas per akun" class="h-full">
                        <ul v-if="activity_by_user?.length" class="m-0 flex list-none flex-col gap-3 p-0" data-testid="activity-list">
                            <li v-for="u in activity_by_user" :key="u.user_id" class="grid gap-1" data-testid="activity-row">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="truncate">{{ u.name }}</span>
                                    <span class="num text-2xs text-muted">{{ u.contacted }} hubungi · {{ u.demo }} demo · {{ u.won }} deal</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-fg/10" aria-hidden="true">
                                    <div class="h-full rounded-full" :style="{ width: `${(u.contacted / maxActivity) * 100}%`, background: STATUS_VAR.contacted }" />
                                </div>
                            </li>
                        </ul>
                        <EmptyState v-else title="Belum ada akun" icon="user" />
                    </Panel>
                </div>

                <div class="lg:col-span-12">
                    <DigestPanel :digest="digest" />
                </div>
            </div>
        </div>
    </AppShell>
</template>
