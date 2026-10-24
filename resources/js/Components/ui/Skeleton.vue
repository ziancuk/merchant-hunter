<script setup lang="ts">
withDefaults(defineProps<{ width?: string; height?: string; lines?: number; circle?: boolean }>(), {
    width: '100%',
    height: '1rem',
    lines: 1,
    circle: false,
});
</script>

<template>
    <div role="status" aria-live="polite" aria-busy="true" class="space-y-2">
        <span class="sr-only">Memuat…</span>
        <div
            v-for="n in lines"
            :key="n"
            class="skeleton"
            :class="circle ? 'rounded-full' : 'rounded-chip'"
            :style="{ width: lines > 1 && n === lines ? '60%' : width, height: circle ? width : height }"
            aria-hidden="true"
        />
    </div>
</template>
