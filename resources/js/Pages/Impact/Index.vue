<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import BarList from '@/Components/charts/BarList.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import Panel from '@/Components/master/Panel.vue';
import TextField from '@/Components/master/TextField.vue';
import type { Assumptions, ImpactResults } from '@/Components/master/types';
import Button from '@/Components/ui/Button.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useDebounceFn } from '@/composables/useDebounce';
import { api, normalizeError } from '@/lib/api';
import { formatNumber } from '@/lib/format';

const props = defineProps<{ assumptions: Assumptions; defaults: Assumptions; results: ImpactResults }>();

interface FieldDef {
    key: string;
    label: string;
    hint: string;
    prefix?: string;
    suffix?: string;
    step: number;
}
interface Group { id: string; title: string; hint: string; fields: FieldDef[] }

const GROUPS: Group[] = [
    {
        id: 'google',
        title: 'Panggilan Google Places',
        hint: 'Seberapa sering dan seberapa banyak Google Places dipanggil.',
        fields: [
            { key: 'outlets', label: 'Jumlah outlet', hint: 'Outlet aktif yang disinkronkan.', suffix: 'outlet', step: 1 },
            { key: 'google_syncs_per_month', label: 'Sinkronisasi per bulan', hint: '30 = sinkronisasi harian.', suffix: 'kali', step: 1 },
            { key: 'google_groups_per_outlet', label: 'Kelompok kategori per outlet', hint: 'Satu panggilan pencarian per kelompok jenis tempat.', suffix: 'panggilan', step: 1 },
            { key: 'avg_pages', label: 'Rata-rata halaman per panggilan', hint: 'searchNearby (New) tanpa paginasi: 1.', suffix: 'halaman', step: 0.1 },
            { key: 'details_ratio', label: 'Rasio permintaan Details', hint: '0 = tanpa Details; 1 = satu Details per hasil pencarian.', step: 0.05 },
        ],
    },
    {
        id: 'cost',
        title: 'Tarif & kurs',
        hint: 'Tarif adalah asumsi yang bisa Anda ubah, bukan harga resmi. Periksa tarif Google terbaru.',
        fields: [
            { key: 'cost_per_1000_search', label: 'Tarif pencarian per 1.000 panggilan', hint: 'Dalam USD.', prefix: 'USD', step: 0.5 },
            { key: 'cost_per_1000_details', label: 'Tarif Details per 1.000 panggilan', hint: 'Dalam USD.', prefix: 'USD', step: 0.5 },
            { key: 'usd_idr', label: 'Kurs USD → IDR', hint: 'Rupiah per 1 USD.', prefix: 'Rp', step: 100 },
        ],
    },
    {
        id: 'funnel',
        title: 'Prospek & konversi',
        hint: 'Seberapa banyak prospek menjadi deal.',
        fields: [
            { key: 'prospects_per_outlet', label: 'Prospek per outlet', hint: 'Rata-rata prospek di sekitar satu outlet.', suffix: 'prospek', step: 1 },
            { key: 'conv_found_to_contact', label: 'Konversi ditemukan → dihubungi', hint: 'Rasio 0–1 (0,2 = 20%).', step: 0.01 },
            { key: 'conv_contact_to_demo', label: 'Konversi dihubungi → demo', hint: 'Rasio 0–1.', step: 0.01 },
            { key: 'conv_demo_to_won', label: 'Konversi demo → menang', hint: 'Rasio 0–1.', step: 0.01 },
            { key: 'deal_value_idr', label: 'Nilai rata-rata satu deal', hint: 'Pendapatan per deal.', prefix: 'Rp', step: 100000 },
        ],
    },
    {
        id: 'time',
        title: 'Penghematan waktu',
        hint: 'Waktu tim sales yang tidak lagi dipakai untuk riset manual.',
        fields: [
            { key: 'hours_saved_per_week', label: 'Jam hemat per minggu', hint: 'Dibanding mencari prospek manual.', suffix: 'jam', step: 0.5 },
            { key: 'hourly_cost_idr', label: 'Biaya per jam kerja', hint: 'Untuk menilai nilai jam yang dihemat.', prefix: 'Rp', step: 5000 },
        ],
    },
];
const ALL_KEYS = GROUPS.flatMap((g) => g.fields.map((f) => f.key));

