<script setup lang="ts">
export type BadgeTone = 'neutral' | 'hot' | 'warm' | 'medium' | 'cold' | 'info' | 'success' | 'danger' | 'simulasi';

withDefaults(defineProps<{ tone?: BadgeTone; dot?: boolean; pulse?: boolean; size?: 'sm' | 'md' }>(), {
    tone: 'neutral',
    dot: false,
    pulse: false,
    size: 'sm',
});

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-fg/10 text-muted border-line',
    hot: 'bg-hot/15 text-hot border-hot/40',
    warm: 'bg-warm/15 text-warm border-warm/40',
    medium: 'bg-medium/15 text-medium border-medium/40',
    cold: 'bg-cold/15 text-cold border-cold/40',
    info: 'bg-st-contacted/15 text-st-contacted border-st-contacted/40',
    success: 'bg-st-won/15 text-st-won border-st-won/40',
    danger: 'bg-danger/15 text-danger border-danger/40',
    simulasi: 'bg-transparent text-muted border-dashed border-muted/60',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border font-medium"
        :class="[tones[tone], size === 'sm' ? 'px-2 py-0.5 text-2xs' : 'px-2.5 py-1 text-xs']"
    >
        <span v-if="dot || pulse" class="h-1.5 w-1.5 rounded-full bg-current" :class="pulse ? 'animate-pulse-ring' : ''" aria-hidden="true" />
        <slot />
    </span>
</template>
