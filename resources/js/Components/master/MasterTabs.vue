<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ current: 'data-sources' | 'outlets' | 'review' | 'categories' | 'settings'; reviewCount?: number }>();

const TABS = [
    { key: 'data-sources', label: 'Sumber data', href: '/master/data-sources' },
    { key: 'outlets', label: 'Outlet', href: '/master/outlets' },
    { key: 'review', label: 'Verifikasi', href: '/master/outlets/review' },
    { key: 'categories', label: 'Kategori', href: '/master/categories' },
    { key: 'settings', label: 'Pengaturan', href: '/master/settings' },
] as const;
</script>

<template>
    <nav aria-label="Sub-halaman master" class="mb-4 overflow-x-auto">
        <ul class="glass m-0 inline-flex list-none gap-1 rounded-full p-1">
            <li v-for="t in TABS" :key="t.key">
                <Link
                    :href="t.href"
                    :data-testid="`master-tab-${t.key}`"
                    class="flex h-9 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 text-sm font-medium transition"
                    :class="current === t.key ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10 hover:text-fg'"
                    :aria-current="current === t.key ? 'page' : undefined"
                >
                    {{ t.label }}
                    <span v-if="t.key === 'review' && reviewCount" class="num rounded-full bg-hot px-1.5 text-2xs font-semibold text-on-hot">{{ reviewCount }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
