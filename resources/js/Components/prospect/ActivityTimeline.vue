<script setup lang="ts">
import { computed } from 'vue';
import { ACTIVITY_LABEL, STATUS_LABEL } from '@/lib/labels';
import { formatDateTime, relativeTime } from '@/lib/format';
import type { Activity } from '@/types';

const props = defineProps<{ activities: Activity[] }>();

const sorted = computed(() => [...props.activities].sort((a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id));
</script>

<template>
    <ol v-if="sorted.length" class="relative space-y-4 border-l border-line pl-4" aria-label="Riwayat aktivitas">
        <li v-for="a in sorted" :key="a.id" class="relative" data-testid="activity">
            <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-ink" :class="a.type === 'contacted' ? 'bg-st-won' : a.type === 'rejected' || a.type === 'opt_out' ? 'bg-danger' : 'bg-muted'" aria-hidden="true" />
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-sm font-medium text-fg">{{ ACTIVITY_LABEL[a.type] }}</span>
                <time :datetime="a.created_at" :title="formatDateTime(a.created_at)" class="num shrink-0 text-2xs text-muted">{{ relativeTime(a.created_at) }}</time>
            </div>
            <p v-if="a.from_status || a.to_status" class="text-xs text-muted">
                {{ a.from_status ? STATUS_LABEL[a.from_status] : '–' }} → {{ a.to_status ? STATUS_LABEL[a.to_status] : '–' }}
            </p>
            <p v-if="a.body" class="whitespace-pre-line break-words text-sm text-fg/90">{{ a.body }}</p>
            <p v-if="a.user" class="text-2xs text-muted">oleh {{ a.user.name }}</p>
        </li>
    </ol>
    <p v-else class="text-sm text-muted">Belum ada aktivitas.</p>
</template>
