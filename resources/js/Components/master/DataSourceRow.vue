<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToasts } from '@/composables/useToasts';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatDateTime, formatNumber, relativeTime } from '@/lib/format';
import Switch from './Switch.vue';
import TextField from './TextField.vue';
import type { DataSource } from './types';

const props = defineProps<{ source: DataSource }>();
const emit = defineEmits<{ updated: [source: DataSource] }>();

const { push } = useToasts();
const { t } = useI18n();

const form = reactive({
    api_key: '',
    priority: props.source.priority as number | null,
    monthly_quota: props.source.monthly_quota as number | null,
    cost_search_per_1000: props.source.cost_search_per_1000 as number | null,
    cost_details_per_1000: props.source.cost_details_per_1000 as number | null,
});
watch(
    () => props.source,
    (s) => {
        form.priority = s.priority;
        form.monthly_quota = s.monthly_quota;
        form.cost_search_per_1000 = s.cost_search_per_1000;
        form.cost_details_per_1000 = s.cost_details_per_1000;
    },
);

const toggling = ref(false);
const saving = ref(false);
const testing = ref(false);
const confirmClear = ref(false);
const errors = ref<Record<string, string[]>>({});
const rowError = ref<string | null>(null);
const test = ref<{ ok: boolean; message: string; latency_ms: number | null } | null>(null);

const needsKey = computed(() => props.source.key === 'google');
const dirty = computed(
    () =>
        form.api_key.trim() !== '' ||
        form.priority !== props.source.priority ||
        form.monthly_quota !== props.source.monthly_quota ||
        form.cost_search_per_1000 !== props.source.cost_search_per_1000 ||
        form.cost_details_per_1000 !== props.source.cost_details_per_1000,
);
const quotaPct = computed(() => {
    const q = props.source.monthly_quota;
    if (!q || q <= 0) return null;
    return Math.min(100, ((props.source.calls_this_month ?? 0) / q) * 100);
});
const costUsd = computed(() => {
    const per = props.source.cost_search_per_1000 ?? 0;
    return ((props.source.calls_this_month ?? 0) / 1000) * per;
});
const configEntries = computed(() => Object.entries(props.source.config ?? {}));
const statusTone = computed(() => {
    const s = props.source.last_status;
    if (!s) return 'neutral' as const;
    return s === 'failed' || s === 'error' ? ('danger' as const) : s === 'success' || s === 'ok' ? ('success' as const) : ('info' as const);
});

async function put(body: Record<string, unknown>): Promise<boolean> {
    errors.value = {};
    rowError.value = null;
    try {
        const { data } = await api.put<DataSource>(`/api/master/data-sources/${props.source.id}`, body);
        emit('updated', data);
        return true;
    } catch (e) {
        const err = normalizeError(e);
        errors.value = err.errors;
        rowError.value = err.errors.api_key?.[0] ?? err.errors.is_active?.[0] ?? err.message;
        return false;
    }
}

async function toggle(value: boolean): Promise<void> {
    toggling.value = true;
    const ok = await put({ is_active: value });
    toggling.value = false;
    if (!ok) push(rowError.value ?? t('master.data_source.toggle_failed'), 'error');
    else push(t(value ? 'master.data_source.enabled' : 'master.data_source.disabled', { name: props.source.name }), 'success', 3000);
}

async function save(): Promise<void> {
    const body: Record<string, unknown> = {};
    if (form.api_key.trim() !== '') body.api_key = form.api_key.trim();
    if (form.priority !== props.source.priority && form.priority !== null) body.priority = form.priority;
    if (form.monthly_quota !== props.source.monthly_quota) body.monthly_quota = form.monthly_quota;
    if (form.cost_search_per_1000 !== props.source.cost_search_per_1000) body.cost_search_per_1000 = form.cost_search_per_1000;
    if (form.cost_details_per_1000 !== props.source.cost_details_per_1000) body.cost_details_per_1000 = form.cost_details_per_1000;
    saving.value = true;
    const ok = await put(body);
    saving.value = false;
    // The typed secret never lives on after the request, success or not.
    form.api_key = '';
    if (ok) push(t('master.data_source.saved', { name: props.source.name }), 'success', 3000);
}

