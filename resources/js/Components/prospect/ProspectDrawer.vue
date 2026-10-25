<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import ActivityTimeline from './ActivityTimeline.vue';
import ProspectActions from './ProspectActions.vue';
import ProspectHeader from './ProspectHeader.vue';
import ProspectNotes from './ProspectNotes.vue';
import ScoreBreakdown from './ScoreBreakdown.vue';
import SimulasiBadge from './SimulasiBadge.vue';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { formatCoordinates, formatDateTime, formatDistance, formatNumber, relativeTime } from '@/lib/format';
import type { Activity, PipelineStatus, ProspectDetail } from '@/types';

export interface RejectionReason {
    id: number;
    key: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        /** Drawer is open while this is non-null. */
        prospectId: number | null;
        /** Custom loader (tests / pages with their own cache). Defaults to GET /api/prospects/{id}. */
        loader?: (id: number) => Promise<ProspectDetail>;
        rejectionReasons?: RejectionReason[];
        aiEnabled?: boolean;
    }>(),
    { loader: undefined, rejectionReasons: () => [], aiEnabled: undefined },
);

const emit = defineEmits<{
    close: [];
    updated: [prospect: ProspectDetail];
    contacted: [prospect: ProspectDetail];
    locate: [coords: { lat: number; lng: number }];
}>();

const { t } = useI18n();
const detail = ref<ProspectDetail | null>(null);
const loading = ref(false);
const loadError = ref('');
const actionError = ref('');
const busy = ref(false);
const showComposer = ref(false);
const pendingLost = ref(false);
const rejectionId = ref<number | ''>('');
const noteText = ref('');
let token = 0;

const open = computed(() => props.prospectId !== null);

function unwrap(raw: unknown): ProspectDetail {
    const r = raw as { data?: ProspectDetail; prospect?: ProspectDetail } & Partial<ProspectDetail>;
    if (r && r.id === undefined) return (r.data ?? r.prospect) as ProspectDetail;
    return r as ProspectDetail;
}

async function load(): Promise<void> {
    const id = props.prospectId;
    if (id === null) return;
    const mine = ++token;
    loading.value = true;
    loadError.value = '';
    try {
        const result = props.loader ? await props.loader(id) : unwrap((await api.get(`/api/prospects/${id}`)).data);
        if (mine !== token) return;
        detail.value = result;
    } catch (e) {
        if (mine !== token) return;
        loadError.value = normalizeError(e).message;
    } finally {
        if (mine === token) loading.value = false;
    }
}

watch(
    () => props.prospectId,
    (id) => {
        showComposer.value = false;
        pendingLost.value = false;
        if (id === null) {
            token++; // keep the last detail on screen while the drawer slides out
            return;
        }
        detail.value = null;
        actionError.value = '';
        rejectionId.value = '';
        noteText.value = '';
        void load();
    },
    { immediate: true },
);

function apply(next: ProspectDetail): void {
    detail.value = next;
    emit('updated', next);
}

async function mutate(fn: () => Promise<void>): Promise<void> {
    busy.value = true;
    actionError.value = '';
    try {
        await fn();
    } catch (e) {
        actionError.value = normalizeError(e).message;
    } finally {
        busy.value = false;
    }
}

function setStatus(status: PipelineStatus): void {
    if (!detail.value || status === detail.value.pipeline_status) return;
    if (status === 'lost') {
        pendingLost.value = true;
        return;
    }
    pendingLost.value = false;
    void mutate(async () => {
        const res = await api.post<{ prospect: ProspectDetail }>(`/api/prospects/${detail.value!.id}/status`, { status });
        apply(res.data.prospect);
    });
}

function confirmLost(): void {
    if (!detail.value || rejectionId.value === '') return;
    const reason = rejectionId.value;
    void mutate(async () => {
        const res = await api.post<{ prospect: ProspectDetail }>(`/api/prospects/${detail.value!.id}/status`, { status: 'lost', rejection_reason_id: reason });
        pendingLost.value = false;
        rejectionId.value = '';
        apply(res.data.prospect);
    });
}

