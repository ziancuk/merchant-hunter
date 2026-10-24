import { computed, ref } from 'vue';
import type { PipelineStatus, ProspectFilter } from '@/types';
import { PIPELINE_STATUSES } from '@/types';

type Scalar = string | number;
export type FilterParams = Record<string, Scalar | Scalar[]>;

const NUMERIC_KEYS = ['province_id', 'score_min', 'score_max', 'rating_min', 'max_distance_m'] as const;
const FLAG_KEYS = ['has_phone', 'new_open_only', 'include_closed'] as const;

function isEmpty(v: unknown): boolean {
    if (v === undefined || v === null || v === '') return true;
    if (typeof v === 'number' && Number.isNaN(v)) return true;
    if (Array.isArray(v) && v.length === 0) return true;
    return false;
}

/** Filter → API params. Arrays use the `key[]` form; empty values are dropped. */
export function filterToParams(filter: ProspectFilter): FilterParams {
    const out: FilterParams = {};
    for (const [key, value] of Object.entries(filter)) {
        if (isEmpty(value)) continue;
        if (Array.isArray(value)) {
            const items = value.filter((x) => !isEmpty(x)) as Scalar[];
            if (items.length) out[`${key}[]`] = items;
        } else {
            out[key] = value as Scalar;
        }
    }
    return out;
}

/** Filter → query string (`category_id[]=1&category_id[]=2&score_min=60`), keys sorted for stable URLs. */
export function filterToQuery(filter: ProspectFilter): string {
    const params = filterToParams(filter);
    const parts: string[] = [];
    for (const key of Object.keys(params).sort()) {
        const v = params[key];
        for (const item of Array.isArray(v) ? v : [v]) parts.push(`${key}=${encodeURIComponent(String(item))}`);
    }
    return parts.join('&');
}

/** Query string / URLSearchParams → filter. Unknown keys and invalid values are ignored. */
export function queryToFilter(query: string | URLSearchParams): ProspectFilter {
    const sp = typeof query === 'string' ? new URLSearchParams(query.replace(/^\?/, '')) : query;
    const get = (k: string) => sp.get(k) ?? sp.get(`${k}[]`);
    const all = (k: string) => [...sp.getAll(`${k}[]`), ...sp.getAll(k)];
    const num = (v: string | null): number | undefined => {
        if (v === null || v.trim() === '') return undefined;
        const n = Number(v);
        return Number.isFinite(n) ? n : undefined;
    };
    const filter: ProspectFilter = {};
    for (const k of NUMERIC_KEYS) {
        const n = num(get(k));
        if (n !== undefined) filter[k] = n;
    }
    for (const k of FLAG_KEYS) {
        const n = num(get(k));
        if (n === 0 || n === 1) filter[k] = n;
    }
    const city = get('city');
    if (city) filter.city = city;
    const cats = all('category_id')
        .map((x) => num(x))
        .filter((x): x is number => x !== undefined);
    if (cats.length) filter.category_id = cats;
    const statuses = all('pipeline_status').filter((x): x is PipelineStatus => (PIPELINE_STATUSES as string[]).includes(x));
    if (statuses.length) filter.pipeline_status = statuses;
    return filter;
}

/** Number of active filter groups (for the "N filter aktif" indicator). */
export function countActiveFilters(filter: ProspectFilter): number {
    return Object.keys(filterToParams(filter)).length;
}

/** Reactive filter state with query conversion. */
export function useFilters(initial: ProspectFilter = {}) {
    const filter = ref<ProspectFilter>({ ...initial });
    const params = computed(() => filterToParams(filter.value));
    const query = computed(() => filterToQuery(filter.value));
    const activeCount = computed(() => countActiveFilters(filter.value));
    function set(patch: Partial<ProspectFilter>): void {
        const next: ProspectFilter = { ...filter.value, ...patch };
        for (const k of Object.keys(next) as (keyof ProspectFilter)[]) if (isEmpty(next[k])) delete next[k];
        filter.value = next;
    }
    function reset(): void {
        filter.value = {};
    }
    function fromQuery(q: string | URLSearchParams): void {
        filter.value = queryToFilter(q);
    }
    return { filter, params, query, activeCount, set, reset, fromQuery };
}
