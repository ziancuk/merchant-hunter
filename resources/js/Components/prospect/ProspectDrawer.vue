<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import ActivityTimeline from './ActivityTimeline.vue';
import ProspectBadges from './ProspectBadges.vue';
import ScoreBreakdown from './ScoreBreakdown.vue';
import ScoreRing from './ScoreRing.vue';
import SimulasiBadge from './SimulasiBadge.vue';
import WhatsAppButton from './WhatsAppButton.vue';
import WhatsAppComposer from './WhatsAppComposer.vue';
import { api, normalizeError } from '@/lib/api';
import { STATUS_CLASSES } from '@/lib/colors';
import { BAND_LABEL, STATUS_LABEL } from '@/lib/labels';
import { formatCoordinates, formatDateTime, formatDistance, formatNumber, relativeTime } from '@/lib/format';
import { PIPELINE_STATUSES, type Activity, type PipelineStatus, type ProspectDetail } from '@/types';

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
const title = computed(() => detail.value?.name ?? (loadError.value ? 'Prospek' : 'Memuat prospek…'));
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

        <ErrorState v-else-if="loadError && !detail" title="Gagal memuat prospek" :message="loadError" :retrying="loading" @retry="load" />

        <div v-else-if="detail" class="space-y-6 p-4" data-testid="drawer-content">
            <!-- Header -->
            <section class="flex items-start gap-4">
                <ScoreRing :score="detail.score" :band="detail.score_band" :size="64" />
                <div class="min-w-0 flex-1 space-y-1.5">
                    <p class="text-sm text-muted">{{ detail.category.name }}</p>
                    <ProspectBadges :badges="detail.badges" />
                    <p class="hud-label">Skor {{ BAND_LABEL[detail.score_band] }}</p>
                </div>
            </section>

            <p v-if="actionError" class="rounded-chip border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert" data-testid="action-error">{{ actionError }}</p>

            <!-- Score -->
            <section aria-labelledby="sec-skor">
                <h3 id="sec-skor" class="hud-label mb-2">Rincian skor</h3>
                <ScoreBreakdown :components="detail.score_breakdown" :band="detail.score_band" />
            </section>

            <!-- Location -->
            <section aria-labelledby="sec-lokasi" class="space-y-2">
                <h3 id="sec-lokasi" class="hud-label">Lokasi</h3>
                <p v-if="detail.address" class="text-sm text-fg">{{ detail.address }}</p>
                <div v-else class="flex items-start gap-2 rounded-chip border border-dashed border-line p-3 text-sm" data-testid="address-missing">
                    <Icon name="pin" class="mt-0.5 text-muted" />
                    <div>
                        <p class="text-fg">Alamat belum tersedia</p>
                        <p class="num text-xs text-muted">{{ formatCoordinates(detail.lat, detail.lng) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant="secondary" @click="emit('locate', { lat: detail.lat, lng: detail.lng })"><Icon name="navigation" :size="14" /> Lihat di peta</Button>
                    <!-- Extra page-specific actions (e.g. "Tambah ke rute" on the map page). -->
                    <slot name="actions" :prospect="detail" />
                </div>
                <p class="text-xs text-muted">
                    <template v-if="detail.nearest_outlet">
                        Outlet terdekat: <strong class="text-fg">{{ detail.nearest_outlet.name }}</strong> <span class="num">({{ formatDistance(detail.nearest_outlet.distance_m) }})</span>.
                    </template>
                    <template v-else>Belum ada outlet terdekat.</template>
                    <span class="num"> {{ formatNumber(detail.neighbor_count_500m) }}</span> prospek lain dalam 500 m.
                </p>
            </section>

            <!-- Contact info -->
            <section aria-labelledby="sec-kontak">
                <h3 id="sec-kontak" class="hud-label mb-2">Kontak &amp; reputasi</h3>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-muted">Telepon</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <span v-if="detail.phone" class="num">{{ detail.phone }}</span>
                        <span v-else class="text-muted">Tidak ada</span>
                        <SimulasiBadge v-if="detail.phone && detail.phone_simulated" field="Telepon" />
                    </dd>
                    <dt class="text-muted">Website</dt>
                    <dd>
                        <a v-if="safeWebsite" :href="safeWebsite" target="_blank" rel="noopener noreferrer" class="break-all text-st-contacted underline-offset-2 hover:underline">{{ safeWebsite }}</a>
                        <span v-else class="text-muted">Tidak ada</span>
                    </dd>
                    <dt class="text-muted">Rating</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <template v-if="detail.rating !== null">
                            <span class="num inline-flex items-center gap-1"><Icon name="star" :size="14" class="text-warm" />{{ formatNumber(detail.rating, 1) }}</span>
                            <SimulasiBadge v-if="detail.rating_simulated" field="Rating" />
                        </template>
                        <span v-else class="text-muted">Belum ada</span>
                    </dd>
                    <dt class="text-muted">Ulasan</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <template v-if="detail.review_count !== null">
                            <span class="num">{{ formatNumber(detail.review_count) }}</span>
                            <SimulasiBadge v-if="detail.reviews_simulated" field="Ulasan" />
                        </template>
                        <span v-else class="text-muted">Belum ada</span>
                    </dd>
                    <dt class="text-muted">Data diperbarui</dt>
                    <dd class="num text-xs" :title="formatDateTime(detail.data_updated_at)">{{ formatDateTime(detail.data_updated_at) }} ({{ relativeTime(detail.data_updated_at) }})</dd>
                </dl>
            </section>

            <!-- Pipeline -->
            <section aria-labelledby="sec-pipeline" class="space-y-3">
                <h3 id="sec-pipeline" class="hud-label">Status pipeline</h3>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Ubah status pipeline">
                    <button
                        v-for="s in PIPELINE_STATUSES"
                        :key="s"
                        type="button"
                        class="cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="detail.pipeline_status === s ? [STATUS_CLASSES[s].soft, STATUS_CLASSES[s].text, STATUS_CLASSES[s].border] : 'border-line text-muted hover:text-fg'"
                        :aria-pressed="detail.pipeline_status === s"
                        :disabled="busy"
                        :data-testid="`status-${s}`"
                        @click="setStatus(s)"
                    >
                        {{ STATUS_LABEL[s] }}
                    </button>
                </div>
                <p v-if="detail.pipeline_status === 'lost' && detail.rejection_reason" class="text-xs text-muted">Alasan: {{ detail.rejection_reason.label }}</p>
                <div v-if="pendingLost" class="space-y-2 rounded-chip border border-st-lost/40 bg-st-lost/10 p-3" data-testid="lost-picker">
                    <label class="block text-xs text-muted" for="rej-reason">Alasan penolakan (wajib)</label>
                    <select id="rej-reason" v-model="rejectionId" class="h-10 w-full rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg">
                        <option value="">Pilih alasan…</option>
                        <option v-for="r in rejectionReasons" :key="r.id" :value="r.id">{{ r.label }}</option>
                    </select>
                    <div class="flex gap-2">
                        <Button size="sm" variant="danger" :disabled="rejectionId === ''" :loading="busy" data-testid="confirm-lost" @click="confirmLost">Tandai ditolak</Button>
                        <Button size="sm" variant="ghost" @click="pendingLost = false">Batal</Button>
                    </div>
                </div>
                <p class="text-xs text-muted">
                    <template v-if="detail.handled_by">Ditangani oleh <strong class="text-fg">{{ detail.handled_by.name }}</strong>. </template>
                    <template v-if="detail.last_contacted_at">
                        Terakhir dihubungi {{ relativeTime(detail.last_contacted_at) }}<template v-if="detail.last_contacted_by"> oleh {{ detail.last_contacted_by.name }}</template>
                        (<span class="num">{{ detail.contact_count }}x</span>).
                    </template>
                    <template v-else>Belum pernah dihubungi.</template>
                </p>
            </section>

            <!-- WhatsApp -->
            <section aria-labelledby="sec-wa" class="space-y-3">
                <h3 id="sec-wa" class="hud-label">Hubungi</h3>
                <WhatsAppButton
                    :phone="detail.phone"
                    :phone-simulated="detail.phone_simulated"
                    :whatsapp-url="detail.whatsapp_url"
                    :do-not-contact="detail.do_not_contact"
                    :lifecycle-status="detail.lifecycle_status"
                    intercept
                    @click="showComposer = true"
                />
                <WhatsAppComposer
                    v-if="showComposer && !detail.do_not_contact"
                    :key="detail.id"
                    :prospect-id="detail.id"
                    :ai-enabled="aiEnabled"
                    @contacted="onContacted"
                    @closed="showComposer = false"
                />
                <div class="flex items-center justify-between rounded-chip border border-line p-3">
                    <div>
                        <p class="text-sm text-fg">Jangan hubungi (opt-out)</p>
                        <p class="text-xs text-muted">Sembunyikan aksi WhatsApp untuk prospek ini.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="detail.do_not_contact"
                        aria-label="Jangan hubungi"
                        :disabled="busy"
                        class="relative h-6 w-11 shrink-0 cursor-pointer rounded-full border border-line transition disabled:opacity-50"
                        :class="detail.do_not_contact ? 'bg-danger/40' : 'bg-fg/10'"
                        data-testid="optout-toggle"
                        @click="toggleOptOut"
                    >
                        <span class="absolute left-0.5 top-0.5 h-4.5 w-4.5 rounded-full bg-fg transition-transform" :class="detail.do_not_contact ? 'translate-x-5' : ''" />
                    </button>
                </div>
            </section>

            <!-- Notes -->
            <section aria-labelledby="sec-note" class="space-y-2">
                <h3 id="sec-note" class="hud-label">Catatan</h3>
                <p v-if="detail.latest_note" class="whitespace-pre-line rounded-chip bg-fg/5 p-3 text-sm" data-testid="latest-note">{{ detail.latest_note }}</p>
                <label class="sr-only" for="note-input">Tambah catatan</label>
                <textarea id="note-input" v-model="noteText" rows="2" maxlength="2000" placeholder="Tambah catatan…" class="w-full rounded-chip border border-line bg-ink/60 p-2 text-sm text-fg" />
                <Button size="sm" variant="secondary" :disabled="!noteText.trim()" :loading="busy" data-testid="save-note" @click="saveNote"><Icon name="note" :size="14" /> Simpan catatan</Button>
            </section>

            <!-- Timeline -->
            <section aria-labelledby="sec-timeline">
                <h3 id="sec-timeline" class="hud-label mb-3">Aktivitas</h3>
                <ActivityTimeline :activities="detail.activities" />
            </section>

            <p class="flex flex-wrap items-center gap-2 text-2xs text-muted">
                <Badge v-if="detail.lifecycle_status === 'closed'" tone="danger">Tutup</Badge>
                Pertama ditemukan {{ relativeTime(detail.first_seen_at) }} · kepadatan area <span class="num">{{ formatNumber(detail.area_density) }}</span>
            </p>
        </div>
    </Drawer>
</template>
