<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useI18n } from '@/i18n';
import { formatNumber } from '@/lib/format';
import type { PageMeta } from './types';

defineProps<{ meta: PageMeta; busy?: boolean }>();
const emit = defineEmits<{ page: [page: number] }>();
const { t } = useI18n();
</script>

<template>
    <nav class="flex items-center justify-between gap-3 text-sm" :aria-label="t('master.pagination.label')" data-testid="pagination">
        <p class="num text-xs text-muted">
            {{ t('master.pagination.summary', { page: meta.current_page, last: meta.last_page, total: formatNumber(meta.total) }) }}
        </p>
        <div class="flex gap-2">
            <Button size="sm" :disabled="busy || meta.current_page <= 1" data-testid="page-prev" @click="emit('page', meta.current_page - 1)">
                <Icon name="chevron-down" :size="14" class="rotate-90" />
                {{ t('master.pagination.prev') }}
            </Button>
            <Button size="sm" :disabled="busy || meta.current_page >= meta.last_page" data-testid="page-next" @click="emit('page', meta.current_page + 1)">
                {{ t('master.pagination.next') }}
                <Icon name="chevron-right" :size="14" />
            </Button>
        </div>
    </nav>
</template>
