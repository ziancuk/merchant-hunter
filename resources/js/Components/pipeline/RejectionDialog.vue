<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import type { RejectionReason } from '@/Components/prospect/ProspectDrawer.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        reasons: RejectionReason[];
        title?: string;
        description?: string;
        busy?: boolean;
    }>(),
    { title: 'Alasan penolakan', description: 'Pilih alasan terstandar. Alasan ini dicatat di riwayat prospek.', busy: false },
);
const emit = defineEmits<{ confirm: [reasonId: number]; cancel: [] }>();

const titleId = `rej-title-${useId()}`;
const selectId = `rej-select-${useId()}`;
const reasonId = ref<number | ''>('');
const dialog = ref<HTMLElement | null>(null);
const select = ref<HTMLSelectElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            reasonId.value = '';
            previouslyFocused = document.activeElement as HTMLElement | null;
            await nextTick();
            select.value?.focus();
        } else {
            previouslyFocused?.focus?.();
            previouslyFocused = null;
        }
    },
);

function submit(): void {
    if (reasonId.value === '' || props.busy) return;
    emit('confirm', reasonId.value);
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape') {
        e.stopPropagation();
        emit('cancel');
        return;
    }
    if (e.key !== 'Tab' || !dialog.value) return;
    const items = Array.from(dialog.value.querySelectorAll<HTMLElement>('select,button:not([disabled])'));
    if (items.length === 0) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 grid place-items-center p-4" @keydown="onKeydown">
        <div class="absolute inset-0 bg-ink/70" aria-hidden="true" @click="emit('cancel')" />
        <div
            ref="dialog"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            class="glass relative w-full max-w-sm space-y-4 rounded-panel p-5 shadow-pop"
            data-testid="rejection-dialog"
        >
            <div>
                <h2 :id="titleId" class="font-display text-lg font-semibold text-fg">{{ title }}</h2>
                <p class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <div>
                <label :for="selectId" class="hud-label mb-1 block">Alasan (wajib)</label>
                <select
                    :id="selectId"
                    ref="select"
                    v-model="reasonId"
                    class="h-11 w-full rounded-chip border border-line bg-ink/60 px-2 text-base text-fg sm:text-sm"
                    data-testid="rejection-select"
                >
                    <option value="">Pilih alasan…</option>
                    <option v-for="r in reasons" :key="r.id" :value="r.id">{{ r.label }}</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <Button variant="ghost" data-testid="rejection-cancel" @click="emit('cancel')">Batal</Button>
                <Button variant="danger" :disabled="reasonId === ''" :loading="busy" data-testid="rejection-confirm" @click="submit">Tandai ditolak</Button>
            </div>
        </div>
    </div>
</template>
