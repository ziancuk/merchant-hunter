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
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatNumber, formatRupiah } from '@/lib/format';

const props = defineProps<{ assumptions: Assumptions; defaults: Assumptions; results: ImpactResults }>();
const { t } = useI18n();

interface FieldDef {
    key: string;
    prefix?: string;
    /** Key under impact.unit.* */
    unit?: string;
    step: number;
}
interface Group { id: string; fields: FieldDef[] }

const GROUPS: Group[] = [
    {
        id: 'google',
        fields: [
            { key: 'outlets', unit: 'outlet', step: 1 },
            { key: 'google_syncs_per_month', unit: 'times', step: 1 },
            { key: 'google_groups_per_outlet', unit: 'calls', step: 1 },
            { key: 'avg_pages', unit: 'pages', step: 0.1 },
            { key: 'details_ratio', step: 0.05 },
        ],
    },
    {
        id: 'cost',
        fields: [
            { key: 'cost_per_1000_search', prefix: 'USD', step: 0.5 },
            { key: 'cost_per_1000_details', prefix: 'USD', step: 0.5 },
            { key: 'usd_idr', prefix: 'Rp', step: 100 },
        ],
    },
    {
        id: 'funnel',
        fields: [
            { key: 'prospects_per_outlet', unit: 'prospects', step: 1 },
            { key: 'conv_found_to_contact', step: 0.01 },
            { key: 'conv_contact_to_demo', step: 0.01 },
            { key: 'conv_demo_to_won', step: 0.01 },
            { key: 'deal_value_idr', prefix: 'Rp', step: 100000 },
        ],
    },
    {
        id: 'time',
        fields: [
            { key: 'hours_saved_per_week', unit: 'hours', step: 0.5 },
            { key: 'hourly_cost_idr', prefix: 'Rp', step: 5000 },
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
        saveError.value = err.status === 422 ? t('impact.out_of_range') : err.message;
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
const hintFor = (f: FieldDef) => {
    const hint = t(`impact.field.${f.key}.hint`);
    return isDefault(f.key) ? hint : t('impact.changed_from_default', { hint, value: formatNumber(props.defaults?.[f.key], 2) });
};
const fieldError = (k: string) => errors.value[k]?.[0] ?? null;

const r = computed(() => results.value);
const costBars = computed(() => [
    { label: t('impact.costs.google'), value: r.value?.google_cost_idr ?? 0, color: 'var(--c-warm)' },
    { label: t('impact.costs.osm'), value: r.value?.osm_cost_idr ?? 0, color: 'var(--c-medium)' },
]);
const roi = computed(() => (r.value?.google_cost_idr ?? 0) > 0 ? (r.value.expected_revenue_idr ?? 0) / r.value.google_cost_idr : null);
const status = computed(() => (saveError.value ? 'error' : saving.value ? 'saving' : savedAt.value ? 'saved' : 'idle'));
</script>

<template>
    <Head :title="t('impact.head')" />
    <AppShell>
        <div class="mx-auto w-full max-w-7xl">
            <PageHeader
                :eyebrow="t('impact.eyebrow')"
                :title="t('impact.title')"
                :description="t('impact.description')"
            >
                <span class="text-xs text-muted" role="status" aria-live="polite" data-testid="impact-status">
                    <template v-if="status === 'saving'">{{ t('impact.status.saving') }}</template>
                    <template v-else-if="status === 'saved'">{{ t('impact.status.saved') }}</template>
                    <template v-else-if="status === 'error'"><span class="text-danger">{{ saveError }}</span></template>
                    <template v-else>{{ t('impact.status.idle') }}</template>
                </span>
                <Button size="sm" data-testid="impact-reset" :disabled="changedCount === 0 && status !== 'error'" @click="reset">
                    <Icon name="refresh" :size="14" />
                    {{ t('impact.reset') }}
                </Button>
            </PageHeader>

            <div class="grid gap-4 lg:grid-cols-12">
                <div class="grid content-start gap-4 lg:col-span-7" data-testid="impact-assumptions">
                    <Panel v-for="g in GROUPS" :key="g.id" :title="t(`impact.group.${g.id}.title`)" :hint="t(`impact.group.${g.id}.hint`)">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="f in g.fields" :key="f.key">
                                <TextField
                                    v-model="form[f.key]"
                                    numeric
                                    type="number"
                                    inputmode="decimal"
                                    :label="t(`impact.field.${f.key}.label`)"
                                    :hint="hintFor(f)"
                                    :prefix="f.prefix"
                                    :suffix="f.unit ? t(`impact.unit.${f.unit}`) : undefined"
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
                        <Panel :title="t('impact.results.title')" :hint="t('impact.results.hint')">
                            <dl class="m-0 grid grid-cols-2 gap-x-4 gap-y-4" data-testid="impact-results">
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.google_calls') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-google-calls">{{ formatNumber(r?.google_calls_per_month) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.google_cost') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-google-cost">{{ formatRupiah(r?.google_cost_idr) }}</dd>
                                    <dd class="num m-0 text-2xs text-muted">{{ t('impact.results.approx_usd', { value: formatNumber(r?.google_cost_usd, 2) }) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.osm_cost') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold text-medium" data-testid="result-osm-cost">{{ formatRupiah(r?.osm_cost_idr) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.prospects') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-prospects">{{ formatNumber(r?.prospects_found) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.deals') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-deals">{{ formatNumber(r?.expected_deals, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.revenue') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-revenue">{{ formatRupiah(r?.expected_revenue_idr) }}</dd>
                                </div>
                                <div>
                                    <dt class="hud-label">{{ t('impact.results.hours') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-hours">{{ formatNumber(r?.hours_saved_per_month, 1) }}</dd>
                                </div>
                                <div v-if="r?.hours_saved_value_idr !== undefined">
                                    <dt class="hud-label">{{ t('impact.results.hours_value') }}</dt>
                                    <dd class="num m-0 mt-0.5 text-xl font-semibold" data-testid="result-hours-value">{{ formatRupiah(r.hours_saved_value_idr) }}</dd>
                                </div>
                            </dl>
                        </Panel>

                        <Panel :title="t('impact.costs.title')" :hint="t('impact.costs.hint')">
                            <BarList :items="costBars" :label="t('impact.costs.aria')" :format="formatRupiah" />
                            <p v-if="roi !== null" class="text-xs text-muted">
                                {{ t('impact.costs.roi_before') }} <span class="num text-fg">{{ formatNumber(roi, 1) }}×</span> {{ t('impact.costs.roi_after') }}
                            </p>
                        </Panel>

                        <GlassPanel class="flex gap-3 text-xs text-muted" role="note">
                            <Icon name="info" :size="16" class="mt-0.5 shrink-0" />
                            <p>
                                {{ t('impact.note_before') }} <strong class="text-fg">{{ t('impact.note_strong') }}</strong>{{ t('impact.note_after') }}
                            </p>
                        </GlassPanel>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>
