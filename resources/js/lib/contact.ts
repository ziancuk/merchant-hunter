import { t } from '@/i18n';
import { normalizePhone } from '@/lib/whatsapp';
import type { ContactCheck, LifecycleStatus } from '@/types';

export interface WhatsAppAvailabilityInput {
    phone: string | null;
    phoneSimulated?: boolean;
    whatsappUrl: string | null;
    doNotContact?: boolean;
    lifecycleStatus?: LifecycleStatus;
}

export interface WhatsAppAvailability {
    enabled: boolean;
    /** Localised reason shown when disabled (or a caution when enabled for a simulated number). */
    reason: string | null;
    kind: 'ok' | 'opt_out' | 'closed' | 'no_phone' | 'simulated' | 'invalid_phone';
}

/** Decides whether the WhatsApp action may be used (D-62, D-65). Priority: opt-out > closed > no phone > simulated > invalid. */
export function whatsappAvailability(input: WhatsAppAvailabilityInput): WhatsAppAvailability {
    if (input.doNotContact) return { enabled: false, reason: t('ui.contact.optOut'), kind: 'opt_out' };
    if (input.lifecycleStatus === 'closed') return { enabled: false, reason: t('ui.contact.closed'), kind: 'closed' };
    if (!input.phone) return { enabled: false, reason: t('ui.contact.noPhone'), kind: 'no_phone' };
    if (input.phoneSimulated) {
        if (input.whatsappUrl) return { enabled: true, reason: t('ui.contact.simulated'), kind: 'simulated' };
        return { enabled: false, reason: t('ui.contact.simulated'), kind: 'simulated' };
    }
    if (!input.whatsappUrl || !normalizePhone(input.phone)) {
        return { enabled: false, reason: t('ui.contact.invalidPhone'), kind: 'invalid_phone' };
    }
    return { enabled: true, reason: null, kind: 'ok' };
}

/** Proxy over the i18n messages: reading a key inside a render function follows the active language. */
export const BLOCKED_REASON_TEXT = new Proxy({} as Record<NonNullable<ContactCheck['blocked_reason']>, string>, {
    get: (_target, key) => (typeof key === 'string' ? t(`ui.contact.blocked.${key}`) : undefined),
    has: () => true,
});
