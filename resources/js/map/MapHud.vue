<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from '@/lib/format';
import type { MapMode } from '@/types';

export interface HudProps {
    outlets: number;
    prospects_active: number;
    new_7d: number;
    hot: number;
}

const props = defineProps<{
    hud: HudProps;
    /** Number of items currently shown on the map (points / grid total / white space cells); null while unknown. */
    count: number | null;
    mode: MapMode;
    loading: boolean;
    truncated: boolean;
    /** Show only the count pill (phones). */
    compact?: boolean;
}>();

const unit = computed(() => (props.mode === 'whitespace' ? 'sel white space' : 'prospek tampil'));
const stats = computed(() => [
    { key: 'outlets', label: 'Outlet', value: props.hud.outlets },
    { key: 'prospects', label: 'Prospek aktif', value: props.hud.prospects_active },
    { key: 'new7d', label: 'Baru 7 hari', value: props.hud.new_7d },
    { key: 'hot', label: 'Panas', value: props.hud.hot },
]);
</script>

<template>
    <div v-if="compact" class="glass flex items-center gap-2 rounded-full px-3 py-1.5" :aria-busy="loading">
        <span class="num text-sm font-semibold" data-testid="hud-count-compact">{{ count === null ? '–' : formatNumber(count) }}</span>
        <span class="hud-label">{{ unit }}</span>
    </div>
    <section v-else class="glass w-[15.5rem] rounded-panel p-3" aria-label="Ringkasan peta" data-testid="map-hud" :aria-busy="loading">
        <div class="flex items-baseline justify-between gap-2">
            <p class="hud-label">{{ unit }}</p>
            <span v-if="loading" class="hud-label animate-pulse text-focus" role="status">memuat…</span>
        </div>
        <p class="num mt-0.5 text-3xl font-semibold leading-none" data-testid="hud-count" :data-count="count ?? ''" aria-live="polite">{{ count === null ? '–' : formatNumber(count) }}</p>
        <p v-if="truncated" class="mt-1 text-2xs text-warm" data-testid="hud-truncated">Dibatasi 20.000 titik; perbesar peta untuk rincian.</p>
        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-line pt-3">
            <div v-for="s in stats" :key="s.key">
                <dt class="hud-label">{{ s.label }}</dt>
                <dd class="num text-sm font-medium" :data-testid="`hud-${s.key}`">{{ formatNumber(s.value) }}</dd>
            </div>
        </dl>
    </section>
</template>
