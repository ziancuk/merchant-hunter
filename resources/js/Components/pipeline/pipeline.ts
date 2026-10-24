// Pure helpers for the pipeline page (URL building, card merging, labels). No Vue, no network.
import { filterToQuery } from '@/composables/useFilters';
import { PIPELINE_STATUSES, type PipelineCard, type PipelineStatus, type ProspectDetail, type ProspectFilter } from '@/types';

export type PipelineView = 'kanban' | 'table';
export type SortKey = 'score' | 'last_contacted_at' | 'name';
export type SortDir = 'asc' | 'desc';

export interface BoardColumn {
    total: number;
    cards: PipelineCard[];
    next_cursor: string | null;
}
export type BoardColumns = Record<PipelineStatus, BoardColumn>;

export interface TableMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

/** Pipeline wording: "Deal"/"Ditolak" (PRD US-13); the drawer keeps Menang/Kalah from lib/colors. */
export const COLUMN_LABEL: Record<PipelineStatus, string> = {
    new: 'Baru',
    contacted: 'Dihubungi',
    demo: 'Demo',
    won: 'Deal',
    lost: 'Ditolak',
};

export const MAX_ROUTE_STOPS = 30;
export const MIN_ROUTE_STOPS = 2;
export const CARDS_PER_STATUS = 50;

export const SKIP_REASON_LABEL: Record<string, string> = {
    not_found: 'Prospek tidak ditemukan',
    already_opted_out: 'Sudah ditandai jangan hubungi',
    same_status: 'Status sudah sama',
};

export function skipReasonLabel(reason: string): string {
    return SKIP_REASON_LABEL[reason] ?? reason;
}

export function emptyColumns(): BoardColumns {
    return Object.fromEntries(PIPELINE_STATUSES.map((s) => [s, { total: 0, cards: [], next_cursor: null }])) as unknown as BoardColumns;
}

/** Fills missing statuses so the board always has five columns. */
export function normalizeColumns(raw: Partial<Record<PipelineStatus, Partial<BoardColumn>>> | null | undefined): BoardColumns {
    const out = emptyColumns();
    for (const s of PIPELINE_STATUSES) {
        const c = raw?.[s];
        if (c) out[s] = { total: c.total ?? c.cards?.length ?? 0, cards: [...(c.cards ?? [])], next_cursor: c.next_cursor ?? null };
    }
    return out;
}

function join(...parts: (string | false | undefined | null)[]): string {
    return parts.filter(Boolean).join('&');
}

export function buildCardsUrl(filter: ProspectFilter, extra: { status?: PipelineStatus; cursor?: string | null; perStatus?: number } = {}): string {
    const query = join(
        filterToQuery(filter),
        extra.status && `status=${extra.status}`,
        `per_status=${extra.perStatus ?? CARDS_PER_STATUS}`,
        extra.cursor && `cursor=${encodeURIComponent(extra.cursor)}`,
    );
    return `/api/pipeline/cards?${query}`;
}

export function buildTableUrl(filter: ProspectFilter, opts: { sort: SortKey; dir: SortDir; page: number; perPage: number }): string {
    return `/api/pipeline/table?${join(filterToQuery(filter), `sort=${opts.sort}`, `dir=${opts.dir}`, `page=${opts.page}`, `per_page=${opts.perPage}`)}`;
}

/** CSV export of the filtered prospects (same filter as the views). */
export function buildExportUrl(filter: ProspectFilter): string {
    const q = filterToQuery(filter);
    return q ? `/prospects/export.csv?${q}` : '/prospects/export.csv';
}

/** Browser URL of the page itself (kept in sync with view + filter). */
export function buildPageUrl(view: PipelineView, filter: ProspectFilter): string {
    return `/pipeline?${join(`view=${view}`, filterToQuery(filter))}`;
}

export function buildRouteUrl(ids: number[]): string {
    return `/?route=${ids.slice(0, MAX_ROUTE_STOPS).join(',')}`;
}

export function buildMapUrl(id: number): string {
    return `/?prospect=${id}`;
}

/** Cards are ordered by score desc, id desc (same as the server's keyset order). */
export function insertSorted(cards: PipelineCard[], card: PipelineCard): PipelineCard[] {
    const next = cards.filter((c) => c.id !== card.id);
    const at = next.findIndex((c) => c.score < card.score || (c.score === card.score && c.id < card.id));
    next.splice(at === -1 ? next.length : at, 0, card);
    return next;
}

/** Merges the fields a ProspectDetail knows about onto an existing card (city is not part of detail, so it stays). */
export function mergeDetail(card: PipelineCard, detail: ProspectDetail): PipelineCard {
    return {
        ...card,
        name: detail.name,
        category: detail.category.name,
        score: detail.score,
        score_band: detail.score_band,
        phone_present: !!detail.phone,
        last_contacted_at: detail.last_contacted_at,
        handled_by: detail.handled_by?.name ?? null,
        badges: detail.badges,
        do_not_contact: detail.do_not_contact,
        ...(card.pipeline_status !== undefined ? { pipeline_status: detail.pipeline_status } : {}),
    };
}
