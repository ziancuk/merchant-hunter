<script setup lang="ts">
import { ref, useId } from 'vue';



withDefaults(defineProps<{ text: string; placement?: 'top' | 'bottom' }>(), { placement: 'top' });

const id = `tip-${useId()}`;
const visible = ref(false);
</script>

<template>
    <span
        class="relative inline-flex"
        :aria-describedby="visible ? id : undefined"
        @mouseenter="visible = true"
        @mouseleave="visible = false"
        @focusin="visible = true"
        @focusout="visible = false"
        @keydown.esc="visible = false"
    >
        <slot />
        <Transition name="fade">
            <span
                v-if="visible"
                :id="id"
                role="tooltip"
                class="glass pointer-events-none absolute left-1/2 z-[80] w-max max-w-[16rem] -translate-x-1/2 rounded-chip px-2.5 py-1.5 text-xs font-normal normal-case leading-snug tracking-normal text-fg"
                :class="placement === 'top' ? 'bottom-full mb-2' : 'top-full mt-2'"
            >
                {{ text }}
            </span>
        </Transition>
    </span>
</template>
