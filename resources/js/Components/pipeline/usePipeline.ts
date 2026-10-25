import { ref, type Ref } from 'vue';
import { useToasts } from '@/composables/useToasts';
import { t } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import { COLUMN_LABEL } from '@/lib/labels';
import { PIPELINE_STATUSES, type PipelineCard, type PipelineStatus, type ProspectDetail, type ProspectFilter } from '@/types';
import {
    buildCardsUrl,
    buildTableUrl,
    insertSorted,
    mergeDetail,
    normalizeColumns,
    type BoardColumn,
    type BoardColumns,
    type SortDir,
    type SortKey,
    type TableMeta,
} from './pipeline';

type RawColumns = Partial<Record<PipelineStatus, Partial<BoardColumn>>>;

/** Kanban state: columns, (re)loading, cursor paging and optimistic moves with rollback. */
export function usePipelineBoard(initial: RawColumns | null | undefined, filter: Ref<ProspectFilter>) {
    const { push } = useToasts();
    const columns = ref<BoardColumns>(normalizeColumns(initial));
    const loading = ref(false);
    const error = ref('');
    const loadingMore = ref<Partial<Record<PipelineStatus, boolean>>>({});
    let token = 0;

    function reset(raw: RawColumns | null | undefined): void {
        token++;
        columns.value = normalizeColumns(raw);
        error.value = '';
        loading.value = false;
    }

    async function reload(): Promise<void> {
        const mine = ++token;
        loading.value = true;
        error.value = '';
        try {
            const res = await api.get<{ columns: RawColumns }>(buildCardsUrl(filter.value));
            if (mine !== token) return;
            columns.value = normalizeColumns(res.data.columns);
        } catch (e) {
            if (mine !== token) return;
            error.value = normalizeError(e).message;
        } finally {
            if (mine === token) loading.value = false;
        }
    }

    async function loadMore(status: PipelineStatus): Promise<void> {
        const col = columns.value[status];
        if (!col.next_cursor || loadingMore.value[status]) return;
        const mine = token;
        loadingMore.value = { ...loadingMore.value, [status]: true };
        try {
            const res = await api.get<{ columns: RawColumns }>(buildCardsUrl(filter.value, { status, cursor: col.next_cursor }));
            if (mine !== token) return;
            const incoming = res.data.columns[status];
            const seen = new Set(columns.value[status].cards.map((c) => c.id));
            const fresh = (incoming?.cards ?? []).filter((c) => !seen.has(c.id));
            columns.value = {
                ...columns.value,
                [status]: {
                    total: incoming?.total ?? col.total,
                    cards: [...columns.value[status].cards, ...fresh],
                    next_cursor: incoming?.next_cursor ?? null,
                },
            };
        } catch (e) {
            push(normalizeError(e).message, 'error');
        } finally {
            loadingMore.value = { ...loadingMore.value, [status]: false };
        }
    }

    function locate(id: number): PipelineStatus | null {
        for (const s of PIPELINE_STATUSES) if (columns.value[s].cards.some((c) => c.id === id)) return s;
        return null;
    }

    function find(id: number): PipelineCard | null {
        const s = locate(id);
        return s ? (columns.value[s].cards.find((c) => c.id === id) ?? null) : null;
    }

    function transfer(card: PipelineCard, from: PipelineStatus, to: PipelineStatus): void {
        const next = { ...columns.value };
        next[from] = { ...next[from], cards: next[from].cards.filter((c) => c.id !== card.id), total: Math.max(0, next[from].total - 1) };
        next[to] = { ...next[to], cards: insertSorted(next[to].cards, card), total: next[to].total + 1 };
        columns.value = next;
    }

    /** Optimistic move; rolls back and toasts when the API rejects. Resolves true on success. */
    async function move(id: number, to: PipelineStatus, rejectionReasonId?: number): Promise<boolean> {
        const from = locate(id);
        const card = from ? columns.value[from].cards.find((c) => c.id === id) : null;
        if (!from || !card || from === to) return false;

        const snapshot: BoardColumns = { ...columns.value, [from]: { ...columns.value[from], cards: [...columns.value[from].cards] }, [to]: { ...columns.value[to], cards: [...columns.value[to].cards] } };
        transfer(card, from, to);
        try {
            const body: { status: PipelineStatus; rejection_reason_id?: number } = { status: to };
            if (to === 'lost' && rejectionReasonId !== undefined) body.rejection_reason_id = rejectionReasonId;
            const res = await api.post<{ prospect: ProspectDetail }>(`/api/prospects/${id}/status`, body);
            const current = find(id);
            if (current) replaceCard(mergeDetail(current, res.data.prospect));
            return true;
        } catch (e) {
            columns.value = { ...columns.value, [from]: snapshot[from], [to]: snapshot[to] };
            push(t('pipeline.moveFailed', { status: COLUMN_LABEL[to], message: normalizeError(e).message }), 'error', 7000);
            return false;
        }
    }

    function replaceCard(card: PipelineCard): void {
        const s = locate(card.id);
        if (!s) return;
        columns.value = { ...columns.value, [s]: { ...columns.value[s], cards: columns.value[s].cards.map((c) => (c.id === card.id ? card : c)) } };
    }

    /** Reflects a drawer update (status, handled_by, badges…) without a reload. */
    function applyDetail(detail: ProspectDetail): void {
        const from = locate(detail.id);
        const current = from ? find(detail.id) : null;
        if (!from || !current) return;
        const merged = mergeDetail(current, detail);
        if (from !== detail.pipeline_status) transfer(merged, from, detail.pipeline_status);
        else replaceCard(merged);
    }

    return { columns, loading, error, loadingMore, reset, reload, loadMore, move, applyDetail, locate, find };
}

