<script setup lang="ts">
import Icon from './Icon.vue';
import type { ToastItem } from '@/composables/useToasts';

defineProps<{ items: readonly ToastItem[] }>();
const emit = defineEmits<{ dismiss: [id: number] }>();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 md:bottom-6" aria-live="polite">
        <div
            v-for="t in items"
            :key="t.id"
            class="glass pointer-events-auto flex max-w-md items-start gap-3 rounded-panel px-4 py-3 text-sm text-fg"
            :class="t.tone === 'error' ? 'border-danger/60' : ''"
            :role="t.tone === 'error' ? 'alert' : 'status'"
            style="animation: toast-in 220ms var(--ease-out-expo)"
        >
            <Icon :name="t.tone === 'error' ? 'alert' : t.tone === 'success' ? 'check' : 'info'" :class="t.tone === 'error' ? 'text-danger' : t.tone === 'success' ? 'text-st-won' : 'text-st-contacted'" />
            <p class="min-w-0 flex-1">{{ t.message }}</p>
            <button type="button" class="cursor-pointer text-muted hover:text-fg" aria-label="Tutup notifikasi" @click="emit('dismiss', t.id)">
                <Icon name="close" :size="14" />
            </button>
        </div>
    </div>
</template>
