<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n';
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
const { t } = useI18n();
const label = (s: RouteStop) => s.name ?? t('map.route.stop_fallback', { id: s.id });
</script>

<template>
    <section class="glass flex max-h-[calc(100dvh-15rem)] w-[min(22rem,calc(100vw-1.5rem))] flex-col rounded-panel text-fg" aria-labelledby="route-title" data-testid="route-panel">
        <header class="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
            <h2 id="route-title" class="flex items-center gap-2 font-display text-sm font-semibold">
                <Icon name="navigation" :size="16" /> {{ t('map.route.title') }}
                <span class="num rounded-full bg-fg/10 px-1.5 text-2xs" data-testid="route-count" :aria-label="t('map.route.selected_aria', { n: stops.length })">{{ stops.length }}/{{ MAX_ROUTE_STOPS }}</span>
            </h2>
            <Button size="sm" variant="ghost" icon-only :aria-label="t('map.route.close')" @click="emit('close')"><Icon name="close" :size="16" /></Button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <label class="mb-3 flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" :checked="picking" data-testid="route-pick-toggle" @change="emit('update:picking', ($event.target as HTMLInputElement).checked)" />
                {{ t('map.route.pick') }}
            </label>

            <p v-if="stops.length === 0" class="rounded-chip border border-dashed border-line p-3 text-sm text-muted" data-testid="route-empty">
                {{ t('map.route.empty', { min: MIN_ROUTE_STOPS, max: MAX_ROUTE_STOPS }) }}
            </p>

            <ol v-else class="grid gap-1.5" data-testid="route-stops">
                <li v-for="(s, i) in stops" :key="s.id" class="flex items-center gap-2 rounded-chip bg-fg/5 px-2 py-1.5 text-sm" data-testid="route-stop">
                    <span class="num grid h-6 w-6 shrink-0 place-items-center rounded-full bg-focus text-xs font-semibold text-ink" aria-hidden="true">{{ i + 1 }}</span>
                    <button type="button" class="min-w-0 flex-1 cursor-pointer truncate text-left hover:underline" :aria-label="t('map.route.view_stop', { name: label(s) })" @click="emit('focus', s)">{{ label(s) }}</button>
                    <span v-if="plan && (legKm.get(s.id) ?? 0) > 0" class="num shrink-0 text-2xs text-muted">+{{ formatNumber(legKm.get(s.id) ?? 0, 1) }} km</span>
                    <div class="flex shrink-0 items-center">
                        <button type="button" class="grid h-7 w-6 cursor-pointer place-items-center text-muted hover:text-fg disabled:opacity-30" :disabled="i === 0" :aria-label="t('map.route.move_up', { name: label(s) })" @click="emit('move', i, i - 1)">
                            <Icon name="chevron-down" :size="14" class="rotate-180" />
                        </button>
                        <button type="button" class="grid h-7 w-6 cursor-pointer place-items-center text-muted hover:text-fg disabled:opacity-30" :disabled="i === stops.length - 1" :aria-label="t('map.route.move_down', { name: label(s) })" @click="emit('move', i, i + 1)">
                            <Icon name="chevron-down" :size="14" />
                        </button>
                        <button type="button" class="grid h-7 w-7 cursor-pointer place-items-center text-muted hover:text-danger" :aria-label="t('map.route.remove', { name: label(s) })" @click="emit('remove', s.id)">
                            <Icon name="close" :size="14" />
                        </button>
                    </div>
                </li>
            </ol>
            <p v-if="stops.length > 1 && !plan" class="mt-2 text-2xs text-muted">{{ t('map.route.first_stop') }}</p>

            <p v-if="error" class="mt-3 rounded-chip border border-danger/40 bg-danger/10 p-2 text-sm text-danger" role="alert" data-testid="route-error">{{ error }}</p>

            <div v-if="plan" class="mt-3 space-y-2 border-t border-line pt-3" data-testid="route-result">
                <p class="text-sm">
                    {{ t('map.route.total') }} <strong class="num" data-testid="route-total">{{ formatNumber(plan.total_km, 1) }} km</strong>
                    <span class="text-muted"> · {{ t('map.route.stops', { n: plan.stops.length }) }}</span>
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
                {{ plan ? t('map.route.replan') : t('map.route.plan') }}
            </Button>
            <Button size="sm" variant="ghost" :disabled="stops.length === 0" data-testid="route-clear" @click="emit('clear')">{{ t('map.route.clear') }}</Button>
            <p v-if="blocker && stops.length > 0" class="basis-full text-2xs text-muted">{{ blocker }}</p>
        </footer>
    </section>
</template>
