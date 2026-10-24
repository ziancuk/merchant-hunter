<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import SimulasiBadge from './SimulasiBadge.vue';
import type { Badge as BadgeKind } from '@/types';

defineProps<{ badges: BadgeKind[] }>();
</script>

<template>
    <span v-if="badges.length" class="inline-flex flex-wrap items-center gap-1.5">
        <Badge v-if="badges.includes('new_open')" tone="hot" pulse>Baru buka</Badge>
        <SimulasiBadge v-if="badges.includes('simulated')" />
        <Badge v-if="badges.includes('closed')" tone="danger">Tutup</Badge>
        <Badge v-if="badges.includes('opt_out')" tone="danger">Jangan hubungi</Badge>
    </span>
</template>
