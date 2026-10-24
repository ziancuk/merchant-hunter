<script setup lang="ts">
withDefaults(defineProps<{ as?: string; padded?: boolean; rounded?: 'panel' | 'full' | 'chip' }>(), {
    as: 'div',
    padded: true,
    rounded: 'panel',
});
</script>

<template>
    <component
        :is="as"
        class="glass text-fg"
        :class="[padded ? 'p-4' : '', rounded === 'panel' ? 'rounded-panel' : rounded === 'full' ? 'rounded-full' : 'rounded-chip']"
    >
        <slot />
    </component>
</template>
