<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n';

export interface CartogramItem {
    id: number | string;
    /** Full name (tooltip, aria-label). */
    label: string;
    /** Short tile label (3 letters). */
    short: string;
    /** The encoded metric (colour intensity). */
    value: number;
    /** Extra lines shown in the tooltip, e.g. "Prospek: 310". */
    details?: string[];
}

const { t } = useI18n();
const props = withDefaults(
    defineProps<{
        items: CartogramItem[];
        /** Name of the metric, e.g. "Penetrasi". */
        metric: string;
        /** Formats `value` for tile caption/tooltip. */
        format: (n: number) => string;
        label: string;
        /** Tiles with level >= this get the hot outline (top tier). */
        highlightAbove?: number;
    }>(),
    { highlightAbove: 0.85 },
);

const emit = defineEmits<{ select: [item: CartogramItem] }>();

const max = computed(() => Math.max(0, ...props.items.map((i) => i.value)));
const level = (v: number) => (max.value > 0 ? v / max.value : 0);
// Intensity ramp tops out at 50% teal so foreground text keeps >= 4.5:1 in both themes (DESIGN.md §3).
const tileStyle = (v: number) => ({
    background: `color-mix(in srgb, var(--c-medium) ${(8 + level(v) * 42).toFixed(1)}%, var(--c-surface))`,
});

const root = ref<HTMLElement | null>(null);
const active = ref<CartogramItem | null>(null);
const tip = ref({ left: 0, top: 0 });

function show(item: CartogramItem, e: Event): void {
    active.value = item;
    const el = e.currentTarget as HTMLElement;
    const host = root.value;
    if (!host) return;
    const half = 110;
    const x = el.offsetLeft + el.offsetWidth / 2;
    tip.value = { left: Math.max(half, Math.min(host.clientWidth - half, x)), top: el.offsetTop };
}
function hide(): void {
    active.value = null;
}
</script>

<template>
    <div ref="root" class="relative" data-testid="cartogram" @mouseleave="hide" @keydown.esc="hide">
        <ul class="m-0 grid list-none gap-1.5 p-0" style="grid-template-columns: repeat(auto-fill, minmax(62px, 1fr))" :aria-label="label">
            <li v-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-0.5 rounded-chip border text-fg transition hover:brightness-125"
                    :class="level(item.value) >= highlightAbove && item.value > 0 ? 'border-hot' : 'border-line'"
                    :style="tileStyle(item.value)"
                    :data-testid="`cartogram-tile-${item.id}`"
                    @mouseenter="show(item, $event)"
                    @focus="show(item, $event)"
                    @blur="hide"
                    @click="emit('select', item)"
                >
                    <span class="font-display text-sm font-semibold leading-none tracking-tight">{{ item.short }}</span>
                    <span class="num text-2xs leading-none text-fg/80">{{ format(item.value) }}</span>
                    <span class="sr-only">{{ ` ${item.label}: ${metric} ${format(item.value)}${item.details?.length ? ', ' + item.details.join(', ') : ''}` }}</span>
                </button>
            </li>
        </ul>

        <div
            v-if="active"
            role="tooltip"
            class="glass pointer-events-none absolute z-10 w-[220px] -translate-x-1/2 -translate-y-full rounded-chip px-3 py-2 text-xs shadow-pop"
            :style="{ left: `${tip.left}px`, top: `${tip.top - 6}px` }"
            data-testid="cartogram-tooltip"
        >
            <p class="font-display text-sm font-semibold">{{ active.label }}</p>
            <p class="num text-muted">{{ metric }}: <span class="text-fg">{{ format(active.value) }}</span></p>
            <p v-for="d in active.details" :key="d" class="num text-muted">{{ d }}</p>
        </div>

        <div class="mt-3 flex items-center gap-2 text-2xs text-muted" aria-hidden="true">
            <span class="num">0</span>
            <span class="h-2 w-28 rounded-full border border-line" style="background: linear-gradient(90deg, color-mix(in srgb, var(--c-medium) 8%, var(--c-surface)), color-mix(in srgb, var(--c-medium) 50%, var(--c-surface)))" />
            <span class="num">{{ format(max) }}</span>
            <span class="ml-2 inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm border border-hot" />{{ t('ui.chart.top') }}</span>
        </div>

        <table class="sr-only" data-testid="cartogram-table">
            <caption>{{ label }}</caption>
            <thead><tr><th>{{ t('ui.chart.region') }}</th><th>{{ metric }}</th></tr></thead>
            <tbody>
                <tr v-for="item in items" :key="item.id"><td>{{ item.label }}</td><td>{{ format(item.value) }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
