<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import { useI18n } from '@/i18n';
import { STATUS_CLASSES } from '@/lib/colors';
import { STATUS_LABEL } from '@/lib/labels';
import { relativeTime } from '@/lib/format';
import { PIPELINE_STATUSES, type PipelineStatus, type ProspectDetail } from '@/types';
import type { RejectionReason } from './ProspectDrawer.vue';
import WhatsAppButton from './WhatsAppButton.vue';
import WhatsAppComposer from './WhatsAppComposer.vue';

defineProps<{ detail: ProspectDetail; busy: boolean; rejectionReasons: RejectionReason[]; aiEnabled?: boolean }>();
const emit = defineEmits<{ setStatus: [status: PipelineStatus]; confirmLost: []; toggleOptOut: []; contacted: [prospect: ProspectDetail] }>();
const { t } = useI18n();
const pendingLost = defineModel<boolean>('pendingLost', { required: true });
const rejectionId = defineModel<number | ''>('rejectionId', { required: true });
const showComposer = defineModel<boolean>('showComposer', { required: true });
</script>

<template>
    <div class="space-y-6">
        <!-- Pipeline -->
        <section aria-labelledby="sec-pipeline" class="space-y-3">
            <h3 id="sec-pipeline" class="hud-label">{{ t('prospect.actions.pipeline') }}</h3>
            <div class="flex flex-wrap gap-1.5" role="group" :aria-label="t('prospect.actions.change_status')">
                <button
                    v-for="s in PIPELINE_STATUSES"
                    :key="s"
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="detail.pipeline_status === s ? [STATUS_CLASSES[s].soft, STATUS_CLASSES[s].text, STATUS_CLASSES[s].border] : 'border-line text-muted hover:text-fg'"
                    :aria-pressed="detail.pipeline_status === s"
                    :disabled="busy"
                    :data-testid="`status-${s}`"
                    @click="emit('setStatus', s)"
                >
                    {{ STATUS_LABEL[s] }}
                </button>
            </div>
            <p v-if="detail.pipeline_status === 'lost' && detail.rejection_reason" class="text-xs text-muted">{{ t('prospect.actions.reason', { reason: detail.rejection_reason.label }) }}</p>
            <div v-if="pendingLost" class="space-y-2 rounded-chip border border-st-lost/40 bg-st-lost/10 p-3" data-testid="lost-picker">
                <label class="block text-xs text-muted" for="rej-reason">{{ t('prospect.actions.lost_reason') }}</label>
                <select id="rej-reason" v-model="rejectionId" class="h-10 w-full rounded-chip border border-line bg-ink/60 px-2 text-sm text-fg">
                    <option value="">{{ t('prospect.actions.pick_reason') }}</option>
                    <option v-for="r in rejectionReasons" :key="r.id" :value="r.id">{{ r.label }}</option>
                </select>
                <div class="flex gap-2">
                    <Button size="sm" variant="danger" :disabled="rejectionId === ''" :loading="busy" data-testid="confirm-lost" @click="emit('confirmLost')">{{ t('prospect.actions.mark_lost') }}</Button>
                    <Button size="sm" variant="ghost" @click="pendingLost = false">{{ t('prospect.actions.cancel') }}</Button>
                </div>
            </div>
            <p class="text-xs text-muted">
                <template v-if="detail.handled_by">{{ t('prospect.actions.handled_by') }} <strong class="text-fg">{{ detail.handled_by.name }}</strong>. </template>
                <template v-if="detail.last_contacted_at">
                    {{ t('prospect.actions.last_contacted', { when: relativeTime(detail.last_contacted_at) }) }}<template v-if="detail.last_contacted_by"> {{ t('prospect.actions.by', { name: detail.last_contacted_by.name }) }}</template>
                    (<span class="num">{{ detail.contact_count }}x</span>).
                </template>
                <template v-else>{{ t('prospect.actions.never_contacted') }}</template>
            </p>
        </section>

        <!-- WhatsApp -->
        <section aria-labelledby="sec-wa" class="space-y-3">
            <h3 id="sec-wa" class="hud-label">{{ t('prospect.actions.contact') }}</h3>
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
                @contacted="(next: ProspectDetail) => emit('contacted', next)"
                @closed="showComposer = false"
            />
            <div class="flex items-center justify-between rounded-chip border border-line p-3">
                <div>
                    <p class="text-sm text-fg">{{ t('prospect.actions.optout_title') }}</p>
                    <p class="text-xs text-muted">{{ t('prospect.actions.optout_hint') }}</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="detail.do_not_contact"
                    :aria-label="t('prospect.actions.optout_aria')"
                    :disabled="busy"
                    class="relative h-6 w-11 shrink-0 cursor-pointer rounded-full border border-line transition disabled:opacity-50"
                    :class="detail.do_not_contact ? 'bg-danger/40' : 'bg-fg/10'"
                    data-testid="optout-toggle"
                    @click="emit('toggleOptOut')"
                >
                    <span class="absolute left-0.5 top-0.5 h-4.5 w-4.5 rounded-full bg-fg transition-transform" :class="detail.do_not_contact ? 'translate-x-5' : ''" />
                </button>
            </div>
        </section>
    </div>
</template>
