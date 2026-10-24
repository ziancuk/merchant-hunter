<script setup lang="ts">
defineProps<{ eyebrow: string; title: string; description?: string }>();
</script>

<template>
    <header class="mb-4 flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <p class="hud-label">{{ eyebrow }}</p>
            <h1 class="font-display text-2xl font-semibold leading-tight tracking-tight sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-2xl text-sm text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
