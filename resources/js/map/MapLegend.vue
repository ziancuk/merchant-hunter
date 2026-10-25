<script setup lang="ts">
import { useI18n } from '@/i18n';
import { BAND_HEX, STATUS_HEX } from '@/lib/colors';
import { BAND_LABEL, STATUS_LABEL } from '@/lib/labels';
import { PIPELINE_STATUSES, type ScoreBand } from '@/types';
import type { ColorBy } from './types';

defineProps<{ colorBy: ColorBy }>();
const emit = defineEmits<{ 'update:colorBy': [value: ColorBy] }>();
const { t } = useI18n();

const bands: ScoreBand[] = ['hot', 'warm', 'medium', 'cold'];
const pill = 'cursor-pointer rounded-full px-2.5 py-1 text-2xs font-medium transition';
</script>

<template>
    <section class="glass hidden w-56 rounded-panel p-3 sm:block" :aria-label="t('map.legend.aria')" data-testid="map-legend">
        <div class="flex items-center justify-between gap-2">
            <h2 class="hud-label">{{ t('map.legend.color_by') }}</h2>
            <div class="flex rounded-full bg-fg/5 p-0.5" role="radiogroup" :aria-label="t('map.legend.color_by_aria')">
                <button type="button" role="radio" :aria-checked="colorBy === 'score'" :class="[pill, colorBy === 'score' ? 'bg-fg text-ink' : 'text-muted hover:text-fg']" data-testid="color-by-score" @click="emit('update:colorBy', 'score')">{{ t('map.legend.score') }}</button>
                <button type="button" role="radio" :aria-checked="colorBy === 'status'" :class="[pill, colorBy === 'status' ? 'bg-fg text-ink' : 'text-muted hover:text-fg']" data-testid="color-by-status" @click="emit('update:colorBy', 'status')">{{ t('map.legend.status') }}</button>
            </div>
        </div>
        <ul class="mt-2 grid gap-1 text-xs">
            <template v-if="colorBy === 'score'">
                <li v-for="b in bands" :key="b" class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: BAND_HEX.map[b] }" aria-hidden="true" />
                    {{ BAND_LABEL[b] }}
                </li>
            </template>
            <template v-else>
                <li v-for="s in PIPELINE_STATUSES" :key="s" class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: STATUS_HEX.map[s] }" aria-hidden="true" />
                    {{ STATUS_LABEL[s] }}
                </li>
            </template>
        </ul>
        <ul class="mt-2 grid gap-1 border-t border-line pt-2 text-xs text-muted">
            <li class="flex items-center gap-2"><span class="grid h-3.5 w-3.5 place-items-center rounded-full bg-focus/30" aria-hidden="true"><span class="h-2 w-2 rounded-full bg-fg" /></span> {{ t('map.legend.outlet') }}</li>
            <li class="flex items-center gap-2"><span class="h-3 w-3 rounded-full border-2 border-hot" aria-hidden="true" /> {{ t('map.legend.new_open') }}</li>
            <li class="flex items-center gap-2"><span class="h-3 w-3 rounded-full border border-fg/60" aria-hidden="true" /> {{ t('map.legend.simulated') }}</li>
        </ul>
    </section>
</template>
