<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { formatNumber } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        value: number;
        label?: string;
        decimals?: number;
        suffix?: string;
        prefix?: string;
        duration?: number;
        /** Custom formatter; defaults to Indonesian number formatting. */
        format?: (n: number) => string;
    }>(),
    { label: undefined, decimals: 0, suffix: '', prefix: '', duration: 900, format: undefined },
);

const reduced = useReducedMotion();
const display = ref(0);
let raf = 0;

const fmt = (n: number) => (props.format ? props.format(n) : formatNumber(n, props.decimals));
const text = computed(() => `${props.prefix}${fmt(display.value)}${props.suffix}`);
const finalText = computed(() => `${props.prefix}${fmt(props.value)}${props.suffix}`);

function animate(to: number): void {
    cancelAnimationFrame(raf);
    const from = display.value;
    if (reduced.value || props.duration <= 0 || from === to || typeof requestAnimationFrame !== 'function') {
        display.value = to;
        return;
    }
    const start = performance.now();
    const step = (now: number) => {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
        display.value = from + (to - from) * eased;
        if (t < 1) raf = requestAnimationFrame(step);
        else display.value = to;
    };
    raf = requestAnimationFrame(step);
}

watch(() => props.value, animate, { immediate: true });
onBeforeUnmount(() => cancelAnimationFrame(raf));
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <span v-if="label" class="hud-label">{{ label }}</span>
        <span class="num text-3xl font-semibold leading-none text-fg" data-testid="kpi-value" aria-hidden="true">{{ text }}</span>
        <span class="sr-only">{{ label ? `${label}: ` : '' }}{{ finalText }}</span>
    </div>
</template>
