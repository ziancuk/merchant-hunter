<script setup lang="ts">
import { useI18n } from '@/i18n';
import Button from './Button.vue';
import Icon from './Icon.vue';

withDefaults(defineProps<{ title?: string; message?: string; retryLabel?: string; retrying?: boolean }>(), {
    title: undefined,
    message: undefined,
    retryLabel: undefined,
    retrying: false,
});
const { t } = useI18n();
const emit = defineEmits<{ retry: [] }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center" role="alert" data-testid="error-state">
        <div class="grid h-14 w-14 place-items-center rounded-full border border-danger/40 bg-danger/10 text-danger">
            <Icon name="alert" :size="24" />
        </div>
        <h3 class="font-display text-base font-semibold text-fg">{{ title ?? t('ui.error.title') }}</h3>
        <p class="max-w-xs text-sm text-muted">{{ message ?? t('ui.error.message') }}</p>
        <Button variant="secondary" size="sm" :loading="retrying" @click="emit('retry')">
            <Icon name="refresh" :size="14" />
            {{ retryLabel ?? t('ui.error.retry') }}
        </Button>
    </div>
</template>
