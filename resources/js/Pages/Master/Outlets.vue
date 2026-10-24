<script setup lang="ts">
/* eslint-disable vue/prop-name-casing -- Inertia props mirror the snake_case JSON contract (CONTRACTS §1) */
import { reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import MasterTabs from '@/Components/master/MasterTabs.vue';
import OutletFormDrawer from '@/Components/master/OutletFormDrawer.vue';
import OutletImport from '@/Components/master/OutletImport.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import Pagination from '@/Components/master/Pagination.vue';
import type { ImportResult, OutletRow, PageMeta, ProvinceOption } from '@/Components/master/types';
import Badge, { type BadgeTone } from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { useDebounceFn } from '@/composables/useDebounce';
import { useToasts } from '@/composables/useToasts';
import { api, normalizeError } from '@/lib/api';
import { formatCoordinates } from '@/lib/format';
import type { GeocodeStatus } from '@/types';

const props = defineProps<{
    outlets: { data: OutletRow[]; meta: PageMeta };
    filters?: { search: string | null; province_id: number | null; geocode_status: string | null };
    review_queue_count?: number;
    provinces?: ProvinceOption[];
}>();

const { push } = useToasts();
const rows = ref<OutletRow[]>(props.outlets?.data ?? []);
const meta = ref<PageMeta>(props.outlets?.meta ?? { current_page: 1, last_page: 1, total: 0, per_page: 25 });
const reviewCount = ref(props.review_queue_count ?? 0);
const filter = reactive({
    search: props.filters?.search ?? '',
    province_id: (props.filters?.province_id ?? '') as number | '',
    geocode_status: (props.filters?.geocode_status ?? '') as GeocodeStatus | '',
});
const loading = ref(false);
const error = ref<string | null>(null);

const drawerOpen = ref(false);
const editing = ref<OutletRow | null>(null);
const confirmingId = ref<number | null>(null);
const busyId = ref<number | null>(null);

const STATUS: Record<GeocodeStatus, { label: string; tone: BadgeTone }> = {
    ok: { label: 'Terverifikasi', tone: 'success' },
    manual: { label: 'Manual', tone: 'info' },
    needs_review: { label: 'Perlu tinjau', tone: 'warm' },
    failed: { label: 'Gagal', tone: 'danger' },
};

async function load(page = 1): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const params: Record<string, unknown> = { page };
        if (filter.search.trim()) params.search = filter.search.trim();
        if (filter.province_id !== '') params.province_id = filter.province_id;
        if (filter.geocode_status !== '') params.geocode_status = filter.geocode_status;
        const { data } = await api.get<{ data: OutletRow[]; meta: PageMeta }>('/api/master/outlets', { params });
        rows.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = normalizeError(e).message;
    } finally {
        loading.value = false;
    }
}
const debouncedLoad = useDebounceFn(() => load(1), 300);
watch(() => filter.search, () => debouncedLoad());
watch(() => [filter.province_id, filter.geocode_status], () => load(1));

function openCreate(): void {
    editing.value = null;
    drawerOpen.value = true;
}
function openEdit(o: OutletRow): void {
    editing.value = o;
    drawerOpen.value = true;
}
async function onSaved(o: OutletRow, created: boolean): Promise<void> {
    push(created ? `Outlet ${o.code} ditambahkan.` : `Outlet ${o.code} disimpan.`, 'success', 3000);
    await load(created ? 1 : meta.value.current_page);
}

async function deactivate(o: OutletRow): Promise<void> {
    busyId.value = o.id;
    confirmingId.value = null;
    try {
        const { data } = await api.delete<OutletRow>(`/api/master/outlets/${o.id}`);
        rows.value = rows.value.map((r) => (r.id === o.id ? data : r));
        push(`Outlet ${o.code} dinonaktifkan.`, 'success', 3000);
    } catch (e) {
        push(normalizeError(e).message, 'error');
    } finally {
        busyId.value = null;
    }
}

async function retry(o: OutletRow): Promise<void> {
    busyId.value = o.id;
    try {
        const { data } = await api.post<{ queued: boolean; outlet: OutletRow }>(`/api/master/outlets/${o.id}/retry-geocode`, {});
        rows.value = rows.value.map((r) => (r.id === o.id ? data.outlet : r));
        push(`Geocode ulang ${o.code} diantrekan.`, 'success', 3000);
    } catch (e) {
        push(normalizeError(e).message, 'error');
    } finally {
        busyId.value = null;
    }
}

async function onImported(r: ImportResult): Promise<void> {
    reviewCount.value = Math.max(reviewCount.value, r.needs_review);
    await load(1);
}
</script>

