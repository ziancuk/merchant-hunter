import { ref, type Ref } from 'vue';
import { api, normalizeError } from '@/lib/api';
import type { ProspectFilter } from '@/types';
import { buildReportUrl, REPORT_COLUMNS, type ReportMeta, type ReportRow, type ReportSortKey, type SortDir } from './report';

/** Report table state: server-side sort + pagination; stale responses are dropped via a request token. */
export function useReportTable(filter: Ref<ProspectFilter>) {
    const rows = ref<ReportRow[]>([]);
    const meta = ref<ReportMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
    const sort = ref<ReportSortKey>('score');
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
            const res = await api.get<{ data: ReportRow[]; meta: ReportMeta }>(buildReportUrl(filter.value, { sort: sort.value, dir: dir.value, page: page.value, perPage: perPage.value }));
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

    /** Clicking the active column flips the direction; a new column starts in its natural direction. */
    function setSort(key: ReportSortKey): Promise<void> {
        if (sort.value === key) dir.value = dir.value === 'asc' ? 'desc' : 'asc';
        else {
            sort.value = key;
            dir.value = REPORT_COLUMNS.find((c) => c.sortKey === key)?.firstDir ?? 'desc';
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

    return { rows, meta, sort, dir, page, perPage, loading, loaded, error, fetch, setSort, setPage, setPerPage, resetPage };
}
