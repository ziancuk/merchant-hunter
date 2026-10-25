<script setup lang="ts">
import { computed } from 'vue';
import Drawer from './Drawer.vue';
import { listShortcuts, shortcutHelpOpen, shortcutText } from '@/composables/useShortcuts';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const grouped = computed(() => {
    const map = new Map<string, { keys: string; description: string }[]>();
    for (const s of listShortcuts()) {
        const g = s.group ? shortcutText(s.group) : t('ui.shortcuts.general');
        if (!map.has(g)) map.set(g, []);
        map.get(g)!.push({ keys: s.keys, description: shortcutText(s.description) });
    }
    return [...map.entries()];
});

const pretty = (keys: string) => keys.replace(/mod/i, 'Ctrl/⌘').split('+').map((k) => (k.length === 1 ? k.toUpperCase() : k.charAt(0).toUpperCase() + k.slice(1)));
</script>

<template>
    <Drawer v-model="shortcutHelpOpen" :title="t('ui.shortcuts.title')" :description="t('ui.shortcuts.description')">
        <div class="space-y-5 p-4">
            <section v-for="[group, list] in grouped" :key="group">
                <h3 class="hud-label mb-2">{{ group }}</h3>
                <ul class="space-y-1.5">
                    <li v-for="s in list" :key="s.keys" class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ s.description }}</span>
                        <span class="flex gap-1">
                            <kbd v-for="k in pretty(s.keys)" :key="k" class="num rounded border border-line px-1.5 py-0.5 text-xs text-muted">{{ k }}</kbd>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </Drawer>
</template>
