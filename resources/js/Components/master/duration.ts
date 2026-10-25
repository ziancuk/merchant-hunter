import { intlLocale, t } from '@/i18n';

/** 65_000 → "1 mnt 5 dtk", 850 → "0,9 dtk", null → "–". */
export function formatDuration(ms: number | null | undefined): string {
    if (ms === null || ms === undefined || Number.isNaN(ms)) return '–';
    if (ms < 1000) return t('master.duration.sec', { n: (ms / 1000).toLocaleString(intlLocale(), { maximumFractionDigits: 1 }) });
    const total = Math.round(ms / 1000);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const parts: string[] = [];
    if (h) parts.push(t('master.duration.hour', { n: h }));
    if (m) parts.push(t('master.duration.min', { n: m }));
    if (s || parts.length === 0) parts.push(t('master.duration.sec', { n: s }));
    return parts.join(' ');
}