async function clearKey(): Promise<void> {
    confirmClear.value = false;
    saving.value = true;
    const ok = await put({ clear_key: true });
    saving.value = false;
    form.api_key = '';
    if (ok) push(t('master.data_source.key_cleared'), 'success', 3000);
}

async function runTest(): Promise<void> {
    testing.value = true;
    test.value = null;
    try {
        const { data } = await api.post<{ ok: boolean; message: string; latency_ms: number | null }>(`/api/master/data-sources/${props.source.id}/test`, {});
        test.value = data;
    } catch (e) {
        test.value = { ok: false, message: normalizeError(e).message, latency_ms: null };
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <GlassPanel as="article" :data-testid="`ds-row-${source.key}`" :aria-label="source.name" class="flex flex-col gap-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-display text-lg font-semibold leading-tight">{{ source.name }}</h2>
                <p class="num text-2xs text-muted">{{ t('master.data_source.key_priority', { key: source.key, n: source.priority }) }}</p>
            </div>
            <div class="flex items-center gap-3">
                <Badge :tone="source.is_active ? 'success' : 'neutral'" dot :data-testid="`ds-state-${source.key}`">{{ source.is_active ? t('master.active') : t('master.inactive') }}</Badge>
                <Switch :model-value="source.is_active" :label="t(source.is_active ? 'master.data_source.deactivate' : 'master.data_source.activate', { name: source.name })" :busy="toggling" :data-testid="`ds-toggle-${source.key}`" @update:model-value="toggle" />
            </div>
        </header>

        <p v-if="rowError" class="rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert" :data-testid="`ds-error-${source.key}`">{{ rowError }}</p>

        <dl class="m-0 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="hud-label">{{ t('master.data_source.calls_month') }}</dt>
                <dd class="num m-0 mt-0.5">
                    {{ formatNumber(source.calls_this_month ?? 0) }}<span v-if="source.monthly_quota" class="text-muted"> / {{ formatNumber(source.monthly_quota) }}</span>
                </dd>
                <dd v-if="quotaPct !== null" class="m-0 mt-1">
                    <div class="h-1.5 overflow-hidden rounded-full bg-fg/10" role="progressbar" :aria-valuenow="Math.round(quotaPct)" aria-valuemin="0" aria-valuemax="100" :aria-label="t('master.data_source.quota_aria')">
                        <span class="block h-full rounded-full" :class="quotaPct >= 90 ? 'bg-danger' : quotaPct >= 70 ? 'bg-warm' : 'bg-medium'" :style="{ width: `${quotaPct}%` }" />
                    </div>
                </dd>
            </div>
            <div>
                <dt class="hud-label">{{ t('master.data_source.est_cost') }}</dt>
                <dd class="num m-0 mt-0.5" :data-testid="`ds-cost-${source.key}`">{{ (source.cost_search_per_1000 ?? 0) > 0 ? `USD ${formatNumber(costUsd, 2)}` : t('master.data_source.free') }}</dd>
            </div>
            <div>
                <dt class="hud-label">{{ t('master.data_source.last_run') }}</dt>
                <dd class="m-0 mt-0.5 flex flex-wrap items-center gap-2">
                    <span :title="formatDateTime(source.last_run_at)">{{ source.last_run_at ? relativeTime(source.last_run_at) : t('labels.run.never') }}</span>
                    <Badge v-if="source.last_status" :tone="statusTone">{{ source.last_status }}</Badge>
                </dd>
            </div>
            <div>
                <dt class="hud-label">{{ t('master.data_source.api_key') }}</dt>
                <dd class="m-0 mt-0.5" :data-testid="`ds-keystate-${source.key}`">
                    <template v-if="source.has_key"><span class="num">{{ source.key_hint ?? '••••' }}</span> <span class="text-xs text-muted">{{ t('master.data_source.stored') }}</span></template>
                    <span v-else class="text-muted">{{ needsKey ? t('master.data_source.not_set') : t('master.data_source.not_needed') }}</span>
                </dd>
            </div>
        </dl>

        <p v-if="source.last_error" class="num rounded-chip border border-line bg-raised/50 px-3 py-2 text-xs text-danger" :data-testid="`ds-last-error-${source.key}`">{{ t('master.data_source.last_error', { message: source.last_error }) }}</p>

        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" autocomplete="off" @submit.prevent="save">
            <TextField
                v-model="form.api_key"
                type="password"
                :label="t('master.data_source.api_key')"
                autocomplete="new-password"
                spellcheck="false"
                :placeholder="source.has_key ? t('master.data_source.key_replace', { hint: source.key_hint ?? '••••' }) : needsKey ? t('master.data_source.key_paste') : t('master.data_source.key_optional')"
                :error="errors.api_key?.[0]"
                :hint="source.has_key ? t('master.data_source.key_old_hidden') : undefined"
                :data-testid="`ds-key-input-${source.key}`"
            />
            <TextField v-model="form.priority" numeric type="number" :label="t('master.data_source.priority')" :hint="t('master.data_source.priority_hint')" :error="errors.priority?.[0]" />
            <TextField v-model="form.monthly_quota" numeric type="number" :label="t('master.data_source.quota')" :suffix="t('master.unit.calls')" :error="errors.monthly_quota?.[0]" />
            <TextField v-model="form.cost_search_per_1000" numeric type="number" step="0.5" :label="t('master.data_source.cost_search')" prefix="USD" :error="errors.cost_search_per_1000?.[0]" />
            <TextField v-if="needsKey" v-model="form.cost_details_per_1000" numeric type="number" step="0.5" :label="t('master.data_source.cost_details')" prefix="USD" :error="errors.cost_details_per_1000?.[0]" />
            <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                <Button type="submit" variant="primary" :loading="saving" :disabled="!dirty" :data-testid="`ds-save-${source.key}`">{{ t('common.save') }}</Button>
                <Button :loading="testing" :data-testid="`ds-test-${source.key}`" @click="runTest">
                    <Icon name="refresh" :size="14" />
                    {{ t('master.data_source.test') }}
                </Button>
                <template v-if="source.has_key">
                    <Button v-if="!confirmClear" variant="ghost" :data-testid="`ds-clear-${source.key}`" @click="confirmClear = true">{{ t('master.data_source.clear_key') }}</Button>
                    <span v-else class="flex items-center gap-2 text-sm">
                        {{ t('master.data_source.clear_question') }}
                        <Button variant="danger" size="sm" :data-testid="`ds-clear-confirm-${source.key}`" @click="clearKey">{{ t('master.data_source.clear_yes') }}</Button>
                        <Button variant="ghost" size="sm" @click="confirmClear = false">{{ t('common.cancel') }}</Button>
                    </span>
                </template>
            </div>
        </form>

        <div v-if="testing || test" class="rounded-chip border px-3 py-2 text-sm" :class="test && !test.ok ? 'border-danger/40 bg-danger/10' : 'border-line bg-raised/50'" role="status" :data-testid="`ds-test-result-${source.key}`">
            <template v-if="testing">{{ t('master.data_source.testing') }}</template>
            <template v-else-if="test">
                <span :class="test.ok ? 'text-st-won' : 'text-danger'" class="font-medium">{{ test.ok ? t('master.data_source.test_ok') : t('master.data_source.test_fail') }}</span>
                · {{ test.message }}
                <span v-if="test.latency_ms !== null" class="num text-muted"> · {{ formatNumber(test.latency_ms) }} ms</span>
            </template>
        </div>

        <details v-if="configEntries.length" class="text-sm">
            <summary class="cursor-pointer text-xs text-muted">{{ t('master.data_source.config', { n: configEntries.length }) }}</summary>
            <dl class="m-0 mt-2 grid gap-1 text-xs">
                <div v-for="[k, v] in configEntries" :key="k" class="flex gap-2">
                    <dt class="num text-muted">{{ k }}</dt>
                    <dd class="num m-0 break-all">{{ typeof v === 'object' ? JSON.stringify(v) : String(v) }}</dd>
                </div>
            </dl>
        </details>
    </GlassPanel>
</template>
