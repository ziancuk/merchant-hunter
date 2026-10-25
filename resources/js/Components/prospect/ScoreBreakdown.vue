<script setup lang="ts">
import { computed } from 'vue';
import { BAND_CLASSES, scoreBand } from '@/lib/colors';
import { useI18n } from '@/i18n';
import { formatNumber } from '@/lib/format';
import type { ScoreBand, ScoreComponent } from '@/types';

const props = defineProps<{ components: ScoreComponent[]; band?: ScoreBand }>();
const { t } = useI18n();

const rows = computed(() =>
    props.components.map((c) => {
        const ratio = c.max > 0 ? Math.max(0, Math.min(1, c.points / c.max)) : 0;
        return { ...c, ratio, band: props.band ?? scoreBand(Math.round(ratio * 100)) };
    }),
);
</script>

<template>
    <ul v-if="rows.length" class="space-y-3" :aria-label="t('prospect.score.aria')">
        <li v-for="row in rows" :key="row.key" data-testid="score-row">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span class="text-fg">{{ row.label }}</span>
                <span class="num text-xs text-muted">
                    <span class="text-fg">{{ formatNumber(row.points, 1) }}</span> / {{ formatNumber(row.max) }}
                </span>
            </div>
            <div
                class="mt-1 h-1.5 overflow-hidden rounded-full bg-fg/10"
                role="meter"
                :aria-label="row.label"
                :aria-valuemin="0"
                :aria-valuemax="row.max"
                :aria-valuenow="row.points"
            >
                <div class="h-full rounded-full" :class="BAND_CLASSES[row.band].bg" :style="{ width: `${row.ratio * 100}%`, transition: 'width 500ms var(--ease-out-expo)' }" />
            </div>
            <p class="mt-1 text-xs text-muted">{{ row.reason }}</p>
        </li>
    </ul>
    <p v-else class="text-sm text-muted">{{ t('prospect.score.empty') }}</p>
</template>
