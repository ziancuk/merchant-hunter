<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import DataSourceRow from '@/Components/master/DataSourceRow.vue';
import MasterTabs from '@/Components/master/MasterTabs.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import type { DataSource } from '@/Components/master/types';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useI18n } from '@/i18n';

const props = defineProps<{ sources: DataSource[] }>();
const { t } = useI18n();
const list = ref<DataSource[]>([...(props.sources ?? [])]);

function replace(updated: DataSource): void {
    list.value = list.value.map((s) => (s.id === updated.id ? updated : s));
}
</script>

<template>
    <Head :title="t('master.data_source.head')" />
    <AppShell>
        <div class="mx-auto w-full max-w-5xl">
            <PageHeader :eyebrow="t('master.eyebrow')" :title="t('master.data_source.title')" :description="t('master.data_source.description')" />
            <MasterTabs current="data-sources" />

            <div v-if="list.length" class="grid gap-4" data-testid="ds-list">
                <DataSourceRow v-for="s in list" :key="s.id" :source="s" @updated="replace" />
            </div>
            <EmptyState v-else :title="t('master.data_source.empty_title')" :description="t('master.data_source.empty_description')" icon="database" />
        </div>
    </AppShell>
</template>