function saveNote(): void {
    const body = noteText.value.trim();
    if (!detail.value || !body) return;
    void mutate(async () => {
        const res = await api.post<{ activity: Activity }>(`/api/prospects/${detail.value!.id}/notes`, { body });
        noteText.value = '';
        apply({ ...detail.value!, latest_note: body, activities: [res.data.activity, ...detail.value!.activities] });
    });
}

function toggleOptOut(): void {
    if (!detail.value) return;
    const id = detail.value.id;
    const turnOff = detail.value.do_not_contact;
    void mutate(async () => {
        const res = turnOff ? await api.delete<{ prospect: ProspectDetail }>(`/api/prospects/${id}/opt-out`) : await api.post<{ prospect: ProspectDetail }>(`/api/prospects/${id}/opt-out`, {});
        showComposer.value = false;
        apply(res.data.prospect);
    });
}

function onContacted(next: ProspectDetail): void {
    detail.value = next;
    emit('contacted', next);
    emit('updated', next);
}

const safeWebsite = computed(() => (detail.value?.website && /^https?:\/\//i.test(detail.value.website) ? detail.value.website : null));
const title = computed(() => detail.value?.name ?? (loadError.value ? t('prospect.drawer.fallback_title') : t('prospect.drawer.loading')));
</script>

<template>
    <Drawer :model-value="open" :title="title" :modal="false" @close="emit('close')">
        <div v-if="loading && !detail" class="space-y-4 p-4" data-testid="drawer-loading">
            <div class="flex items-center gap-3">
                <Skeleton width="56px" circle />
                <div class="flex-1"><Skeleton :lines="2" height="1rem" /></div>
            </div>
            <Skeleton :lines="4" height="0.9rem" />
            <Skeleton height="5rem" />
        </div>

        <ErrorState v-else-if="loadError && !detail" :title="t('prospect.drawer.load_failed')" :message="loadError" :retrying="loading" @retry="load" />

        <div v-else-if="detail" class="space-y-6 p-4" data-testid="drawer-content">
            <ProspectHeader :detail="detail" />

            <p v-if="actionError" class="rounded-chip border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert" data-testid="action-error">{{ actionError }}</p>

            <!-- Score -->
            <section aria-labelledby="sec-skor">
                <h3 id="sec-skor" class="hud-label mb-2">{{ t('prospect.drawer.score_title') }}</h3>
                <ScoreBreakdown :components="detail.score_breakdown" :band="detail.score_band" />
            </section>

            <!-- Location -->
            <section aria-labelledby="sec-lokasi" class="space-y-2">
                <h3 id="sec-lokasi" class="hud-label">{{ t('prospect.drawer.location') }}</h3>
                <p v-if="detail.address" class="text-sm text-fg">{{ detail.address }}</p>
                <div v-else class="flex items-start gap-2 rounded-chip border border-dashed border-line p-3 text-sm" data-testid="address-missing">
                    <Icon name="pin" class="mt-0.5 text-muted" />
                    <div>
                        <p class="text-fg">{{ t('prospect.drawer.no_address') }}</p>
                        <p class="num text-xs text-muted">{{ formatCoordinates(detail.lat, detail.lng) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant="secondary" @click="emit('locate', { lat: detail.lat, lng: detail.lng })"><Icon name="navigation" :size="14" /> {{ t('prospect.drawer.view_on_map') }}</Button>
                    <!-- Extra page-specific actions (e.g. "Tambah ke rute" on the map page). -->
                    <slot name="actions" :prospect="detail" />
                </div>
                <p class="text-xs text-muted">
                    <template v-if="detail.nearest_outlet">
                        {{ t('prospect.drawer.nearest_outlet') }} <strong class="text-fg">{{ detail.nearest_outlet.name }}</strong> <span class="num">({{ formatDistance(detail.nearest_outlet.distance_m) }})</span>.
                    </template>
                    <template v-else>{{ t('prospect.drawer.no_nearest') }}</template>
                    <span class="num"> {{ formatNumber(detail.neighbor_count_500m) }}</span> {{ t('prospect.drawer.neighbors_suffix') }}
                </p>
            </section>

            <!-- Contact info -->
            <section aria-labelledby="sec-kontak">
                <h3 id="sec-kontak" class="hud-label mb-2">{{ t('prospect.drawer.contact_title') }}</h3>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-muted">{{ t('prospect.drawer.phone') }}</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <span v-if="detail.phone" class="num">{{ detail.phone }}</span>
                        <span v-else class="text-muted">{{ t('prospect.drawer.none') }}</span>
                        <SimulasiBadge v-if="detail.phone && detail.phone_simulated" :field="t('prospect.drawer.phone')" />
                    </dd>
                    <dt class="text-muted">{{ t('prospect.drawer.website') }}</dt>
                    <dd>
                        <a v-if="safeWebsite" :href="safeWebsite" target="_blank" rel="noopener noreferrer" class="break-all text-st-contacted underline-offset-2 hover:underline">{{ safeWebsite }}</a>
                        <span v-else class="text-muted">{{ t('prospect.drawer.none') }}</span>
                    </dd>
                    <dt class="text-muted">{{ t('prospect.drawer.rating') }}</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <template v-if="detail.rating !== null">
                            <span class="num inline-flex items-center gap-1"><Icon name="star" :size="14" class="text-warm" />{{ formatNumber(detail.rating, 1) }}</span>
                            <SimulasiBadge v-if="detail.rating_simulated" :field="t('prospect.drawer.rating')" />
                        </template>
                        <span v-else class="text-muted">{{ t('prospect.drawer.none_yet') }}</span>
                    </dd>
                    <dt class="text-muted">{{ t('prospect.drawer.reviews') }}</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <template v-if="detail.review_count !== null">
                            <span class="num">{{ formatNumber(detail.review_count) }}</span>
                            <SimulasiBadge v-if="detail.reviews_simulated" :field="t('prospect.drawer.reviews')" />
                        </template>
                        <span v-else class="text-muted">{{ t('prospect.drawer.none_yet') }}</span>
                    </dd>
                    <dt class="text-muted">{{ t('prospect.drawer.data_updated') }}</dt>
                    <dd class="num text-xs" :title="formatDateTime(detail.data_updated_at)">{{ formatDateTime(detail.data_updated_at) }} ({{ relativeTime(detail.data_updated_at) }})</dd>
                </dl>
            </section>

            <ProspectActions
                v-model:pending-lost="pendingLost"
                v-model:rejection-id="rejectionId"
                v-model:show-composer="showComposer"
                :detail="detail"
                :busy="busy"
                :rejection-reasons="rejectionReasons"
                :ai-enabled="aiEnabled"
                @set-status="setStatus"
                @confirm-lost="confirmLost"
                @toggle-opt-out="toggleOptOut"
                @contacted="onContacted"
            />

            <ProspectNotes v-model="noteText" :latest-note="detail.latest_note" :busy="busy" @save="saveNote" />

            <!-- Timeline -->
            <section aria-labelledby="sec-timeline">
                <h3 id="sec-timeline" class="hud-label mb-3">{{ t('prospect.drawer.activity') }}</h3>
                <ActivityTimeline :activities="detail.activities" />
            </section>

            <p class="flex flex-wrap items-center gap-2 text-2xs text-muted">
                <Badge v-if="detail.lifecycle_status === 'closed'" tone="danger">{{ t('prospect.badge.closed') }}</Badge>
                {{ t('prospect.drawer.first_seen', { when: relativeTime(detail.first_seen_at) }) }} · {{ t('prospect.drawer.density') }} <span class="num">{{ formatNumber(detail.area_density) }}</span>
            </p>
        </div>
    </Drawer>
</template>
