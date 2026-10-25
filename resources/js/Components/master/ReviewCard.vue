<script setup lang="ts">
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatCoordinates } from '@/lib/format';
import TextField from './TextField.vue';
import type { OutletRow, ReviewItem } from './types';

const props = defineProps<{ item: ReviewItem }>();
const { t } = useI18n();
const emit = defineEmits<{ resolved: [outlet: OutletRow] }>();

const lat = ref<number | null>(null);
const lng = ref<number | null>(null);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const errors = ref<Record<string, string[]>>({});

async function resolve(body: Record<string, unknown>, tag: string): Promise<void> {
    busy.value = tag;
    error.value = null;
    errors.value = {};
    try {
        const { data } = await api.post<OutletRow>(`/api/master/outlets/${props.item.outlet.id}/resolve`, body);
        emit('resolved', data);
    } catch (e) {
        const err = normalizeError(e);
        errors.value = err.errors;
        error.value = err.message;
    } finally {
        busy.value = null;
    }
}
const pick = (i: number) => resolve({ candidate_index: i }, `c${i}`);
const manual = () => resolve({ lat: lat.value, lng: lng.value }, 'manual');
const scoreTone = (s: number) => (s >= 80 ? 'success' : s >= 65 ? 'warm' : 'neutral');
</script>

<template>
    <GlassPanel as="article" :data-testid="`review-${item.outlet.id}`" class="grid gap-4">
        <header>
            <p class="num text-2xs text-muted">{{ item.outlet.code }}</p>
            <h2 class="font-display text-lg font-semibold leading-tight">{{ item.outlet.name }}</h2>
            <p class="text-sm text-muted">{{ [item.outlet.address, item.outlet.city, item.outlet.province].filter(Boolean).join(', ') }}</p>
        </header>

        <p v-if="error" class="rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">{{ error }}</p>

        <section :aria-label="t('master.review.candidates_aria')">
            <h3 class="hud-label mb-2">{{ t('master.review.candidates_title') }}</h3>
            <ul v-if="item.candidates.length" class="m-0 flex list-none flex-col gap-2 p-0">
                <li v-for="(c, i) in item.candidates" :key="i" class="flex flex-wrap items-center justify-between gap-3 rounded-chip border border-line bg-surface/40 px-3 py-2" data-testid="review-candidate">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm">{{ c.display_name }}</p>
                        <p class="num text-2xs text-muted">{{ formatCoordinates(c.lat, c.lng) }}</p>
                    </div>
                    <Badge :tone="scoreTone(c.score)" class="num" :aria-label="t('master.review.match_aria', { score: c.score })">{{ t('master.review.match', { score: c.score }) }}</Badge>
                    <Button size="sm" :loading="busy === `c${i}`" :disabled="busy !== null" :data-testid="`review-pick-${item.outlet.id}-${i}`" @click="pick(i)">{{ t('master.review.use') }}</Button>
                </li>
            </ul>
            <p v-else class="text-sm text-muted">{{ t('master.review.none') }}</p>
        </section>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="manual">
            <TextField v-model="lat" numeric type="number" step="any" :label="t('master.outlet_form.lat')" :error="errors.lat?.[0]" :data-testid="`review-lat-${item.outlet.id}`" class="w-36" />
            <TextField v-model="lng" numeric type="number" step="any" :label="t('master.outlet_form.lng')" :error="errors.lng?.[0]" :data-testid="`review-lng-${item.outlet.id}`" class="w-36" />
            <Button type="submit" variant="primary" :loading="busy === 'manual'" :disabled="lat === null || lng === null || busy !== null" :data-testid="`review-manual-${item.outlet.id}`">{{ t('master.review.save_coords') }}</Button>
        </form>
    </GlassPanel>
</template>
