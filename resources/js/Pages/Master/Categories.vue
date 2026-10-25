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
import { useI18n } from '@/i18n';

const props = defineProps<{ categories: CategoryRow[] }>();
const { push } = useToasts();
const { t } = useI18n();
const list = ref<CategoryRow[]>([...(props.categories ?? [])]);
const adding = ref(false);

function onSaved(c: CategoryRow, created: boolean): void {
    if (created) {
        list.value = [...list.value, c];
        adding.value = false;
        push(t('master.categories.toast_added', { name: c.name }), 'success', 3000);
    } else {
        list.value = list.value.map((x) => (x.id === c.id ? c : x));
        push(t('master.categories.toast_saved', { name: c.name }), 'success', 3000);
    }
}
</script>

<template>
    <Head :title="t('master.categories.head')" />
    <AppShell>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader :eyebrow="t('master.eyebrow')" :title="t('master.categories.title')" :description="t('master.categories.description')">
                <Button v-if="!adding" variant="primary" size="sm" data-testid="category-add" @click="adding = true">
                    <Icon name="sparkle" :size="14" />
                    {{ t('master.category.add') }}
                </Button>
            </PageHeader>
            <MasterTabs current="categories" />

            <div class="grid gap-4" data-testid="category-list">
                <CategoryEditor v-if="adding" :category="null" @saved="onSaved" @cancel="adding = false" />
                <CategoryEditor v-for="c in list" :key="c.id" :category="c" @saved="onSaved" />
                <EmptyState v-if="list.length === 0 && !adding" :title="t('master.categories.empty_title')" :description="t('master.categories.empty_description')" icon="store" />
            </div>
        </div>
    </AppShell>
</template>
