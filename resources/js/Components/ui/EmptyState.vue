<script setup lang="ts">
import Icon, { type IconName } from './Icon.vue';

withDefaults(defineProps<{ title: string; description?: string; icon?: IconName }>(), { description: undefined, icon: 'search' });
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center" data-testid="empty-state">
        <div class="relative grid h-16 w-16 place-items-center rounded-full border border-line text-muted">
            <span class="absolute inset-2 rounded-full border border-dashed border-line" aria-hidden="true" />
            <Icon :name="icon" :size="24" />
        </div>
        <h3 class="font-display text-base font-semibold text-fg">{{ title }}</h3>
        <p v-if="description" class="max-w-xs text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-1 flex flex-wrap justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
