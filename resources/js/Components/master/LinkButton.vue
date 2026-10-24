<script setup lang="ts">
withDefaults(defineProps<{ href: string; download?: boolean | string; variant?: 'secondary' | 'ghost' }>(), { download: false, variant: 'secondary' });
</script>

<template>
    <a
        :href="href"
        :download="download === false ? undefined : download === true ? '' : download"
        class="inline-flex h-8 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-chip px-3 text-xs font-medium transition"
        :class="variant === 'secondary' ? 'border border-line bg-raised/70 text-fg hover:bg-raised' : 'text-fg hover:bg-fg/10'"
    >
        <slot />
    </a>
</template>
