export const PLACEHOLDERS = ['{nama_bisnis}', '{nama_pengirim}', '{kategori}'] as const;

/** Mirrors backend MessageBuilder::isValidTemplate: at least one known placeholder and no unknown `{...}` token. Returns an error message or null. */
export function validateTemplate(template: string): string | null {
    if (template.trim() === '') return 'Template wajib diisi.';
    if (template.length > 2000) return 'Template maksimal 2000 karakter.';
    const tokens = Array.from(new Set(template.match(/\{[^{}\s]*\}/g) ?? []));
    if (tokens.length === 0) return `Template harus memuat minimal satu placeholder: ${PLACEHOLDERS.join(', ')}.`;
    const unknown = tokens.filter((t) => !(PLACEHOLDERS as readonly string[]).includes(t));
    if (unknown.length > 0) return `Placeholder tidak dikenal: ${unknown.join(', ')}. Yang valid: ${PLACEHOLDERS.join(', ')}.`;
    return null;
}

export function renderPreview(template: string, categoryName: string): string {
    return template
        .replaceAll('{nama_bisnis}', 'Kopi Senja')
        .replaceAll('{nama_pengirim}', 'Ayu')
        .replaceAll('{kategori}', categoryName || 'kategori');
}
