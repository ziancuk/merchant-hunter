<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Tooltip from '@/Components/ui/Tooltip.vue';
import { useI18n } from '@/i18n';

const props = withDefaults(defineProps<{ field?: string }>(), { field: undefined });
const { t } = useI18n();
</script>

<template>
    <Tooltip :text="props.field ? t('prospect.simulated.tooltip_field', { field: props.field }) : t('prospect.simulated.tooltip')">
        <span tabindex="0" class="focus-ring inline-flex rounded-full" data-testid="simulasi-badge" :aria-label="props.field ? t('prospect.simulated.aria_field', { field: props.field }) : t('prospect.simulated.aria')">
            <Badge tone="simulasi">{{ t('prospect.badge.simulated') }}</Badge>
        </span>
    </Tooltip>
</template>
