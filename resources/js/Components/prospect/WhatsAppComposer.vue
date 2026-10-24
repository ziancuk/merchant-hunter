<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import ErrorState from '@/Components/ui/ErrorState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { api, normalizeError, postKeepalive, type ApiError } from '@/lib/api';
import { BLOCKED_REASON_TEXT } from '@/lib/contact';
import { formatDateTime } from '@/lib/format';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import type { ContactCheck, ProspectDetail, SharedProps } from '@/types';

const props = withDefaults(defineProps<{ prospectId: number; aiEnabled?: boolean; autoCheck?: boolean }>(), { aiEnabled: undefined, autoCheck: true });
const emit = defineEmits<{ contacted: [prospect: ProspectDetail]; closed: [] }>();

const MAX_LEN = 1000;
const page = usePage();
const shared = (): SharedProps => page.props as unknown as SharedProps;
const aiOn = computed(() => props.aiEnabled ?? shared()?.app?.ai_enabled ?? false);

const phase = ref<'idle' | 'loading' | 'ready' | 'error' | 'sent'>('idle');
const loadError = ref<string>('');
const check = ref<ContactCheck | null>(null);
const message = ref('');
const overrideReason = ref('');
const sending = ref(false);
const logError = ref<string>('');
const aiLoading = ref(false);
const aiError = ref('');
let lastBody: { message: string; override_reason?: string } | null = null;
let checkToken = 0;

const overridesLeft = computed(() => (check.value ? Math.max(0, check.value.max_overrides - check.value.override_count) : 0));
const needsOverride = computed(() => !!check.value?.min_interval_violation);
const overrideExhausted = computed(() => needsOverride.value && overridesLeft.value <= 0);
const blocked = computed(() => !!check.value && (!check.value.allowed || !!check.value.blocked_reason || !check.value.phone_normalized));
const blockedText = computed(() => {
    const r = check.value?.blocked_reason;
    return r ? BLOCKED_REASON_TEXT[r] : 'Prospek ini tidak dapat dihubungi.';
});
const trimmed = computed(() => message.value.trim());
const canSend = computed(
    () =>
        phase.value === 'ready' &&
        !!check.value &&
        !blocked.value &&
        !sending.value &&
        trimmed.value.length > 0 &&
        message.value.length <= MAX_LEN &&
        (!needsOverride.value || (!overrideExhausted.value && overrideReason.value.trim().length > 0)),
);

async function runCheck(): Promise<void> {
    const token = ++checkToken;
    phase.value = 'loading';
    loadError.value = '';
    try {
        const res = await api.post<ContactCheck>(`/api/prospects/${props.prospectId}/contact-check`, {});
        if (token !== checkToken) return;
        check.value = res.data;
        message.value = res.data.message ?? '';
        overrideReason.value = '';
        phase.value = 'ready';
    } catch (e) {
        if (token !== checkToken) return;
        loadError.value = normalizeError(e).message;
        phase.value = 'error';
    }
}

async function record(body: { message: string; override_reason?: string }): Promise<void> {
    sending.value = true;
    logError.value = '';
    try {
        const res = await postKeepalive<{ prospect: ProspectDetail }>(`/api/prospects/${props.prospectId}/contact`, body);
        phase.value = 'sent';
        lastBody = null;
        emit('contacted', res.prospect);
    } catch (e) {
        logError.value = (e as ApiError).message ?? 'Gagal mencatat kontak.';
    } finally {
        sending.value = false;
    }
}

/** D-65: open wa.me SYNCHRONOUSLY inside the click handler (popup blockers), then log with keepalive. */
function send(): void {
    if (!canSend.value || !check.value?.phone_normalized) return;
    const url = buildWhatsAppUrl(check.value.phone_normalized, trimmed.value);
    window.open(url, '_blank', 'noopener,noreferrer');
    const body: { message: string; override_reason?: string } = { message: trimmed.value };
    if (needsOverride.value) body.override_reason = overrideReason.value.trim();
    lastBody = body;
    void record(body);
}

function retryRecord(): void {
    if (lastBody) void record(lastBody);
}

async function generateAi(): Promise<void> {
    aiLoading.value = true;
    aiError.value = '';
    try {
        const res = await api.post<{ message: string }>(`/api/prospects/${props.prospectId}/message-ai`, {});
        message.value = res.data.message;
    } catch (e) {
        aiError.value = normalizeError(e).message;
    } finally {
        aiLoading.value = false;
    }
}

