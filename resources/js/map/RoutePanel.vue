<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { formatNumber } from '@/lib/format';
import type { RoutePlan } from '@/types';
import { MAX_ROUTE_STOPS, MIN_ROUTE_STOPS } from './constants';
import { canPlan, isSafeMapsUrl, mapsLinkLabel, planBlocker } from './route';
import type { RouteStop } from './types';

const props = defineProps<{
    stops: RouteStop[];
    plan: RoutePlan | null;
    planning: boolean;
    error: string;
    /** Map clicks add/remove stops instead of opening the drawer. */
    picking: boolean;
}>();

const emit = defineEmits<{
    'update:picking': [value: boolean];
    remove: [id: number];
    move: [from: number, to: number];
    focus: [stop: RouteStop];
    plan: [];
    clear: [];
    close: [];
}>();

const blocker = computed(() => planBlocker(props.stops));
const legKm = computed(() => new Map((props.plan?.stops ?? []).map((s) => [s.id, s.leg_km])));
const safeUrls = computed(() => (props.plan?.google_maps_urls ?? []).filter(isSafeMapsUrl));
const label = (s: RouteStop) => s.name ?? `Prospek #${s.id}`;
</script>

<template>
    <section class="glass flex max-h-[calc(100dvh-15rem)] w-[min(22rem,calc(100vw-1.5rem))] flex-col rounded-panel text-fg" aria-labelledby="route-title" data-testid="route-panel">
        <header class="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
            <h2 id="route-title" class="flex items-center gap-2 font-display text-sm font-semibold">
                <Icon name="navigation" :size="16" /> Rute kunjungan
                <span class="num rounded-full bg-fg/10 px-1.5 text-2xs" data-testid="route-count" :aria-label="`${stops.length} prospek dipilih`">{{ stops.length }}/{{ MAX_ROUTE_STOPS }}</span>
            </h2>
            <Button size="sm" variant="ghost" icon-only aria-label="Tutup panel rute" @click="emit('close')"><Icon name="close" :size="16" /></Button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <label class="mb-3 flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" :checked="picking" data-testid="route-pick-toggle" @change="emit('update:picking', ($event.target as HTMLInputElement).checked)" />
                Pilih prospek dengan klik di peta
            </label>

            <p v-if="stops.length === 0" class="rounded-chip border border-dashed border-line p-3 text-sm text-muted" data-testid="route-empty">
                Belum ada prospek. Aktifkan pilihan di atas lalu klik titik di peta, atau tekan "Tambah ke rute" di laci prospek (pilih {{ MIN_ROUTE_STOPS }}–{{ MAX_ROUTE_STOPS }}).
            </p>

            <ol v-else class="grid gap-1.5" data-testid="route-stops">
                <li v-for="(s, i) in stops" :key="s.id" class="flex items-center gap-2 rounded-chip bg-fg/5 px-2 py-1.5 text-sm" data-testid="route-stop">
                    <span class="num grid h-6 w-6 shrink-0 place-items-center rounded-full bg-focus text-xs font-semibold text-ink" aria-hidden="true">{{ i + 1 }}</span>
                    <button type="button" class="min-w-0 flex-1 cursor-pointer truncate text-left hover:underline" :aria-label="`Lihat ${label(s)} di peta`" @click="emit('focus', s)">{{ label(s) }}</button>
                    <span v-if="plan && (legKm.get(s.id) ?? 0) > 0" class="num shrink-0 text-2xs text-muted">+{{ formatNumber(legKm.get(s.id) ?? 0, 1) }} km</span>
                    <div class="flex shrink-0 items-center">
                        <button type="button" class="grid h-7 w-6 cursor-pointer place-items-center text-muted hover:text-fg disabled:opacity-30" :disabled="i === 0" :aria-label="`Naikkan ${label(s)}`" @click="emit('move', i, i - 1)">
                            <Icon name="chevron-down" :size="14" class="rotate-180" />
                        </button>
                        <button type="button" class="grid h-7 w-6 cursor-pointer place-items-center text-muted hover:text-fg disabled:opacity-30" :disabled="i === stops.length - 1" :aria-label="`Turunkan ${label(s)}`" @click="emit('move', i, i + 1)">
                            <Icon name="chevron-down" :size="14" />
                        </button>
                        <button type="button" class="grid h-7 w-7 cursor-pointer place-items-center text-muted hover:text-danger" :aria-label="`Hapus ${label(s)} dari rute`" @click="emit('remove', s.id)">
                            <Icon name="close" :size="14" />
                        </button>
                    </div>
                </li>
            </ol>
            <p v-if="stops.length > 1 && !plan" class="mt-2 text-2xs text-muted">Perhentian pertama menjadi titik awal.</p>

            <p v-if="error" class="mt-3 rounded-chip border border-danger/40 bg-danger/10 p-2 text-sm text-danger" role="alert" data-testid="route-error">{{ error }}</p>

            <div v-if="plan" class="mt-3 space-y-2 border-t border-line pt-3" data-testid="route-result">
                <p class="text-sm">
                    Total <strong class="num" data-testid="route-total">{{ formatNumber(plan.total_km, 1) }} km</strong>
                    <span class="text-muted"> · {{ plan.stops.length }} perhentian</span>
                </p>
                <p class="text-xs text-muted" data-testid="route-note">{{ plan.estimate_note }}</p>
                <div class="grid gap-1.5">
                    <a
                        v-for="(url, n) in safeUrls"
                        :key="url"
                        :href="url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex h-9 items-center justify-center gap-2 rounded-chip bg-fg px-3 text-sm font-medium text-ink hover:opacity-90"
                        data-testid="route-gmaps-link"
                    >
                        <Icon name="navigation" :size="14" /> {{ mapsLinkLabel(n, safeUrls.length) }}
                    </a>
                </div>
            </div>
        </div>

        <footer class="flex flex-wrap items-center gap-2 border-t border-line p-3">
            <Button size="sm" variant="primary" :disabled="!canPlan(stops)" :loading="planning" data-testid="route-plan" :title="blocker ?? undefined" @click="emit('plan')">
                {{ plan ? 'Hitung ulang' : 'Susun rute' }}
            </Button>
            <Button size="sm" variant="ghost" :disabled="stops.length === 0" data-testid="route-clear" @click="emit('clear')">Kosongkan</Button>
            <p v-if="blocker && stops.length > 0" class="basis-full text-2xs text-muted">{{ blocker }}</p>
        </footer>
    </section>
</template>
