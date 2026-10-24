<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import Icon from './Icon.vue';



const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        title: string;
        description?: string;
        side?: 'right' | 'left';
        /** Tailwind max-width class for the panel. */
        widthClass?: string;
        /** Modal: dims the map, traps focus, sets aria-modal. Non-modal leaves the map interactive. */
        modal?: boolean;
    }>(),
    { description: undefined, side: 'right', widthClass: 'sm:max-w-[460px]', modal: true },
);

const emit = defineEmits<{ 'update:modelValue': [value: boolean]; close: [] }>();

const titleId = `drawer-title-${useId()}`;
const panel = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

function close(): void {
    emit('update:modelValue', false);
    emit('close');
}

function onWindowKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && !e.defaultPrevented) {
        e.stopPropagation();
        close();
    }
}

function onPanelKeydown(e: KeyboardEvent): void {
    if (e.key !== 'Tab' || !props.modal || !panel.value) return;
    const items = Array.from(panel.value.querySelectorAll<HTMLElement>(FOCUSABLE));
    if (items.length === 0) {
        e.preventDefault();
        panel.value.focus();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    if (e.shiftKey && (active === first || active === panel.value)) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
    }
}

watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            window.addEventListener('keydown', onWindowKeydown);
            await nextTick();
            const target = panel.value?.querySelector<HTMLElement>('[data-autofocus]') ?? panel.value?.querySelector<HTMLElement>(FOCUSABLE) ?? panel.value;
            target?.focus();
        } else {
            window.removeEventListener('keydown', onWindowKeydown);
            await nextTick();
            if (previouslyFocused && document.contains(previouslyFocused)) previouslyFocused.focus();
            previouslyFocused = null;
        }
    },
    { immediate: true, flush: 'post' },
);

onBeforeUnmount(() => window.removeEventListener('keydown', onWindowKeydown));
</script>

<template>
    <div class="contents">
        <Transition name="fade">
            <div v-if="modelValue && modal" class="fixed inset-0 z-30 bg-black/40" aria-hidden="true" data-testid="drawer-scrim" @click="close" />
        </Transition>
        <Transition :name="side === 'left' ? 'drawer-left' : 'drawer'">
            <div
                v-if="modelValue"
                ref="panel"
                role="dialog"
                :aria-modal="modal ? 'true' : 'false'"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="glass fixed bottom-0 top-0 z-40 flex w-full flex-col overflow-hidden text-fg outline-none sm:bottom-3 sm:top-3 sm:rounded-panel"
                :class="[widthClass, side === 'left' ? 'left-0 sm:left-3' : 'right-0 sm:right-3']"
                @keydown="onPanelKeydown"
            >
                <header class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
                    <div class="min-w-0">
                        <slot :id="titleId" name="title">
                            <h2 :id="titleId" class="truncate font-display text-lg font-semibold">{{ title }}</h2>
                        </slot>
                        <p v-if="description" class="text-xs text-muted">{{ description }}</p>
                    </div>
                    <button
                        type="button"
                        class="grid h-8 w-8 shrink-0 cursor-pointer place-items-center rounded-chip text-muted transition hover:bg-fg/10 hover:text-fg"
                        aria-label="Tutup panel"
                        @click="close"
                    >
                        <Icon name="close" />
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <slot />
                </div>
                <footer v-if="$slots.footer" class="border-t border-line px-4 py-3">
                    <slot name="footer" />
                </footer>
            </div>
        </Transition>
    </div>
</template>