onMounted(() => {
    if (props.autoCheck) void runCheck();
});
watch(
    () => props.prospectId,
    () => {
        phase.value = 'idle';
        check.value = null;
        if (props.autoCheck) void runCheck();
    },
);
defineExpose({ runCheck });
</script>

<template>
    <section class="space-y-3 rounded-panel border border-line bg-surface/60 p-4" aria-label="Kirim pesan WhatsApp">
        <header class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 font-display text-sm font-semibold"><Icon name="whatsapp" class="text-st-won" /> Hubungi lewat WhatsApp</h3>
            <button type="button" class="cursor-pointer text-xs text-muted hover:text-fg" @click="emit('closed')">Tutup</button>
        </header>

        <Skeleton v-if="phase === 'loading' || phase === 'idle'" :lines="3" height="1.25rem" />
        <ErrorState v-else-if="phase === 'error'" title="Pengecekan kontak gagal" :message="loadError" @retry="runCheck" />

        <template v-else-if="check">
            <p v-if="blocked" class="flex items-start gap-2 rounded-chip border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert" data-testid="wa-blocked">
                <Icon name="ban" class="mt-0.5" />
                {{ blockedText }}
            </p>

            <p
                v-if="check.warning && phase !== 'sent'"
                class="flex items-start gap-2 rounded-chip border border-warm/50 bg-warm/10 p-3 text-sm text-warm"
                role="status"
                data-testid="wa-warning"
            >
                <Icon name="alert" class="mt-0.5" />
                <span>
                    Sudah dihubungi oleh <strong>{{ check.warning.by }}</strong> {{ check.warning.days_ago }} hari lalu ({{ formatDateTime(check.warning.at) }}).
                    Pastikan tidak terjadi kontak ganda.
                </span>
            </p>

            <div v-if="needsOverride && !blocked && phase !== 'sent'" class="space-y-2 rounded-chip border border-hot/40 bg-hot/10 p-3 text-sm" data-testid="wa-override">
                <p class="text-fg">
                    Kontak ini melanggar jeda minimal antar kontak.
                    <span v-if="!overrideExhausted">Isi alasan untuk melanjutkan.</span>
                    <strong v-else class="text-danger">Batas override untuk prospek ini sudah habis.</strong>
                </p>
                <p class="num text-xs text-muted" data-testid="wa-overrides-left">Sisa override: {{ overridesLeft }} dari {{ check.max_overrides }}</p>
                <label v-if="!overrideExhausted" class="block text-xs text-muted">
                    Alasan override (wajib)
                    <textarea v-model="overrideReason" rows="2" class="mt-1 w-full rounded-chip border border-line bg-ink/60 p-2 text-sm text-fg" data-testid="wa-override-reason" />
                </label>
            </div>

            <template v-if="!blocked">
                <label class="block text-xs text-muted" for="wa-message">Pesan (dapat diedit)</label>
                <textarea
                    id="wa-message"
                    v-model="message"
                    rows="5"
                    :maxlength="MAX_LEN"
                    :disabled="phase === 'sent'"
                    class="w-full rounded-chip border border-line bg-ink/60 p-3 text-sm text-fg"
                    data-testid="wa-message"
                />
                <div class="flex items-center justify-between text-xs text-muted">
                    <span class="num">{{ message.length }}/{{ MAX_LEN }}</span>
                    <Button v-if="aiOn && phase !== 'sent'" size="sm" variant="ghost" :loading="aiLoading" data-testid="wa-ai" @click="generateAi">
                        <Icon name="sparkle" :size="14" /> Buat pesan dengan AI
                    </Button>
                </div>
                <p v-if="aiError" class="text-xs text-danger" role="alert">{{ aiError }}</p>

                <p v-if="phase === 'sent'" class="flex items-center gap-2 rounded-chip border border-st-won/40 bg-st-won/10 p-3 text-sm text-st-won" role="status" data-testid="wa-sent">
                    <Icon name="check" /> WhatsApp dibuka dan kontak sudah dicatat.
                </p>
                <div v-if="logError" class="space-y-2 rounded-chip border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert" data-testid="wa-log-error">
                    <p>WhatsApp sudah dibuka, tetapi pencatatan gagal: {{ logError }}</p>
                    <Button size="sm" variant="secondary" :loading="sending" @click="retryRecord">Catat ulang</Button>
                </div>

                <Button v-if="phase !== 'sent'" variant="primary" class="w-full" :disabled="!canSend" :loading="sending" data-testid="wa-send" @click="send">
                    <Icon name="send" :size="16" /> Buka WhatsApp &amp; catat
                </Button>
            </template>
        </template>
    </section>
</template>
