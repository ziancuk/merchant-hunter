<script setup lang="ts">
import { computed, useAttrs } from 'vue';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'hot' | 'danger';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: 'sm' | 'md' | 'lg';
        type?: 'button' | 'submit' | 'reset';
        loading?: boolean;
        disabled?: boolean;
        /** Square icon-only button. Requires an `aria-label` attribute. */
        iconOnly?: boolean;
    }>(),
    { variant: 'secondary', size: 'md', type: 'button', loading: false, disabled: false, iconOnly: false },
);

const attrs = useAttrs();
if (import.meta.env?.DEV && props.iconOnly && !attrs['aria-label']) {
    console.warn('[Button] iconOnly buttons need an aria-label.');
}

const variants: Record<ButtonVariant, string> = {
    primary: 'bg-fg text-ink hover:opacity-90',
    secondary: 'bg-raised/70 text-fg border border-line hover:bg-raised',
    ghost: 'text-fg hover:bg-fg/10',
    hot: 'bg-hot text-on-hot hover:brightness-110',
    danger: 'bg-danger/15 text-danger border border-danger/40 hover:bg-danger/25',
};
const sizes = computed(() => {
    const s = props.size;
    if (props.iconOnly) return s === 'sm' ? 'h-8 w-8' : s === 'lg' ? 'h-12 w-12' : 'h-10 w-10';
    return s === 'sm' ? 'h-8 px-3 text-xs' : s === 'lg' ? 'h-12 px-6 text-base' : 'h-10 px-4 text-sm';
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading || undefined"
        class="inline-flex shrink-0 cursor-pointer select-none items-center justify-center gap-2 rounded-chip font-medium transition duration-150 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100"
        :class="[variants[variant], sizes]"
    >
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <slot />
    </button>
</template>
