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

const props = defineProps<{ queue: ReviewItem[] }>();
const { push } = useToasts();
const queue = ref<ReviewItem[]>([...(props.queue ?? [])]);
const count = computed(() => queue.value.length);

function onResolved(o: OutletRow): void {
    queue.value = queue.value.filter((q) => q.outlet.id !== o.id);
    push(`Lokasi ${o.code} ditetapkan.`, 'success', 3000);
}
</script>

<template>
    <Head title="Verifikasi outlet" />
    <AppShell>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader eyebrow="Master data" title="Verifikasi lokasi" description="Outlet yang hasil geocoding-nya meragukan. Pilih kandidat yang benar atau isi koordinat sendiri." />
            <MasterTabs current="review" :review-count="count" />

            <div v-if="queue.length" class="grid gap-4" data-testid="review-queue">
                <ReviewCard v-for="q in queue" :key="q.outlet.id" :item="q" @resolved="onResolved" />
            </div>
            <EmptyState v-else title="Antrean verifikasi kosong" description="Semua outlet sudah punya lokasi yang pasti." icon="check">
                <Link href="/master/outlets" class="text-sm text-focus hover:underline">Kembali ke daftar outlet</Link>
            </EmptyState>
        </div>
    </AppShell>
</template>
