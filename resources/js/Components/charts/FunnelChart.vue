<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber, formatPercent } from '@/lib/format';

export interface FunnelStage {
    key: string;
    label: string;
    value: number;
    /** CSS colour of the stage bar. */
    color: string;
}

const props = defineProps<{
    stages: FunnelStage[];
    /** Lost prospects are drawn apart from the cumulative funnel (D-64). */
    lost?: { label: string; value: number; color: string } | null;
    label: string;
}>();

const base = computed(() => Math.max(1, props.stages[0]?.value ?? 1));
const rows = computed(() =>
    props.stages.map((s, i) => {
        const prev = i === 0 ? null : props.stages[i - 1].value;
        return {
            ...s,
            width: `${Math.max(s.value > 0 ? 3 : 0, (s.value / base.value) * 100)}%`,
            fromPrev: prev === null ? null : prev > 0 ? s.value / prev : 0,
        };
    }),
);
</script>

<template>
    <div role="group" :aria-label="label" class="flex flex-col gap-3" data-testid="funnel">
        <ol class="m-0 flex list-none flex-col gap-2.5 p-0">
            <li v-for="r in rows" :key="r.key" :data-testid="`funnel-${r.key}`">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span>{{ r.label }}</span>
                    <span class="num">
                        {{ formatNumber(r.value) }}
                        <span v-if="r.fromPrev !== null" class="ml-1 text-2xs text-muted">{{ formatPercent(r.fromPrev) }} dari tahap sebelumnya</span>
                    </span>
                </div>
                <div class="mt-1 h-3 overflow-hidden rounded-chip bg-fg/10" aria-hidden="true">
                    <div class="h-full rounded-chip transition-[width] duration-[420ms] ease-[var(--ease-out-expo)]" :style="{ width: r.width, background: r.color }" />
                </div>
            </li>
        </ol>
        <div v-if="lost" class="flex items-center justify-between gap-3 border-t border-dashed border-line pt-2 text-sm" data-testid="funnel-lost">
            <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full" :style="{ background: lost.color }" aria-hidden="true" />{{ lost.label }} <span class="text-2xs text-muted">(terpisah dari corong)</span></span>
            <span class="num">{{ formatNumber(lost.value) }}</span>
        </div>
    </div>
</template>
