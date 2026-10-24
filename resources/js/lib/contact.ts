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
    /** Indonesian reason shown when disabled (or a caution when enabled for a simulated number). */
    reason: string | null;
    kind: 'ok' | 'opt_out' | 'closed' | 'no_phone' | 'simulated' | 'invalid_phone';
}

/** Decides whether the WhatsApp action may be used (D-62, D-65). Priority: opt-out > closed > no phone > simulated > invalid. */
export function whatsappAvailability(input: WhatsAppAvailabilityInput): WhatsAppAvailability {
    if (input.doNotContact) return { enabled: false, reason: 'Jangan hubungi (opt-out)', kind: 'opt_out' };
    if (input.lifecycleStatus === 'closed') return { enabled: false, reason: 'Usaha sudah tutup', kind: 'closed' };
    if (!input.phone) return { enabled: false, reason: 'Tidak ada nomor telepon', kind: 'no_phone' };
    if (input.phoneSimulated) {
        if (input.whatsappUrl) return { enabled: true, reason: 'Nomor simulasi', kind: 'simulated' };
        return { enabled: false, reason: 'Nomor simulasi', kind: 'simulated' };
    }
    if (!input.whatsappUrl || !normalizePhone(input.phone)) {
        return { enabled: false, reason: 'Nomor tidak valid (bukan nomor ponsel)', kind: 'invalid_phone' };
    }
    return { enabled: true, reason: null, kind: 'ok' };
}

export const BLOCKED_REASON_TEXT: Record<NonNullable<ContactCheck['blocked_reason']>, string> = {
    do_not_contact: 'Prospek ini ditandai jangan hubungi (opt-out).',
    no_phone: 'Prospek ini tidak memiliki nomor telepon.',
    invalid_phone: 'Nomor bukan nomor ponsel yang valid (mis. telepon rumah).',
    closed: 'Usaha ini sudah tutup.',
};
