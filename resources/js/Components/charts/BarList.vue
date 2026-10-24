<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from '@/lib/format';

export interface BarItem {
    label: string;
    value: number;
    /** Secondary text shown under the bar (e.g. "skor rata-rata 58"). */
    hint?: string;
    /** CSS colour override for this bar. */
    color?: string;
}

const props = withDefaults(
    defineProps<{
        items: BarItem[];
        label: string;
        format?: (n: number) => string;
        color?: string;
        /** Fixed max; defaults to the largest value. */
        max?: number;
    }>(),
    { format: (n: number) => formatNumber(n), color: 'var(--c-medium)', max: undefined },
);

const top = computed(() => props.max ?? Math.max(1, ...props.items.map((i) => i.value)));
const pct = (v: number) => `${Math.max(v > 0 ? 2 : 0, Math.min(100, (v / top.value) * 100))}%`;
</script>

<template>
    <ul class="m-0 flex list-none flex-col gap-2.5 p-0" :aria-label="label" data-testid="bar-list">
        <li v-for="(item, i) in items" :key="`${item.label}-${i}`" class="grid gap-1" data-testid="bar-item">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate">{{ item.label }}</span>
                <span class="num shrink-0 text-fg">{{ format(item.value) }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-fg/10" aria-hidden="true">
                <div class="h-full rounded-full transition-[width] duration-[420ms] ease-[var(--ease-out-expo)]" :style="{ width: pct(item.value), background: item.color ?? color }" />
            </div>
            <span v-if="item.hint" class="text-2xs text-muted">{{ item.hint }}</span>
        </li>
    </ul>
</template>
