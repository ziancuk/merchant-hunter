<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps<{ current: 'data-sources' | 'outlets' | 'review' | 'categories' | 'settings'; reviewCount?: number }>();

const { t } = useI18n();

const TABS = [
    { key: 'data-sources', label: 'data_sources', href: '/master/data-sources' },
    { key: 'outlets', label: 'outlets', href: '/master/outlets' },
    { key: 'review', label: 'review', href: '/master/outlets/review' },
    { key: 'categories', label: 'categories', href: '/master/categories' },
    { key: 'settings', label: 'settings', href: '/master/settings' },
] as const;
</script>

<template>
    <nav :aria-label="t('master.tabs.label')" class="mb-4 overflow-x-auto">
        <ul class="glass m-0 inline-flex list-none gap-1 rounded-full p-1">
            <li v-for="tab in TABS" :key="tab.key">
                <Link
                    :href="tab.href"
                    :data-testid="`master-tab-${tab.key}`"
                    class="flex h-9 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 text-sm font-medium transition"
                    :class="current === tab.key ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10 hover:text-fg'"
                    :aria-current="current === tab.key ? 'page' : undefined"
                >
                    {{ t(`master.tabs.${tab.label}`) }}
                    <span v-if="tab.key === 'review' && reviewCount" class="num rounded-full bg-hot px-1.5 text-2xs font-semibold text-on-hot">{{ reviewCount }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