const form = reactive<Record<string, number | null>>({});
for (const k of ALL_KEYS) form[k] = props.assumptions?.[k] ?? props.defaults?.[k] ?? 0;
// Assumptions the backend knows about that this page has no dedicated field for are still kept and sent.
const extraKeys = Object.keys(props.assumptions ?? {}).filter((k) => !ALL_KEYS.includes(k));
for (const k of extraKeys) form[k] = props.assumptions[k];

const results = ref<ImpactResults>(props.results);
const saving = ref(false);
const savedAt = ref<number | null>(null);
const saveError = ref<string | null>(null);
const errors = ref<Record<string, string[]>>({});
let pending = 0;
let applying = false;

function payload(): Assumptions {
    const out: Assumptions = {};
    for (const [k, v] of Object.entries(form)) if (typeof v === 'number' && Number.isFinite(v)) out[k] = v;
    return out;
}

async function save(): Promise<void> {
    const seq = ++pending;
    saving.value = true;
    saveError.value = null;
    try {
        const { data } = await api.put<{ assumptions: Assumptions; results: ImpactResults }>('/api/settings/impact', payload());
        if (seq !== pending) return;
        results.value = data.results;
        errors.value = {};
        savedAt.value = Date.now();
    } catch (e) {
        if (seq !== pending) return;
        const err = normalizeError(e);
        errors.value = err.errors;
        saveError.value = err.status === 422 ? 'Ada asumsi di luar rentang yang diizinkan.' : err.message;
    } finally {
        if (seq === pending) saving.value = false;
    }
}
const debouncedSave = useDebounceFn(save, 450);

watch(form, () => {
    if (applying) return;
    saving.value = true;
    debouncedSave();
});

async function reset(): Promise<void> {
    applying = true;
    debouncedSave.cancel();
    for (const [k, v] of Object.entries(props.defaults ?? {})) form[k] = v;
    applying = false;
    await save();
}
onBeforeUnmount(() => debouncedSave.cancel());

const isDefault = (k: string) => props.defaults?.[k] === undefined || form[k] === props.defaults[k];
const changedCount = computed(() => Object.keys(form).filter((k) => !isDefault(k)).length);
const hintFor = (f: FieldDef) => (isDefault(f.key) ? f.hint : `${f.hint} Diubah dari default ${formatNumber(props.defaults?.[f.key], 2)}.`);
const fieldError = (k: string) => errors.value[k]?.[0] ?? null;

const r = computed(() => results.value);
const idr = (n: number | undefined) => `Rp ${formatNumber(n ?? 0)}`;
const costBars = computed(() => [
    { label: 'Google Places (per bulan)', value: r.value?.google_cost_idr ?? 0, color: 'var(--c-warm)' },
    { label: 'OpenStreetMap (per bulan)', value: r.value?.osm_cost_idr ?? 0, color: 'var(--c-medium)' },
]);
const roi = computed(() => (r.value?.google_cost_idr ?? 0) > 0 ? (r.value.expected_revenue_idr ?? 0) / r.value.google_cost_idr : null);
const status = computed(() => (saveError.value ? 'error' : saving.value ? 'saving' : savedAt.value ? 'saved' : 'idle'));
</script>

