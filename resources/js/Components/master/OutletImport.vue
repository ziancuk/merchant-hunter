<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import Panel from './Panel.vue';
import type { ImportResult } from './types';

const emit = defineEmits<{ imported: [result: ImportResult] }>();
const { t } = useI18n();

const MAX_BYTES = 2 * 1024 * 1024;
const SAMPLE = 'code,name,address,city,province,lat,lng\nOUT-901,Toko Contoh Makmur,Jl. Asia Afrika No. 1,Bandung,Jawa Barat,-6.9215,107.6071\nOUT-902,Toko Tanpa Koordinat,Jl. Malioboro No. 5,Yogyakarta,DI Yogyakarta,,\n';
const sampleHref = `data:text/csv;charset=utf-8,${encodeURIComponent(SAMPLE)}`;

const input = ref<HTMLInputElement | null>(null);
const file = ref<File | null>(null);
const uploading = ref(false);
const error = ref<string | null>(null);
const result = ref<ImportResult | null>(null);

function onPick(e: Event): void {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;
    result.value = null;
    error.value = null;
    if (f && f.size > MAX_BYTES) {
        error.value = t('master.import.too_big');
        file.value = null;
        return;
    }
    file.value = f;
}

async function upload(): Promise<void> {
    if (!file.value) return;
    uploading.value = true;
    error.value = null;
    result.value = null;
    const body = new FormData();
    body.append('file', file.value);
    try {
        const { data } = await api.post<ImportResult>('/api/master/outlets/import', body, { headers: { 'Content-Type': 'multipart/form-data' } });
        result.value = data;
        emit('imported', data);
        file.value = null;
        if (input.value) input.value.value = '';
    } catch (e) {
        const err = normalizeError(e);
        error.value = err.errors.file?.[0] ?? err.message;
    } finally {
        uploading.value = false;
    }
}

const stats = (r: ImportResult) => [
    { key: 'created', label: t('master.import.created'), value: r.created },
    { key: 'updated', label: t('master.import.updated'), value: r.updated },
    { key: 'geocoded', label: t('master.import.geocoded'), value: r.geocoded },
    { key: 'needs_review', label: t('master.import.needs_review'), value: r.needs_review },
    { key: 'failed', label: t('master.import.failed'), value: r.failed },
];
</script>

<template>
    <Panel :title="t('master.import.title')" :hint="t('master.import.hint')">
        <template #actions>
            <a :href="sampleHref" download="contoh-outlet.csv" class="text-xs text-focus underline-offset-2 hover:underline" data-testid="import-sample">{{ t('master.import.sample') }}</a>
        </template>
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="upload">
            <div class="flex min-w-0 flex-col gap-1">
                <label for="import-file" class="text-xs font-medium text-muted">{{ t('master.import.file_label') }}</label>
                <input
                    id="import-file"
                    ref="input"
                    type="file"
                    accept=".csv,text/csv"
                    data-testid="import-file"
                    class="max-w-full text-sm text-fg file:mr-3 file:h-9 file:cursor-pointer file:rounded-chip file:border file:border-line file:bg-raised/70 file:px-3 file:text-sm file:text-fg"
                    @change="onPick"
                />
            </div>
            <Button type="submit" variant="primary" :disabled="!file" :loading="uploading" data-testid="import-submit">
                <Icon name="send" :size="14" class="-rotate-90" />
                {{ t('master.import.submit') }}
            </Button>
        </form>

        <p v-if="error" class="rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert" data-testid="import-error">{{ error }}</p>

        <div v-if="result" data-testid="import-result" class="grid gap-3" role="status">
            <dl class="m-0 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="s in stats(result)" :key="s.key" :data-testid="`import-${s.key}`">
                    <dt class="hud-label">{{ s.label }}</dt>
                    <dd class="num m-0 text-xl font-semibold">{{ formatNumber(s.value) }}</dd>
                </div>
            </dl>
            <div v-if="result.errors?.length" data-testid="import-errors">
                <p class="text-sm font-medium text-danger">{{ t('master.import.error_rows', { n: result.errors.length }) }}</p>
                <ul class="m-0 mt-1 max-h-48 list-none overflow-y-auto p-0 text-xs">
                    <li v-for="(er, i) in result.errors" :key="i" class="flex gap-3 border-b border-line/60 py-1">
                        <span class="num shrink-0 text-muted">{{ t('master.import.row', { n: er.row }) }}</span>
                        <span>{{ er.message }}</span>
                    </li>
                </ul>
            </div>
            <p v-else class="text-sm text-st-won">{{ t('master.import.all_ok') }}</p>
        </div>
    </Panel>
</template>
