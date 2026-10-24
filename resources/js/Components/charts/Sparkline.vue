<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        values: number[];
        /** Accessible description, e.g. "Prospek baru per run". */
        label: string;
        width?: number;
        height?: number;
        /** CSS colour (variable reference) of the line. */
        color?: string;
    }>(),
    { width: 160, height: 36, color: 'var(--c-medium)' },
);

const pad = 3;
const points = computed(() => {
    const v = props.values;
    if (v.length === 0) return [] as [number, number][];
    const min = Math.min(...v);
    const max = Math.max(...v);
    const span = max - min || 1;
    const stepX = v.length > 1 ? (props.width - pad * 2) / (v.length - 1) : 0;
    return v.map((n, i): [number, number] => [
        v.length > 1 ? pad + i * stepX : props.width / 2,
        props.height - pad - ((n - min) / span) * (props.height - pad * 2),
    ]);
});
const line = computed(() => points.value.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' '));
const area = computed(() => {
    if (points.value.length < 2) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `M${first[0]},${props.height - pad} L${line.value.split(' ').join(' L')} L${last[0]},${props.height - pad} Z`;
});
const last = computed(() => points.value[points.value.length - 1] ?? null);
const summary = computed(() => {
    const v = props.values;
    if (v.length === 0) return `${props.label}: tidak ada data`;
    return `${props.label}: ${v.length} titik, terakhir ${v[v.length - 1]}, terendah ${Math.min(...v)}, tertinggi ${Math.max(...v)}`;
});
</script>

<template>
    <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" role="img" :aria-label="summary" data-testid="sparkline" class="overflow-visible">
        <template v-if="points.length > 0">
            <path v-if="area" :d="area" :fill="color" fill-opacity="0.12" />
            <polyline v-if="points.length > 1" :points="line" fill="none" :stroke="color" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
            <circle v-if="last" :cx="last[0]" :cy="last[1]" r="2.5" :fill="color" />
        </template>
        <line v-else :x1="pad" :x2="width - pad" :y1="height / 2" :y2="height / 2" stroke="var(--c-line)" stroke-dasharray="3 3" />
    </svg>
</template>
