import { currentLocale, intlLocale, t } from '@/i18n';

const TZ = 'Asia/Jakarta';
const MONTHS: Record<'id' | 'en', string[]> = {
    id: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
    en: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
};
const month = (m: number) => MONTHS[currentLocale()][m - 1];

const partsFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

function zoned(iso: string | Date): { d: string; m: number; y: string; hh: string; mm: string } | null {
    const date = iso instanceof Date ? iso : new Date(iso);
    if (Number.isNaN(date.getTime())) return null;
    const p = Object.fromEntries(partsFormatter.formatToParts(date).map((x) => [x.type, x.value]));
    return { d: String(Number(p.day)), m: Number(p.month), y: p.year, hh: p.hour, mm: p.minute };
}

/** 12345 → "12.345" (Indonesian grouping). */
export function formatNumber(value: number | null | undefined, decimals = 0): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '–';
    return new Intl.NumberFormat(intlLocale(), { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
}

/** 1250000 → "Rp 1.250.000"; a missing amount reads as zero. */
export function formatRupiah(value: number | null | undefined): string {
    return `Rp ${formatNumber(value ?? 0)}`;
}

/** 0.034 → "3,4%" */
export function formatPercent(ratio: number | null | undefined, decimals = 1): string {
    if (ratio === null || ratio === undefined || Number.isNaN(ratio)) return '–';
    const pct = ratio * 100;
    const floor = 10 ** -decimals;
    // A tiny non-zero share must not read as "0,0%" (B-08).
    if (pct > 0 && pct < floor / 2) return `<${formatNumber(floor, decimals)}%`;
    return `${formatNumber(pct, decimals)}%`;
}

/** 640 → "640 m", 1450 → "1,5 km" */
export function formatDistance(meters: number | null | undefined): string {
    if (meters === null || meters === undefined || Number.isNaN(meters)) return '–';
    if (meters < 1000) return `${Math.round(meters)} m`;
    const km = meters / 1000;
    return `${formatNumber(km, km >= 10 ? 0 : 1)} km`;
}

/** "24 Okt 2026" in Asia/Jakarta. */
export function formatDate(iso: string | Date | null | undefined): string {
    if (!iso) return '–';
    const z = zoned(iso);
    return z ? `${z.d} ${month(z.m)} ${z.y}` : '–';
}

/** "24 Okt 2026, 08.00 WIB" in Asia/Jakarta. */
export function formatDateTime(iso: string | Date | null | undefined): string {
    if (!iso) return '–';
    const z = zoned(iso);
    return z ? `${z.d} ${month(z.m)} ${z.y}, ${z.hh}${currentLocale() === 'en' ? ':' : '.'}${z.mm} ${t('time.tz')}` : '–';
}

/** Relative time: "baru saja" / "just now", "5 menit lalu" / "5 min ago", "kemarin", "dalam 2 jam"… */
export function relativeTime(iso: string | Date | null | undefined, now: number | Date = Date.now()): string {
    if (!iso) return '–';
    const at = (iso instanceof Date ? iso : new Date(iso)).getTime();
    if (Number.isNaN(at)) return '–';
    const diff = at - (now instanceof Date ? now.getTime() : now);
    const abs = Math.abs(diff);
    const future = diff > 0;
    const fmt = (n: number, unit: string) => t(future ? 'time.in' : 'time.ago', { value: t(`time.${unit}`, { n }) });
    const min = 60_000;
    const hour = 60 * min;
    const day = 24 * hour;
    if (abs < min) return t('time.now');
    if (abs < hour) return fmt(Math.floor(abs / min), 'minute');
    if (abs < day) return fmt(Math.floor(abs / hour), 'hour');
    if (abs < 2 * day) return future ? t('time.tomorrow') : t('time.yesterday');
    if (abs < 7 * day) return fmt(Math.floor(abs / day), 'day');
    if (abs < 30 * day) return fmt(Math.floor(abs / (7 * day)), 'week');
    if (abs < 365 * day) return fmt(Math.floor(abs / (30 * day)), 'month');
    return fmt(Math.floor(abs / (365 * day)), 'year');
}

/** 0 → "hari ini", 1 → "kemarin", 3 → "3 hari lalu" (B-10). */
export function daysAgoText(days: number): string {
    if (days <= 0) return t('time.today');
    if (days === 1) return t('time.yesterday');
    return t('time.ago', { value: t('time.day', { n: days }) });
}

export function formatCoordinates(lat: number, lng: number): string {
    return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}
