<script setup lang="ts">
import { BAND_LABEL } from '@/lib/labels';
import type { ProspectDetail } from '@/types';
import ProspectBadges from './ProspectBadges.vue';
import ScoreRing from './ScoreRing.vue';

defineProps<{ detail: ProspectDetail }>();
</script>

<template>
    <section class="flex items-start gap-4">
        <ScoreRing :score="detail.score" :band="detail.score_band" :size="64" />
        <div class="min-w-0 flex-1 space-y-1.5">
            <p class="text-sm text-muted">{{ detail.category.name }}</p>
            <ProspectBadges :badges="detail.badges" />
            <p class="hud-label">Skor {{ BAND_LABEL[detail.score_band] }}</p>
        </div>
    </section>
</template>
