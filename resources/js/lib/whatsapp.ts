/** Normalises an Indonesian phone number to 62… digits; null when not a mobile number. */
export function normalizePhone(raw: string | null | undefined): string | null {
    if (!raw) return null;
    let digits = raw.replace(/[^\d+]/g, '');
    if (digits.startsWith('+')) digits = digits.slice(1);
    if (digits.startsWith('0')) digits = '62' + digits.slice(1);
    else if (digits.startsWith('8')) digits = '62' + digits;
    if (!/^628\d{7,11}$/.test(digits)) return null;
    return digits;
}

export function buildWhatsAppUrl(phoneNormalized: string, message: string): string {
    return `https://wa.me/${phoneNormalized}?text=${encodeURIComponent(message)}`;
}
