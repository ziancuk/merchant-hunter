<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useI18n } from '@/i18n';
import { PIPELINE_STATUSES, type PipelineStatus } from '@/types';
import { COLUMN_LABEL } from '@/lib/labels';
import { MAX_ROUTE_STOPS, MIN_ROUTE_STOPS } from './pipeline';

const props = defineProps<{ count: number; busy?: boolean }>();
const { t } = useI18n();
const emit = defineEmits<{ status: [status: PipelineStatus]; 'opt-out': []; route: []; clear: [] }>();

const routeBlocked = computed(() => (props.count < MIN_ROUTE_STOPS ? t('pipeline.bulk.routeMin', { n: MIN_ROUTE_STOPS }) : props.count > MAX_ROUTE_STOPS ? t('pipeline.bulk.routeMax', { n: MAX_ROUTE_STOPS }) : ''));

function onStatus(e: Event): void {
    const el = e.target as HTMLSelectElement;
    const value = el.value as PipelineStatus | '';
    el.value = '';
    if (value) emit('status', value);
}
</script>

<template>
    <div
        class="glass fixed bottom-20 left-1/2 z-30 flex w-[calc(100%-2rem)] max-w-3xl -translate-x-1/2 flex-wrap items-center gap-2 rounded-panel p-2.5 shadow-float md:bottom-6"
        role="region"
        :aria-label="t('pipeline.bulk.region')"
        data-testid="bulk-bar"
    >
        <p class="num px-2 text-sm font-semibold text-fg" role="status" data-testid="bulk-count">{{ t('pipeline.bulk.selected', { count }) }}</p>
        <label class="sr-only" for="bulk-status">{{ t('pipeline.bulk.changeStatusLabel') }}</label>
        <select id="bulk-status" class="h-9 rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg" :disabled="busy" data-testid="bulk-status" @change="onStatus">
            <option value="">{{ t('pipeline.bulk.changeStatusTo') }}</option>
            <option v-for="s in PIPELINE_STATUSES" :key="s" :value="s">{{ COLUMN_LABEL[s] }}</option>
        </select>
        <Button size="sm" variant="danger" :disabled="busy" data-testid="bulk-optout" @click="emit('opt-out')"><Icon name="ban" :size="14" /> {{ t('pipeline.bulk.optOut') }}</Button>
        <Button size="sm" variant="secondary" :disabled="busy || !!routeBlocked" :title="routeBlocked || undefined" data-testid="bulk-route" @click="emit('route')">
            <Icon name="navigation" :size="14" /> {{ t('pipeline.bulk.route') }}
        </Button>
        <Button size="sm" variant="ghost" class="ml-auto" :disabled="busy" data-testid="bulk-clear" @click="emit('clear')">{{ t('pipeline.bulk.clear') }}</Button>
        <p v-if="routeBlocked" class="w-full px-2 text-2xs text-muted">{{ routeBlocked }}.</p>
    </div>
</template>