/** Table state: server-side sort + pagination. */
export function usePipelineTable(filter: Ref<ProspectFilter>) {
    const rows = ref<PipelineCard[]>([]);
    const meta = ref<TableMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
    const sort = ref<SortKey>('score');
    const dir = ref<SortDir>('desc');
    const page = ref(1);
    const perPage = ref(25);
    const loading = ref(false);
    const loaded = ref(false);
    const error = ref('');
    let token = 0;

    async function fetch(): Promise<void> {
        const mine = ++token;
        loading.value = true;
        error.value = '';
        try {
            const res = await api.get<{ data: PipelineCard[]; meta: TableMeta }>(buildTableUrl(filter.value, { sort: sort.value, dir: dir.value, page: page.value, perPage: perPage.value }));
            if (mine !== token) return;
            rows.value = res.data.data;
            meta.value = res.data.meta;
            loaded.value = true;
        } catch (e) {
            if (mine !== token) return;
            error.value = normalizeError(e).message;
        } finally {
            if (mine === token) loading.value = false;
        }
    }

    /** Clicking the active column flips the direction; a new column starts descending (score/recent first) or ascending (name). */
    function setSort(key: SortKey): Promise<void> {
        if (sort.value === key) dir.value = dir.value === 'asc' ? 'desc' : 'asc';
        else {
            sort.value = key;
            dir.value = key === 'name' ? 'asc' : 'desc';
        }
        page.value = 1;
        return fetch();
    }

    function setPage(n: number): Promise<void> {
        page.value = Math.min(Math.max(1, n), meta.value.last_page);
        return fetch();
    }

    function setPerPage(n: number): Promise<void> {
        perPage.value = n;
        page.value = 1;
        return fetch();
    }

    function resetPage(): void {
        page.value = 1;
    }

    function applyDetail(detail: ProspectDetail): void {
        rows.value = rows.value.map((c) => (c.id === detail.id ? mergeDetail(c, detail) : c));
    }

    return { rows, meta, sort, dir, page, perPage, loading, loaded, error, fetch, setSort, setPage, setPerPage, resetPage, applyDetail };
}
