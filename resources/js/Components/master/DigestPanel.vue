<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { scoreBand } from '@/lib/colors';
import { BAND_LABEL } from '@/lib/labels';
import Panel from './Panel.vue';
import type { Digest, DigestRow } from './types';

const props = defineProps<{ digest: Digest }>();
const { t } = useI18n();

type TabKey = 'new_open' | 'hot' | 'follow_up';
const TABS = computed<{ key: TabKey; label: string; empty: string }[]>(() => [
    { key: 'new_open', label: t('dashboard.digest.tab_new_open'), empty: t('dashboard.digest.empty_new_open') },
    { key: 'hot', label: BAND_LABEL.hot, empty: t('dashboard.digest.empty_hot', { band: BAND_LABEL.hot }) },
    { key: 'follow_up', label: t('dashboard.digest.tab_follow_up'), empty: t('dashboard.digest.empty_follow_up') },
]);

const current = ref<Digest>(props.digest);
const tab = ref<TabKey>('new_open');
const date = ref(props.digest?.date ?? '');
const loading = ref(false);
const error = ref<string | null>(null);

const rows = computed<DigestRow[]>(() => current.value?.[tab.value] ?? []);
const counts = computed(() => ({
    new_open: current.value?.new_open?.length ?? 0,
    hot: current.value?.hot?.length ?? 0,
    follow_up: current.value?.follow_up?.length ?? 0,
}));

async function load(): Promise<void> {
    if (!date.value) return;
    loading.value = true;
    error.value = null;
    try {
        const { data } = await api.get<Digest>('/api/digest', { params: { date: date.value } });
        current.value = data;
    } catch (e) {
        error.value = normalizeError(e).message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Panel :title="t('dashboard.digest.title')" :hint="t('dashboard.digest.hint', { date: formatDate(current?.date) })">
        <template #actions>
            <label class="sr-only" for="digest-date">{{ t('dashboard.digest.date_label') }}</label>
            <input
                id="digest-date"
                v-model="date"
                type="date"
                data-testid="digest-date"
                class="num h-8 rounded-chip border border-line bg-surface/60 px-2 text-xs text-fg"
                @change="load"
            />
        </template>

        <div role="tablist" :aria-label="t('dashboard.digest.tabs_label')" class="flex flex-wrap gap-1">
            <button
                v-for="tb in TABS"
                :id="`digest-tab-${tb.key}`"
                :key="tb.key"
                type="button"
                role="tab"
                :aria-selected="tab === tb.key"
                :aria-controls="`digest-panel`"
                :data-testid="`digest-tab-${tb.key}`"
                class="flex h-8 cursor-pointer items-center gap-1.5 rounded-full px-3 text-xs font-medium transition"
                :class="tab === tb.key ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10'"
                @click="tab = tb.key"
            >
                {{ tb.label }}
                <span class="num opacity-70">{{ counts[tb.key] }}</span>
            </button>
        </div>

        <div id="digest-panel" role="tabpanel" :aria-labelledby="`digest-tab-${tab}`" class="min-h-[8rem]">
            <Skeleton v-if="loading" :lines="4" />
            <ErrorState v-else-if="error" :message="error" @retry="load" />
            <EmptyState v-else-if="rows.length === 0" :title="TABS.find((tb) => tb.key === tab)?.empty ?? t('dashboard.digest.empty_fallback')" icon="note" />
            <ul v-else class="m-0 flex list-none flex-col p-0" data-testid="digest-list">
                <li v-for="r in rows" :key="r.id" class="border-b border-line/60 last:border-0">
                    <Link :href="`/?prospect=${r.id}`" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-focus" data-testid="digest-row">
                        <span class="min-w-0 truncate">{{ r.name }}</span>
                        <Badge :tone="scoreBand(r.score)" class="num">{{ r.score }}</Badge>
                    </Link>
                </li>
            </ul>
        </div>
    </Panel>
</template>
