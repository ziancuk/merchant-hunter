<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import MasterTabs from '@/Components/master/MasterTabs.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import ReviewCard from '@/Components/master/ReviewCard.vue';
import type { OutletRow, ReviewItem } from '@/Components/master/types';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useToasts } from '@/composables/useToasts';
import { useI18n } from '@/i18n';

const props = defineProps<{ queue: ReviewItem[] }>();
const { push } = useToasts();
const { t } = useI18n();
const queue = ref<ReviewItem[]>([...(props.queue ?? [])]);
const count = computed(() => queue.value.length);

function onResolved(o: OutletRow): void {
    queue.value = queue.value.filter((q) => q.outlet.id !== o.id);
    push(t('master.review_page.toast_resolved', { code: o.code }), 'success', 3000);
}
</script>

<template>
    <Head :title="t('master.review_page.head')" />
    <AppShell>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader :eyebrow="t('master.eyebrow')" :title="t('master.review_page.title')" :description="t('master.review_page.description')" />
            <MasterTabs current="review" :review-count="count" />

            <div v-if="queue.length" class="grid gap-4" data-testid="review-queue">
                <ReviewCard v-for="q in queue" :key="q.outlet.id" :item="q" @resolved="onResolved" />
            </div>
            <EmptyState v-else :title="t('master.review_page.empty_title')" :description="t('master.review_page.empty_description')" icon="check">
                <Link href="/master/outlets" class="text-sm text-focus hover:underline">{{ t('master.review_page.back') }}</Link>
            </EmptyState>
        </div>
    </AppShell>
</template>
