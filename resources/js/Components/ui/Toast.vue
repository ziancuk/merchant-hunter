<script setup lang="ts">
import Icon from './Icon.vue';
import { useI18n } from '@/i18n';
import type { ToastItem } from '@/composables/useToasts';

defineProps<{ items: readonly ToastItem[] }>();
const emit = defineEmits<{ dismiss: [id: number] }>();
const { t } = useI18n();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 md:bottom-6" aria-live="polite">
        <div
            v-for="toast in items"
            :key="toast.id"
            class="glass pointer-events-auto flex max-w-md items-start gap-3 rounded-panel px-4 py-3 text-sm text-fg"
            :class="toast.tone === 'error' ? 'border-danger/60' : ''"
            :role="toast.tone === 'error' ? 'alert' : 'status'"
            style="animation: toast-in 220ms var(--ease-out-expo)"
        >
            <Icon :name="toast.tone === 'error' ? 'alert' : toast.tone === 'success' ? 'check' : 'info'" :class="toast.tone === 'error' ? 'text-danger' : toast.tone === 'success' ? 'text-st-won' : 'text-st-contacted'" />
            <p class="min-w-0 flex-1">{{ toast.message }}</p>
            <button type="button" class="cursor-pointer text-muted hover:text-fg" :aria-label="t('ui.closeToast')" @click="emit('dismiss', toast.id)">
                <Icon name="close" :size="14" />
            </button>
        </div>
    </div>
</template>
