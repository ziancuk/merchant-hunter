<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Tooltip from '@/Components/ui/Tooltip.vue';

withDefaults(defineProps<{ field?: string }>(), { field: undefined });
</script>

<template>
    <Tooltip :text="`${field ? field + ': ' : ''}Data simulasi untuk demo, bukan data nyata dari sumber.`">
        <span tabindex="0" class="focus-ring inline-flex rounded-full" data-testid="simulasi-badge" :aria-label="`${field ? field + ' ' : ''}berupa data simulasi`">
            <Badge tone="simulasi">Simulasi</Badge>
        </span>
    </Tooltip>
</template>
