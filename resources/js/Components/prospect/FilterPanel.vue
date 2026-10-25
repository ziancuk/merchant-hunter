<script lang="ts">
import type { PipelineStatus } from '@/types';
export interface FilterOptions {
    provinces: { id: number; name: string }[];
    cities: string[];
    categories: { id: number; key: string; name: string }[];
    pipeline_statuses: PipelineStatus[];
}
</script>

<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { countActiveFilters, filterToQuery } from '@/composables/useFilters';
import { STATUS_CLASSES } from '@/lib/colors';
import { STATUS_LABEL } from '@/lib/labels';
import type { ProspectFilter } from '@/types';

const props = withDefaults(defineProps<{ modelValue: ProspectFilter; options: FilterOptions; collapsible?: boolean }>(), { collapsible: true });
const emit = defineEmits<{ 'update:modelValue': [value: ProspectFilter]; query: [query: string] }>();

const expanded = ref(true);
const active = computed(() => countActiveFilters(props.modelValue));

function commit(patch: Partial<Record<keyof ProspectFilter, unknown>>): void {
    const next: Record<string, unknown> = { ...props.modelValue, ...patch };
    for (const k of Object.keys(next)) {
        const v = next[k];
        if (v === undefined || v === null || v === '' || (typeof v === 'number' && Number.isNaN(v)) || (Array.isArray(v) && v.length === 0)) delete next[k];
    }
    const filter = next as ProspectFilter;
    emit('update:modelValue', filter);
    emit('query', filterToQuery(filter));
}

function reset(): void {
    emit('update:modelValue', {});
    emit('query', '');
}

const num = (e: Event): number | undefined => {
    const v = (e.target as HTMLInputElement | HTMLSelectElement).value;
    return v === '' ? undefined : Number(v);
};

function toggleIn<T>(list: T[] | undefined, item: T): T[] {
    const cur = list ?? [];
    return cur.includes(item) ? cur.filter((x) => x !== item) : [...cur, item];
}

const field = 'h-9 w-full rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg';
const chip = 'cursor-pointer rounded-full border px-2.5 py-1 text-xs transition';
</script>

<template>
    <section class="glass w-full rounded-panel text-fg" aria-label="Filter prospek" data-testid="filter-panel">
        <header class="flex items-center justify-between gap-2 px-3 py-2">
            <button
                v-if="collapsible"
                type="button"
                class="flex cursor-pointer items-center gap-2 font-display text-sm font-semibold"
                :aria-expanded="expanded"
                aria-controls="filter-body"
                @click="expanded = !expanded"
            >
                <Icon name="filter" :size="16" /> Filter
                <span v-if="active" class="num rounded-full bg-hot px-1.5 text-2xs text-on-hot" :aria-label="`${active} filter aktif`">{{ active }}</span>
                <Icon name="chevron-down" :size="14" class="transition-transform" :class="expanded ? 'rotate-180' : ''" />
            </button>
            <h2 v-else class="flex items-center gap-2 font-display text-sm font-semibold"><Icon name="filter" :size="16" /> Filter</h2>
            <Button size="sm" variant="ghost" :disabled="!active" data-testid="filter-reset" @click="reset">Atur ulang</Button>
        </header>

        <div v-show="expanded || !collapsible" id="filter-body" class="grid gap-3 border-t border-line p-3 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <label class="space-y-1">
                    <span class="hud-label">Provinsi</span>
                    <select :class="field" :value="modelValue.province_id ?? ''" data-testid="f-province" @change="commit({ province_id: num($event) })">
                        <option value="">Semua</option>
                        <option v-for="p in options.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="hud-label">Kota</span>
                    <select :class="field" :value="modelValue.city ?? ''" data-testid="f-city" @change="commit({ city: ($event.target as HTMLSelectElement).value })">
                        <option value="">Semua</option>
                        <option v-for="c in options.cities" :key="c" :value="c">{{ c }}</option>
                    </select>
                </label>
            </div>

            <fieldset class="space-y-1">
                <legend class="hud-label">Kategori</legend>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="c in options.categories"
                        :key="c.id"
                        type="button"
                        :class="[chip, modelValue.category_id?.includes(c.id) ? 'border-focus bg-focus/15 text-fg' : 'border-line text-muted hover:text-fg']"
                        :aria-pressed="!!modelValue.category_id?.includes(c.id)"
                        :data-testid="`f-cat-${c.id}`"
                        @click="commit({ category_id: toggleIn(modelValue.category_id, c.id) })"
                    >
                        {{ c.name }}
                    </button>
                </div>
            </fieldset>

            <div class="grid grid-cols-3 gap-2">
                <label class="space-y-1">
                    <span class="hud-label">Skor min</span>
                    <input type="number" min="0" max="100" :class="[field, 'num']" :value="modelValue.score_min ?? ''" data-testid="f-score-min" @change="commit({ score_min: num($event) })" />
                </label>
                <label class="space-y-1">
                    <span class="hud-label">Skor maks</span>
                    <input type="number" min="0" max="100" :class="[field, 'num']" :value="modelValue.score_max ?? ''" data-testid="f-score-max" @change="commit({ score_max: num($event) })" />
                </label>
                <label class="space-y-1">
                    <span class="hud-label">Rating min</span>
                    <select :class="[field, 'num']" :value="modelValue.rating_min ?? ''" data-testid="f-rating" @change="commit({ rating_min: num($event) })">
                        <option value="">Semua</option>
                        <option v-for="r in [3, 3.5, 4, 4.5]" :key="r" :value="r">≥ {{ r }}</option>
                    </select>
                </label>
            </div>

            <fieldset class="space-y-1">
                <legend class="hud-label">Status pipeline</legend>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="s in options.pipeline_statuses"
                        :key="s"
                        type="button"
                        :class="[chip, modelValue.pipeline_status?.includes(s) ? [STATUS_CLASSES[s].soft, STATUS_CLASSES[s].text, STATUS_CLASSES[s].border] : 'border-line text-muted hover:text-fg']"
                        :aria-pressed="!!modelValue.pipeline_status?.includes(s)"
                        :data-testid="`f-status-${s}`"
                        @click="commit({ pipeline_status: toggleIn(modelValue.pipeline_status, s) })"
                    >
                        {{ STATUS_LABEL[s] }}
                    </button>
                </div>
            </fieldset>

            <label class="space-y-1">
                <span class="hud-label">Jarak maks. ke outlet</span>
                <select :class="field" :value="modelValue.max_distance_m ?? ''" data-testid="f-distance" @change="commit({ max_distance_m: num($event) })">
                    <option value="">Tanpa batas</option>
                    <option v-for="d in [500, 1000, 2000, 5000, 10000]" :key="d" :value="d">≤ {{ d >= 1000 ? `${d / 1000} km` : `${d} m` }}</option>
                </select>
            </label>

            <div class="grid gap-1.5">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" :checked="modelValue.has_phone === 1" data-testid="f-phone" @change="commit({ has_phone: ($event.target as HTMLInputElement).checked ? 1 : undefined })" />
                    Punya nomor telepon
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" :checked="modelValue.new_open_only === 1" data-testid="f-newopen" @change="commit({ new_open_only: ($event.target as HTMLInputElement).checked ? 1 : undefined })" />
                    Hanya yang baru buka
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" :checked="modelValue.include_closed === 1" data-testid="f-closed" @change="commit({ include_closed: ($event.target as HTMLInputElement).checked ? 1 : undefined })" />
                    Tampilkan yang sudah tutup
                </label>
            </div>
        </div>
    </section>
</template>
