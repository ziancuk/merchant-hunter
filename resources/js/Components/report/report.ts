// Pure helpers for the Laporan (report) page: URL building and column definitions. No Vue, no network.
import { filterToQuery } from '@/composables/useFilters';
import type { PipelineStatus, ProspectFilter, ScoreBand } from '@/types';

export type ReportSortKey =
    | 'score'
    | 'name'
    | 'category'
    | 'city'
    | 'province'
    | 'rating'
    | 'review_count'
    | 'status'
    | 'last_contacted_at'
    | 'distance';
export type SortDir = 'asc' | 'desc';

export interface ReportRow {
    id: number;
    name: string;
    category: string | null;
    city: string | null;
    province: string | null;
    address: string | null;
    phone: string | null;
    phone_simulated: boolean;
    rating: number | null;
    review_count: number | null;
    score: number;
    score_band: ScoreBand;
    pipeline_status: PipelineStatus;
    handled_by: string | null;
    last_contacted_at: string | null;
    distance_m: number | null;
}

export interface ReportMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface ReportColumn {
    key: ReportSortKey | 'address' | 'phone' | 'handled_by' | 'map';
    /** i18n key; resolve with t() at render time so headers follow the active language. */
    labelKey: string;
    sortKey?: ReportSortKey;
    /** Descending first (numbers, recency) vs ascending first (text). */
    firstDir?: SortDir;
    align?: 'right';
}

export const REPORT_COLUMNS: ReportColumn[] = [
    { key: 'name', labelKey: 'report.col.name', sortKey: 'name', firstDir: 'asc' },
    { key: 'category', labelKey: 'report.col.category', sortKey: 'category', firstDir: 'asc' },
    { key: 'city', labelKey: 'report.col.city', sortKey: 'city', firstDir: 'asc' },
    { key: 'province', labelKey: 'report.col.province', sortKey: 'province', firstDir: 'asc' },
    { key: 'address', labelKey: 'report.col.address' },
    { key: 'phone', labelKey: 'report.col.phone' },
    { key: 'rating', labelKey: 'report.col.rating', sortKey: 'rating', firstDir: 'desc', align: 'right' },
    { key: 'review_count', labelKey: 'report.col.review_count', sortKey: 'review_count', firstDir: 'desc', align: 'right' },
    { key: 'score', labelKey: 'report.col.score', sortKey: 'score', firstDir: 'desc', align: 'right' },
    { key: 'status', labelKey: 'report.col.status', sortKey: 'status', firstDir: 'asc' },
    { key: 'handled_by', labelKey: 'report.col.handled_by' },
    { key: 'last_contacted_at', labelKey: 'report.col.last_contacted_at', sortKey: 'last_contacted_at', firstDir: 'desc' },
    { key: 'distance', labelKey: 'report.col.distance', sortKey: 'distance', firstDir: 'asc', align: 'right' },
];

function join(...parts: (string | false | undefined | null)[]): string {
    return parts.filter(Boolean).join('&');
}

export function buildReportUrl(filter: ProspectFilter, opts: { sort: ReportSortKey; dir: SortDir; page: number; perPage: number }): string {
    return `/api/reports/prospects?${join(filterToQuery(filter), `sort=${opts.sort}`, `dir=${opts.dir}`, `page=${opts.page}`, `per_page=${opts.perPage}`)}`;
}

/** Download URL of the filtered prospects in the given format (same filter as the table). */
export function buildDownloadUrl(format: 'csv' | 'xlsx', filter: ProspectFilter): string {
    const q = filterToQuery(filter);
    return `/prospects/export.${format}${q ? `?${q}` : ''}`;
}

/** Browser URL of the page itself (kept in sync with the filter so a view can be bookmarked or shared). */
export function buildReportPageUrl(filter: ProspectFilter): string {
    const q = filterToQuery(filter);
    return q ? `/reports?${q}` : '/reports';
}

export function buildMapUrl(id: number): string {
    return `/?prospect=${id}`;
}
