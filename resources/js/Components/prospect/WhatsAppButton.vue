<script setup lang="ts">
import { computed, useId } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import { useI18n } from '@/i18n';
import { whatsappAvailability } from '@/lib/contact';
import type { LifecycleStatus } from '@/types';

const props = withDefaults(
    defineProps<{
        phone: string | null;
        phoneSimulated?: boolean;
        whatsappUrl: string | null;
        doNotContact?: boolean;
        lifecycleStatus?: LifecycleStatus;
        label?: string;
        /** When true, the click is intercepted (preventDefault) and only emitted, so a composer can take over. */
        intercept?: boolean;
    }>(),
    { phoneSimulated: false, doNotContact: false, lifecycleStatus: 'open', label: 'WhatsApp', intercept: false },
);
const emit = defineEmits<{ click: [event: MouseEvent] }>();

const { t } = useI18n();
const reasonId = `wa-reason-${useId()}`;
const state = computed(() =>
    whatsappAvailability({
        phone: props.phone,
        phoneSimulated: props.phoneSimulated,
        whatsappUrl: props.whatsappUrl,
        doNotContact: props.doNotContact,
        lifecycleStatus: props.lifecycleStatus,
    }),
);

function onClick(e: MouseEvent): void {
    if (props.intercept) e.preventDefault();
    emit('click', e);
}

/** Translated here from the availability kind (lib/contact.ts keeps the Indonesian fallback text). */
const reason = computed(() => (state.value.reason ? t(`prospect.wa.reason.${state.value.kind}`) : null));

const base = 'inline-flex h-10 items-center justify-center gap-2 rounded-chip px-4 text-sm font-medium transition';
</script>

<template>
    <span class="inline-flex flex-col items-start gap-1">
        <a
            v-if="state.enabled"
            :href="whatsappUrl!"
            target="_blank"
            rel="noopener noreferrer"
            :class="[base, 'bg-st-won text-ink hover:brightness-110']"
            data-testid="wa-button"
            :aria-describedby="reason ? reasonId : undefined"
            @click="onClick"
        >
            <Icon name="whatsapp" />
            {{ label }}
        </a>
        <button
            v-else
            type="button"
            disabled
            aria-disabled="true"
            :aria-describedby="reasonId"
            :class="[base, 'cursor-not-allowed border border-line bg-fg/5 text-muted']"
            data-testid="wa-button"
        >
            <Icon name="whatsapp" />
            {{ label }}
        </button>
        <span v-if="reason" :id="reasonId" class="text-xs text-muted" data-testid="wa-reason">{{ reason }}</span>
    </span>
</template>
