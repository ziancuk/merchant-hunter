import { t } from '@/i18n';

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
    /** Translated at read time so the form follows the active language. */
    readonly label: string;
    readonly hint: string;
    step: number;
    min?: number;
    max?: number;
}

function field(path: string, step: number, min?: number, max?: number, weight = false): ScoringField {
    const key = path.replace('.', '_');
    return {
        path,
        step,
        min,
        max,
        get label() {
            return t(`master.scoring_field.${key}.label`);
        },
        get hint() {
            return weight ? t('master.scoring_field.max_points') : t(`master.scoring_field.${key}.hint`);
        },
    };
}

export const WEIGHT_FIELDS: ScoringField[] = [
    field('weights.category', 1, 0, 100, true),
    field('weights.quality', 1, 0, 100, true),
    field('weights.proximity', 1, 0, 100, true),
    field('weights.density', 1, 0, 100, true),
    field('weights.contact', 1, 0, 100, true),
    field('weights.new', 1, 0, 100, true),
];

export const PARAM_FIELDS: ScoringField[] = [
    field('rating_floor', 0.1, 0, 5),
    field('rating_ceiling', 0.1, 0, 5),
    field('rating_weight', 0.05, 0, 1),
    field('reviews_weight', 0.05, 0, 1),
    field('reviews_cap', 10, 1),
    field('unrated_value', 0.05, 0, 1),
    field('proximity_distance_m', 100, 1),
    field('proximity_distance_share', 0.05, 0, 1),
    field('density_cap', 1, 1),
    field('contact_phone', 0.05, 0, 1),
    field('contact_website', 0.05, 0, 1),
    field('contact_address', 0.05, 0, 1),
    field('new_days', 1, 1),
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
