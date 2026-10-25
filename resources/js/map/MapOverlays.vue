<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import type { MapMode } from '@/types';
import type { TooltipContent } from './useMapTooltip';

/** Status layers floating over the map canvas: loading, errors, banners, tooltip, empty state and intro caption. */
defineProps<{
    ready: boolean;
    mapError: string;
    basemapFailed: boolean;
    tooltip: TooltipContent | null;
    tooltipStyle: Record<string, string | undefined>;
    loadError: string;
    loading: boolean;
    isEmpty: boolean;
    introActive: boolean;
    mode: MapMode;
    activeCount: number;
    liveSummary: string;
}>();

defineEmits<{ reload: []; retry: []; 'reset-filter': []; 'skip-intro': [] }>();
</script>

<template>
    <p class="sr-only" role="status" aria-live="polite">{{ liveSummary }}</p>

    <!-- Initial loading skeleton -->
    <div v-if="!ready && !mapError" class="absolute inset-0 grid place-items-center" data-testid="map-loading">
        <GlassPanel class="flex items-center gap-3" role="status">
            <span class="h-3 w-3 animate-pulse-ring rounded-full bg-hot" aria-hidden="true" />
            <span class="text-sm">Memuat peta…</span>
        </GlassPanel>
    </div>

    <!-- WebGL / module failure -->
    <div v-if="mapError" class="absolute inset-0 grid place-items-center p-4" data-testid="map-error">
        <GlassPanel>
            <ErrorState title="Peta tidak dapat ditampilkan" :message="`${mapError} Pastikan browser mendukung WebGL2, lalu muat ulang halaman.`" retry-label="Muat ulang" @retry="$emit('reload')" />
        </GlassPanel>
    </div>

    <!-- Basemap fallback banner -->
    <div v-if="basemapFailed" class="absolute left-1/2 top-[4.5rem] z-10 -translate-x-1/2 px-3" data-testid="basemap-banner">
        <GlassPanel class="flex items-center gap-2 !px-3 !py-2 text-sm" role="status">
            <Icon name="alert" :size="16" class="text-warm" />
            Basemap tidak tersedia. Menampilkan peta dasar lokal.
        </GlassPanel>
    </div>

    <!-- Tooltip -->
    <div v-if="tooltip" class="pointer-events-none absolute z-10 max-w-[16rem]" :style="tooltipStyle" role="tooltip" data-testid="map-tooltip">
        <GlassPanel class="!px-3 !py-2" rounded="chip">
            <p class="text-sm font-medium leading-snug">{{ tooltip.title }}</p>
            <p v-for="l in tooltip.lines" :key="l" class="text-xs text-muted">{{ l }}</p>
        </GlassPanel>
    </div>

    <!-- Data error -->
    <div v-if="loadError && ready" class="absolute left-1/2 top-[4.5rem] z-10 -translate-x-1/2 px-3" data-testid="map-load-error">
        <GlassPanel :padded="false">
            <ErrorState title="Gagal memuat data peta" :message="loadError" :retrying="loading" @retry="$emit('retry')" />
        </GlassPanel>
    </div>

    <!-- Empty -->
    <div v-if="isEmpty" class="absolute inset-x-0 bottom-28 z-10 flex justify-center px-3 md:bottom-16" data-testid="map-empty">
        <GlassPanel :padded="false" class="flex items-center gap-3 !px-4 !py-3" role="status">
            <Icon name="search" :size="18" class="text-muted" />
            <div class="text-sm">
                <p class="font-medium">Tidak ada {{ mode === 'whitespace' ? 'area white space' : 'prospek' }} di tampilan ini</p>
                <p class="text-xs text-muted">Geser atau perkecil peta{{ activeCount ? ', atau longgarkan filter' : '' }}.</p>
            </div>
            <Button v-if="activeCount" size="sm" variant="secondary" @click="$emit('reset-filter')">Atur ulang filter</Button>
        </GlassPanel>
    </div>

    <!-- Intro caption + skip -->
    <div v-if="introActive" class="absolute inset-x-0 bottom-28 z-10 flex flex-col items-center gap-3 px-4 text-center md:bottom-14" data-testid="map-intro">
        <p class="font-display text-xl font-semibold tracking-tight text-fg drop-shadow md:text-3xl">Memindai peluang merchant di seluruh Indonesia</p>
        <Button variant="secondary" size="md" data-testid="skip-intro" @click="$emit('skip-intro')">Lewati intro</Button>
    </div>
</template>
