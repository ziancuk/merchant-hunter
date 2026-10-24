<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { PIPELINE_STATUSES, type PipelineStatus } from '@/types';
import { COLUMN_LABEL, MAX_ROUTE_STOPS, MIN_ROUTE_STOPS } from './pipeline';

const props = defineProps<{ count: number; busy?: boolean }>();
const emit = defineEmits<{ status: [status: PipelineStatus]; 'opt-out': []; route: []; clear: [] }>();

const routeBlocked = computed(() => (props.count < MIN_ROUTE_STOPS ? `Pilih minimal ${MIN_ROUTE_STOPS} prospek untuk rute` : props.count > MAX_ROUTE_STOPS ? `Maksimal ${MAX_ROUTE_STOPS} prospek per rute` : ''));

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
        aria-label="Aksi massal"
        data-testid="bulk-bar"
    >
        <p class="num px-2 text-sm font-semibold text-fg" role="status" data-testid="bulk-count">{{ count }} dipilih</p>
        <label class="sr-only" for="bulk-status">Ubah status yang dipilih</label>
        <select id="bulk-status" class="h-9 rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg" :disabled="busy" data-testid="bulk-status" @change="onStatus">
            <option value="">Ubah status ke…</option>
            <option v-for="s in PIPELINE_STATUSES" :key="s" :value="s">{{ COLUMN_LABEL[s] }}</option>
        </select>
        <Button size="sm" variant="danger" :disabled="busy" data-testid="bulk-optout" @click="emit('opt-out')"><Icon name="ban" :size="14" /> Jangan hubungi</Button>
        <Button size="sm" variant="secondary" :disabled="busy || !!routeBlocked" :title="routeBlocked || undefined" data-testid="bulk-route" @click="emit('route')">
            <Icon name="navigation" :size="14" /> Rencanakan rute
        </Button>
        <Button size="sm" variant="ghost" class="ml-auto" :disabled="busy" data-testid="bulk-clear" @click="emit('clear')">Bersihkan pilihan</Button>
        <p v-if="routeBlocked" class="w-full px-2 text-2xs text-muted">{{ routeBlocked }}.</p>
    </div>
</template>
