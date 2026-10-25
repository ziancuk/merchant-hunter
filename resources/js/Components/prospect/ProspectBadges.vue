<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import { useI18n } from '@/i18n';
import SimulasiBadge from './SimulasiBadge.vue';
import type { Badge as BadgeKind } from '@/types';

defineProps<{ badges: BadgeKind[] }>();
const { t } = useI18n();
</script>

<template>
    <span v-if="badges.length" class="inline-flex flex-wrap items-center gap-1.5">
        <Badge v-if="badges.includes('new_open')" tone="hot" pulse>{{ t('prospect.badge.new_open') }}</Badge>
        <SimulasiBadge v-if="badges.includes('simulated')" />
        <Badge v-if="badges.includes('closed')" tone="danger">{{ t('prospect.badge.closed') }}</Badge>
        <Badge v-if="badges.includes('opt_out')" tone="danger">{{ t('prospect.badge.opt_out') }}</Badge>
    </span>
</template>
