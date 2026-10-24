<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { formatNumber } from '@/lib/format';
import type { PageMeta } from './types';

defineProps<{ meta: PageMeta; busy?: boolean }>();
const emit = defineEmits<{ page: [page: number] }>();
</script>

<template>
    <nav class="flex items-center justify-between gap-3 text-sm" aria-label="Paginasi" data-testid="pagination">
        <p class="num text-xs text-muted">
            Halaman {{ meta.current_page }} dari {{ meta.last_page }} · {{ formatNumber(meta.total) }} data
        </p>
        <div class="flex gap-2">
            <Button size="sm" :disabled="busy || meta.current_page <= 1" data-testid="page-prev" @click="emit('page', meta.current_page - 1)">
                <Icon name="chevron-down" :size="14" class="rotate-90" />
                Sebelumnya
            </Button>
            <Button size="sm" :disabled="busy || meta.current_page >= meta.last_page" data-testid="page-next" @click="emit('page', meta.current_page + 1)">
                Berikutnya
                <Icon name="chevron-right" :size="14" />
            </Button>
        </div>
    </nav>
</template>
