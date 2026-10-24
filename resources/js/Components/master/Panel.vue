<script setup lang="ts">
import { useId } from 'vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';

defineProps<{ title: string; hint?: string }>();
const id = `panel-${useId()}`;
</script>

<template>
    <GlassPanel as="section" :aria-labelledby="id" class="flex min-w-0 flex-col gap-3">
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 :id="id" class="hud-label">{{ title }}</h2>
                <p v-if="hint" class="mt-0.5 text-xs text-muted">{{ hint }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2"><slot name="actions" /></div>
        </header>
        <slot />
    </GlassPanel>
</template>
