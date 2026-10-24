<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import CategoryEditor from '@/Components/master/CategoryEditor.vue';
import MasterTabs from '@/Components/master/MasterTabs.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import type { CategoryRow } from '@/Components/master/types';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToasts } from '@/composables/useToasts';

const props = defineProps<{ categories: CategoryRow[] }>();
const { push } = useToasts();
const list = ref<CategoryRow[]>([...(props.categories ?? [])]);
const adding = ref(false);

function onSaved(c: CategoryRow, created: boolean): void {
    if (created) {
        list.value = [...list.value, c];
        adding.value = false;
        push(`Kategori ${c.name} ditambahkan.`, 'success', 3000);
    } else {
        list.value = list.value.map((x) => (x.id === c.id ? c : x));
        push(`Kategori ${c.name} disimpan.`, 'success', 3000);
    }
}
</script>

<template>
    <Head title="Kategori" />
    <AppShell>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader eyebrow="Master data" title="Kategori" description="Bobot kategori memengaruhi skor prospek. Template WhatsApp dipakai saat menghubungi.">
                <Button v-if="!adding" variant="primary" size="sm" data-testid="category-add" @click="adding = true">
                    <Icon name="sparkle" :size="14" />
                    Tambah kategori
                </Button>
            </PageHeader>
            <MasterTabs current="categories" />

            <div class="grid gap-4" data-testid="category-list">
                <CategoryEditor v-if="adding" :category="null" @saved="onSaved" @cancel="adding = false" />
                <CategoryEditor v-for="c in list" :key="c.id" :category="c" @saved="onSaved" />
                <EmptyState v-if="list.length === 0 && !adding" title="Belum ada kategori" description="Tambahkan kategori pertama untuk mulai memberi skor." icon="store" />
            </div>
        </div>
    </AppShell>
</template>
