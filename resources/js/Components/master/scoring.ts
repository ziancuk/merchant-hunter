// Mirror of App\Services\Scoring\ScoreCalculator::DEFAULTS (used to show defaults and send only overrides).
export const SCORING_DEFAULTS = {
    weights: { category: 25, quality: 20, proximity: 15, density: 15, contact: 10, new: 15 },
    rating_floor: 3.0,
    rating_ceiling: 5.0,
    rating_weight: 0.6,
    reviews_weight: 0.4,
    reviews_cap: 500,
    unrated_value: 0.25,
    proximity_distance_m: 2000,
    proximity_distance_share: 0.5,
    neighbor_values: [0.0, 0.6, 0.85, 1.0],
    density_cap: 25,
    contact_phone: 0.5,
    contact_website: 0.25,
    contact_address: 0.25,
    new_days: 30,
} as const;

export type ScoringParams = Record<string, unknown>;

function isObject(v: unknown): v is Record<string, unknown> {
    return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/** defaults <- overrides (recursive, arrays replaced), like array_replace_recursive. */
export function mergeParams(defaults: ScoringParams, overrides: ScoringParams): ScoringParams {
    const out: ScoringParams = {};
    for (const [k, d] of Object.entries(defaults)) {
        const o = overrides?.[k];
        if (isObject(d)) out[k] = mergeParams(d, isObject(o) ? o : {});
        else if (Array.isArray(d)) out[k] = Array.isArray(o) ? [...o] : [...d];
        else out[k] = o === undefined ? d : o;
    }
    return out;
}

/** Only the values that differ from the defaults (what gets stored as `scoring.params`). */
export function diffParams(current: ScoringParams, defaults: ScoringParams): ScoringParams {
    const out: ScoringParams = {};
    for (const [k, d] of Object.entries(defaults)) {
        const c = current[k];
        if (isObject(d)) {
            const sub = diffParams(isObject(c) ? c : {}, d);
            if (Object.keys(sub).length) out[k] = sub;
        } else if (Array.isArray(d)) {
            if (JSON.stringify(c) !== JSON.stringify(d)) out[k] = c;
        } else if (c !== d) out[k] = c;
    }
    return out;
}

export interface ScoringField {
    path: string;
    label: string;
    hint: string;
    step: number;
    min?: number;
    max?: number;
}

export const WEIGHT_FIELDS: ScoringField[] = [
    { path: 'weights.category', label: 'Kecocokan kategori', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
    { path: 'weights.quality', label: 'Kualitas (rating & ulasan)', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
    { path: 'weights.proximity', label: 'Kedekatan dengan outlet', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
    { path: 'weights.density', label: 'Kepadatan area', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
    { path: 'weights.contact', label: 'Kelengkapan kontak', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
    { path: 'weights.new', label: 'Baru buka', hint: 'Poin maksimum', step: 1, min: 0, max: 100 },
];

export const PARAM_FIELDS: ScoringField[] = [
    { path: 'rating_floor', label: 'Rating terendah (nilai 0)', hint: 'Di bawah ini kualitas rating dianggap 0', step: 0.1, min: 0, max: 5 },
    { path: 'rating_ceiling', label: 'Rating tertinggi (nilai 1)', hint: 'Di atas ini kualitas rating dianggap penuh', step: 0.1, min: 0, max: 5 },
    { path: 'rating_weight', label: 'Bobot rating dalam kualitas', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'reviews_weight', label: 'Bobot ulasan dalam kualitas', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'reviews_cap', label: 'Batas jumlah ulasan', hint: 'Ulasan di atas ini dihitung penuh', step: 10, min: 1 },
    { path: 'unrated_value', label: 'Nilai tanpa rating', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'proximity_distance_m', label: 'Jarak acuan outlet', hint: 'Meter', step: 100, min: 1 },
    { path: 'proximity_distance_share', label: 'Porsi jarak dalam kedekatan', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'density_cap', label: 'Batas kepadatan', hint: 'Jumlah tetangga yang dihitung penuh', step: 1, min: 1 },
    { path: 'contact_phone', label: 'Nilai ada telepon', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'contact_website', label: 'Nilai ada situs web', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'contact_address', label: 'Nilai ada alamat', hint: '0–1', step: 0.05, min: 0, max: 1 },
    { path: 'new_days', label: 'Lama dianggap baru', hint: 'Hari sejak ditemukan', step: 1, min: 1 },
];

export function getPath(obj: Record<string, unknown>, path: string): unknown {
    return path.split('.').reduce<unknown>((o, k) => (isObject(o) ? o[k] : undefined), obj);
}
export function setPath(obj: Record<string, unknown>, path: string, value: unknown): void {
    const keys = path.split('.');
    let cur = obj;
    for (const k of keys.slice(0, -1)) {
        if (!isObject(cur[k])) cur[k] = {};
        cur = cur[k] as Record<string, unknown>;
    }
    cur[keys[keys.length - 1]] = value;
}
