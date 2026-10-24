const TZ = 'Asia/Jakarta';
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

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
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
}

/** 0.034 → "3,4%" */
export function formatPercent(ratio: number | null | undefined, decimals = 1): string {
    if (ratio === null || ratio === undefined || Number.isNaN(ratio)) return '–';
    return `${formatNumber(ratio * 100, decimals)}%`;
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
    return z ? `${z.d} ${MONTHS[z.m - 1]} ${z.y}` : '–';
}

/** "24 Okt 2026, 08.00 WIB" in Asia/Jakarta. */
export function formatDateTime(iso: string | Date | null | undefined): string {
    if (!iso) return '–';
    const z = zoned(iso);
    return z ? `${z.d} ${MONTHS[z.m - 1]} ${z.y}, ${z.hh}.${z.mm} WIB` : '–';
}

/** Indonesian relative time: "baru saja", "5 menit lalu", "kemarin", "dalam 2 jam". */
export function relativeTime(iso: string | Date | null | undefined, now: number | Date = Date.now()): string {
    if (!iso) return '–';
    const t = (iso instanceof Date ? iso : new Date(iso)).getTime();
    if (Number.isNaN(t)) return '–';
    const diff = t - (now instanceof Date ? now.getTime() : now);
    const abs = Math.abs(diff);
    const future = diff > 0;
    const fmt = (n: number, unit: string) => (future ? `dalam ${n} ${unit}` : `${n} ${unit} lalu`);
    const min = 60_000;
    const hour = 60 * min;
    const day = 24 * hour;
    if (abs < min) return 'baru saja';
    if (abs < hour) return fmt(Math.floor(abs / min), 'menit');
    if (abs < day) return fmt(Math.floor(abs / hour), 'jam');
    if (abs < 2 * day) return future ? 'besok' : 'kemarin';
    if (abs < 7 * day) return fmt(Math.floor(abs / day), 'hari');
    if (abs < 30 * day) return fmt(Math.floor(abs / (7 * day)), 'minggu');
    if (abs < 365 * day) return fmt(Math.floor(abs / (30 * day)), 'bulan');
    return fmt(Math.floor(abs / (365 * day)), 'tahun');
}

export function formatCoordinates(lat: number, lng: number): string {
    return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}
