import { t } from '@/i18n';

export const PLACEHOLDERS = ['{nama_bisnis}', '{nama_pengirim}', '{kategori}'] as const;

/** Mirrors backend MessageBuilder::isValidTemplate: at least one known placeholder and no unknown `{...}` token. Returns an error message or null. */
export function validateTemplate(template: string): string | null {
    if (template.trim() === '') return t('master.template.required');
    if (template.length > 2000) return t('master.template.too_long');
    const tokens = Array.from(new Set(template.match(/\{[^{}\s]*\}/g) ?? []));
    if (tokens.length === 0) return t('master.template.no_placeholder', { list: PLACEHOLDERS.join(', ') });
    const unknown = tokens.filter((tok) => !(PLACEHOLDERS as readonly string[]).includes(tok));
    if (unknown.length > 0) return t('master.template.unknown', { tokens: unknown.join(', '), list: PLACEHOLDERS.join(', ') });
    return null;
}

export function renderPreview(template: string, categoryName: string): string {
    return template
        .replaceAll('{nama_bisnis}', 'Kopi Senja')
        .replaceAll('{nama_pengirim}', 'Ayu')
        .replaceAll('{kategori}', categoryName || 'kategori');
}