<template>
    <Head title="Outlet" />
    <AppShell>
        <div class="mx-auto w-full max-w-7xl">
            <PageHeader eyebrow="Master data" title="Outlet" description="Daftar outlet yang menjadi pusat pencarian prospek. Menghapus outlet hanya menonaktifkannya.">
                <Button variant="primary" size="sm" data-testid="outlet-add" @click="openCreate">
                    <Icon name="store" :size="14" />
                    Tambah outlet
                </Button>
            </PageHeader>
            <MasterTabs current="outlets" :review-count="reviewCount" />

            <div class="grid gap-4">
                <OutletImport @imported="onImported" />

                <Link
                    v-if="reviewCount > 0"
                    href="/master/outlets/review"
                    class="glass flex items-center justify-between gap-3 rounded-panel px-4 py-3 text-sm transition hover:bg-fg/5"
                    data-testid="review-banner"
                >
                    <span class="flex items-center gap-2"><Icon name="alert" :size="16" class="text-warm" /> {{ reviewCount }} outlet menunggu verifikasi lokasi manual</span>
                    <span class="text-focus">Tinjau →</span>
                </Link>

                <GlassPanel :padded="false" class="overflow-hidden">
                    <form class="flex flex-wrap items-end gap-3 border-b border-line p-4" role="search" @submit.prevent="load(1)">
                        <div class="flex min-w-[12rem] flex-1 flex-col gap-1">
                            <label for="outlet-search" class="text-xs font-medium text-muted">Cari</label>
                            <input id="outlet-search" v-model="filter.search" type="search" placeholder="Nama, kode, atau kota" data-testid="outlet-search" class="h-10 rounded-chip border border-line bg-surface/60 px-3 text-base text-fg placeholder:text-muted/70 sm:text-sm" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="outlet-filter-province" class="text-xs font-medium text-muted">Provinsi</label>
                            <select id="outlet-filter-province" v-model.number="filter.province_id" data-testid="outlet-filter-province" class="h-10 rounded-chip border border-line bg-surface/60 px-3 text-base text-fg sm:text-sm">
                                <option value="">Semua provinsi</option>
                                <option v-for="p in provinces ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="outlet-filter-geo" class="text-xs font-medium text-muted">Status geocode</label>
                            <select id="outlet-filter-geo" v-model="filter.geocode_status" data-testid="outlet-filter-geocode" class="h-10 rounded-chip border border-line bg-surface/60 px-3 text-base text-fg sm:text-sm">
                                <option value="">Semua status</option>
                                <option v-for="(s, k) in STATUS" :key="k" :value="k">{{ s.label }}</option>
                            </select>
                        </div>
                    </form>

                    <div class="p-4" data-testid="outlet-body">
                        <Skeleton v-if="loading && rows.length === 0" :lines="6" />
                        <ErrorState v-else-if="error" :message="error" :retrying="loading" @retry="load(meta.current_page)" />
                        <EmptyState v-else-if="rows.length === 0" title="Tidak ada outlet" description="Ubah filter pencarian, tambahkan outlet, atau impor dari CSV." icon="store">
                            <Button size="sm" variant="primary" @click="openCreate">Tambah outlet</Button>
                        </EmptyState>
                        <div v-else class="overflow-x-auto" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                            <table class="w-full min-w-[44rem] border-collapse text-left text-sm" data-testid="outlet-table">
                                <caption class="sr-only">Daftar outlet</caption>
                                <thead>
                                    <tr class="hud-label border-b border-line">
                                        <th scope="col" class="py-2 pr-3 font-normal">Kode</th>
                                        <th scope="col" class="py-2 pr-3 font-normal">Nama</th>
                                        <th scope="col" class="py-2 pr-3 font-normal">Kota · Provinsi</th>
                                        <th scope="col" class="py-2 pr-3 font-normal">Koordinat</th>
                                        <th scope="col" class="py-2 pr-3 font-normal">Geocode</th>
                                        <th scope="col" class="py-2 text-right font-normal">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="o in rows" :key="o.id" class="border-b border-line/60 align-top" :class="o.is_active ? '' : 'opacity-60'" :data-testid="`outlet-row-${o.code}`">
                                        <td class="num py-2.5 pr-3">{{ o.code }}</td>
                                        <td class="py-2.5 pr-3">
                                            <span class="font-medium">{{ o.name }}</span>
                                            <Badge v-if="!o.is_active" class="ml-2">Nonaktif</Badge>
                                            <Badge v-if="o.is_simulated" tone="simulasi" class="ml-2">Simulasi</Badge>
                                        </td>
                                        <td class="py-2.5 pr-3 text-muted">{{ o.city }} · {{ o.province ?? '–' }}</td>
                                        <td class="num py-2.5 pr-3 text-xs">{{ o.lat !== null && o.lng !== null ? formatCoordinates(o.lat, o.lng) : '–' }}</td>
                                        <td class="py-2.5 pr-3"><Badge :tone="STATUS[o.geocode_status]?.tone ?? 'neutral'">{{ STATUS[o.geocode_status]?.label ?? o.geocode_status }}</Badge></td>
                                        <td class="py-2.5 text-right">
                                            <div class="flex flex-wrap justify-end gap-1">
                                                <Button v-if="o.geocode_status === 'failed'" size="sm" :loading="busyId === o.id" :data-testid="`outlet-retry-${o.code}`" @click="retry(o)">Coba geocode ulang</Button>
                                                <Button size="sm" variant="ghost" :aria-label="`Ubah ${o.name}`" :data-testid="`outlet-edit-${o.code}`" @click="openEdit(o)">Ubah</Button>
                                                <template v-if="o.is_active">
                                                    <Button v-if="confirmingId !== o.id" size="sm" variant="ghost" :aria-label="`Nonaktifkan ${o.name}`" :data-testid="`outlet-delete-${o.code}`" @click="confirmingId = o.id">Nonaktifkan</Button>
                                                    <template v-else>
                                                        <Button size="sm" variant="danger" :loading="busyId === o.id" :data-testid="`outlet-delete-confirm-${o.code}`" @click="deactivate(o)">Yakin?</Button>
                                                        <Button size="sm" variant="ghost" @click="confirmingId = null">Batal</Button>
                                                    </template>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="rows.length > 0 || meta.total > 0" class="border-t border-line p-4">
                        <Pagination :meta="meta" :busy="loading" @page="load" />
                    </div>
                </GlassPanel>
            </div>
        </div>

        <OutletFormDrawer v-model="drawerOpen" :outlet="editing" :provinces="provinces ?? []" @saved="onSaved" />
    </AppShell>
</template>
