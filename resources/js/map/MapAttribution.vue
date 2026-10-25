<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import { useDismissable } from '@/composables/useDismissable';
import { useI18n } from '@/i18n';
import { ATTRIBUTION } from './constants';

// Compact attribution: a small "i" button that opens the credits on demand. The OpenStreetMap/OpenFreeMap licences
// require the credit to stay reachable, so it is collapsed (as MapLibre's own compact control does), not removed.
const { t } = useI18n();
const open = ref(false);
const root = ref<HTMLElement | null>(null);
useDismissable(root, () => (open.value = false), { enabled: open });
</script>

<template>
    <section ref="root" class="relative" :aria-label="t('map.attribution.label')">
        <button
            type="button"
            class="glass grid h-7 w-7 cursor-pointer place-items-center rounded-full text-muted transition hover:text-fg"
            :aria-expanded="open"
            :aria-label="t('map.attribution.toggle')"
            data-testid="map-attribution-toggle"
            @click="open = !open"
        >
            <Icon name="info" :size="14" />
        </button>
        <p
            v-if="open"
            class="glass absolute bottom-9 left-0 w-max max-w-[min(20rem,calc(100vw-1.5rem))] rounded-chip px-2.5 py-1.5 text-2xs leading-snug text-muted"
            data-testid="map-attribution"
        >
            {{ ATTRIBUTION.text }} ·
            <template v-for="(l, i) in ATTRIBUTION.links" :key="l.href">
                <a :href="l.href" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2 hover:text-fg">{{ l.label }}</a
                ><span v-if="i < ATTRIBUTION.links.length - 1">, </span>
            </template>
        </p>
    </section>
</template>
