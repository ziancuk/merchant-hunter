/** 65_000 → "1 mnt 5 dtk", 850 → "0,9 dtk", null → "–". */
export function formatDuration(ms: number | null | undefined): string {
    if (ms === null || ms === undefined || Number.isNaN(ms)) return '–';
    if (ms < 1000) return `${(ms / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} dtk`;
    const total = Math.round(ms / 1000);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const parts: string[] = [];
    if (h) parts.push(`${h} jam`);
    if (m) parts.push(`${m} mnt`);
    if (s || parts.length === 0) parts.push(`${s} dtk`);
    return parts.join(' ');
}