<template>
    <Head title="Estimasi Dampak & Biaya" />
    <AppShell>
        <div class="mx-auto w-full max-w-7xl">
            <PageHeader
                eyebrow="Estimasi · bukan angka pasti"
                title="Dampak & biaya"
                description="Semua angka di bawah dihitung dari asumsi yang bisa Anda ubah. Ini estimasi untuk perencanaan, bukan tagihan atau jaminan hasil."
            >
                <span class="text-xs text-muted" role="status" aria-live="polite" data-testid="impact-status">
                    <template v-if="status === 'saving'">Menyimpan & menghitung…</template>
                    <template v-else-if="status === 'saved'">Tersimpan · hasil diperbarui</template>
                    <template v-else-if="status === 'error'"><span class="text-danger">{{ saveError }}</span></template>
                    <template v-else>Perubahan tersimpan otomatis</template>
                </span>
                <Button size="sm" data-testid="impact-reset" :disabled="changedCount === 0 && status !== 'error'" @click="reset">
                    <Icon name="refresh" :size="14" />
                    Kembalikan default
                </Button>
            </PageHeader>

            <div class="grid gap-4 lg:grid-cols-12">
                <div class="grid content-start gap-4 lg:col-span-7" data-testid="impact-assumptions">
                    <Panel v-for="g in GROUPS" :key="g.id" :title="g.title" :hint="g.hint">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="f in g.fields" :key="f.key">
                                <TextField
                                    v-model="form[f.key]"
                                    numeric
                                    type="number"
                                    inputmode="decimal"
                                    :label="f.label"
                                    :hint="hintFor(f)"
                                    :prefix="f.prefix"
                                    :suffix="f.suffix"
                                    :error="fieldError(f.key)"
                                    :step="f.step"
                                    :data-testid="`impact-${f.key}`"
                                />
                            </div>
                        </div>
                    </Panel>
                </div>

                <div class="lg:col-span-5">
                    <div class="grid content-start gap-4 lg:sticky lg:top-20">
                        <Panel title="Hasil estimasi" hint="Dihitung ulang di server setiap asumsi berubah.">
                            <dl class="m-0 grid grid-cols-2 gap-x-4 gap-y-4" data-testid="impact-results">
                                <div>
                                    <dt class="hud-label">Panggilan Google / bulan</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-google-calls">{{ formatNumber(r?.google_calls_per_month) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Biaya Google / bulan</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-google-cost">{{ idr(r?.google_cost_idr) }}</dd>
                                    <dd class="num m-0 text-2xs text-muted">≈ USD {{ formatNumber(r?.google_cost_usd, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Biaya OpenStreetMap</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold text-medium" data-testid="result-osm-cost">{{ idr(r?.osm_cost_idr) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Prospek ditemukan</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-prospects">{{ formatNumber(r?.prospects_found) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Perkiraan deal</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-deals">{{ formatNumber(r?.expected_deals, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Perkiraan pendapatan</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-revenue">{{ idr(r?.expected_revenue_idr) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">Jam hemat / bulan</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-hours">{{ formatNumber(r?.hours_saved_per_month, 1) }}</dd>
                                </div>
                                <div v-if="r?.hours_saved_value_idr !== undefined">
                                    <dt class="hud-label">Nilai jam yang dihemat</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-hours-value">{{ idr(r.hours_saved_value_idr) }}</dd>
                                </div>
                            </dl>
                        </Panel>

                        <Panel title="Biaya sumber data per bulan" hint="OpenStreetMap gratis; Google Places berbayar menurut pemakaian.">
                            <BarList :items="costBars" label="Perbandingan biaya per bulan (Rupiah)" :format="idr" />
                            <p v-if="roi !== null" class="text-xs text-muted">
                                Perkiraan pendapatan sekitar <span class="num text-fg">{{ formatNumber(roi, 1) }}×</span> biaya Google bulanan, bila semua asumsi tercapai.
                            </p>
                        </Panel>

                        <GlassPanel class="flex gap-3 text-xs text-muted" role="note">
                            <Icon name="info" :size="16" class="mt-0.5 shrink-0" />
                            <p>
                                Hasil adalah <strong class="text-fg">estimasi</strong>. Tarif Google dan kurs berubah; konversi sebenarnya bergantung pada tim dan pasar. Gunakan untuk membandingkan skenario, bukan sebagai komitmen.
                            </p>
                        </GlassPanel>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>
