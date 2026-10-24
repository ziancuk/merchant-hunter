<script setup lang="ts">
import { computed } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import ProspectBadges from './ProspectBadges.vue';
import ScoreRing from './ScoreRing.vue';
import { relativeTime } from '@/lib/format';
import type { PipelineCard } from '@/types';

const props = withDefaults(defineProps<{ prospect: PipelineCard; selected?: boolean }>(), { selected: false });
const emit = defineEmits<{ select: [id: number] }>();

const subtitle = computed(() => [props.prospect.category, props.prospect.city].filter(Boolean).join(' · '));
</script>

<template>
    <button
        type="button"
        class="group flex w-full cursor-pointer items-start gap-3 rounded-panel border bg-surface/80 p-3 text-left transition hover:border-fg/30 hover:bg-raised/80"
        :class="[selected ? 'border-focus' : 'border-line', prospect.do_not_contact ? 'opacity-70' : '']"
        :aria-pressed="selected"
        :aria-label="`Prospek ${prospect.name}, skor ${prospect.score}`"
        data-testid="prospect-card"
        @click="emit('select', prospect.id)"
    >
        <ScoreRing :score="prospect.score" :band="prospect.score_band" :size="44" />
        <span class="min-w-0 flex-1">
            <span class="block truncate font-display text-sm font-semibold text-fg">{{ prospect.name }}</span>
            <span class="block truncate text-xs text-muted">{{ subtitle }}</span>
            <span class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                <ProspectBadges :badges="prospect.badges" />
                <span v-if="!prospect.phone_present" class="inline-flex items-center gap-1 text-2xs text-muted" data-testid="no-phone">
                    <Icon name="phone" :size="12" /> Tanpa telepon
                </span>
            </span>
            <span v-if="prospect.last_contacted_at || prospect.handled_by" class="num mt-1 block truncate text-2xs text-muted">
                <template v-if="prospect.last_contacted_at">Dihubungi {{ relativeTime(prospect.last_contacted_at) }}</template>
                <template v-if="prospect.handled_by"> · {{ prospect.handled_by }}</template>
            </span>
        </span>
    </button>
</template>
