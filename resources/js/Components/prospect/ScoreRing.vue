<script setup lang="ts">
import { computed } from 'vue';
import { BAND_LABEL, BAND_VAR, scoreBand } from '@/lib/colors';
import type { ScoreBand } from '@/types';

const props = withDefaults(defineProps<{ score: number; band?: ScoreBand; size?: number }>(), { band: undefined, size: 56 });

const effectiveBand = computed(() => props.band ?? scoreBand(props.score));
const R = 22;
const C = 2 * Math.PI * R;
const offset = computed(() => C * (1 - Math.max(0, Math.min(100, props.score)) / 100));
</script>

<template>
    <div class="relative inline-grid shrink-0 place-items-center" :style="{ width: `${size}px`, height: `${size}px` }" role="img" :aria-label="`Skor ${score} dari 100, ${BAND_LABEL[effectiveBand]}`">
        <svg viewBox="0 0 50 50" class="absolute inset-0 -rotate-90" aria-hidden="true">
            <circle cx="25" cy="25" :r="R" fill="none" stroke="var(--c-line)" stroke-width="4" />
            <circle
                cx="25"
                cy="25"
                :r="R"
                fill="none"
                :stroke="BAND_VAR[effectiveBand]"
                stroke-width="4"
                stroke-linecap="round"
                :stroke-dasharray="C"
                :stroke-dashoffset="offset"
                style="transition: stroke-dashoffset 600ms var(--ease-out-expo)"
            />
        </svg>
        <span class="num font-semibold leading-none" :style="{ color: BAND_VAR[effectiveBand], fontSize: `${size * 0.32}px` }" aria-hidden="true">{{ Math.round(score) }}</span>
    </div>
</template>
