import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import KanbanBoard from './KanbanBoard.vue';
import { normalizeColumns } from './pipeline';
import { cardFixture, mockMatchMedia } from '@/lib/testing';

let wrapper: VueWrapper | null = null;
const columns = () =>
    normalizeColumns({
        new: { total: 120, cards: [cardFixture({ id: 1, name: 'Kopi Senja' }), cardFixture({ id: 2, name: 'Toko Roti', phone_present: false })], next_cursor: '70:2' },
        contacted: { total: 1, cards: [cardFixture({ id: 3, name: 'Warung Sari', handled_by: 'Ayu' })] },
    });

function mountBoard(props: Record<string, unknown> = {}) {
    wrapper = mount(KanbanBoard, { props: { columns: columns(), ...props }, attachTo: document.body });
    return wrapper;
}

beforeEach(() => mockMatchMedia(false));
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.innerHTML = '';
});

describe('KanbanBoard', () => {
    it('renders five columns with totals and cards', () => {
        const w = mountBoard();
        for (const s of ['new', 'contacted', 'demo', 'won', 'lost']) expect(w.find(`[data-testid="kanban-col-${s}"]`).exists()).toBe(true);
        expect(w.get('[data-testid="kanban-count-new"]').text()).toBe('120');
        expect(w.get('[data-testid="kanban-col-new"]').findAll('[data-testid^="kanban-card-"]')).toHaveLength(2);
        expect(w.get('[data-testid="kanban-col-contacted"]').text()).toContain('Warung Sari');
        expect(w.get('[data-testid="kanban-col-lost"]').text()).toContain('Tidak ada prospek');
        expect(w.get('[data-testid="kanban-col-won"]').text()).toContain('Deal');
        expect(w.get('[data-testid="kanban-col-lost"]').text()).toContain('Ditolak');
    });

    it('shows a "muat lagi" button only for columns with a cursor and emits load-more', async () => {
        const w = mountBoard();
        expect(w.find('[data-testid="kanban-more-contacted"]').exists()).toBe(false);
        await w.get('[data-testid="kanban-more-new"]').trigger('click');
        expect(w.emitted('load-more')).toEqual([['new']]);
        expect(w.get('[data-testid="kanban-more-new"]').text()).toContain('118 lagi');
    });

    it('emits move on drop into another column and ignores drops on the same column', async () => {
        const w = mountBoard();
        await w.get('[data-testid="kanban-card-1"]').trigger('dragstart');
        await w.get('[data-testid="kanban-col-demo"]').trigger('dragover');
        await w.get('[data-testid="kanban-col-demo"]').trigger('drop');
        expect(w.emitted('move')).toEqual([[1, 'new', 'demo']]);

        await w.get('[data-testid="kanban-card-1"]').trigger('dragstart');
        await w.get('[data-testid="kanban-col-new"]').trigger('drop');
        expect(w.emitted('move')).toHaveLength(1);
    });

    it('ignores a drop that did not start from a card', async () => {
        const w = mountBoard();
        await w.get('[data-testid="kanban-col-demo"]').trigger('drop');
        expect(w.emitted('move')).toBeUndefined();
    });

    it('offers a keyboard alternative: "Pindahkan ke…" menu', async () => {
        const w = mountBoard();
        await w.get('[data-testid="move-menu-1"]').trigger('click');
        const menu = document.body.querySelector('[role="menu"]');
        expect(menu).not.toBeNull();
        expect(Array.from(menu!.querySelectorAll('[role="menuitem"]')).map((e) => e.textContent?.trim())).toEqual(['Dihubungi', 'Demo', 'Deal', 'Ditolak']);
        (document.body.querySelector('[data-testid="move-to-demo"]') as HTMLElement).click();
        await w.vm.$nextTick();
        expect(w.emitted('move')).toEqual([[1, 'new', 'demo']]);
        expect(document.body.querySelector('[role="menu"]')).toBeNull();
    });

    it('closes the move menu with Escape', async () => {
        const w = mountBoard();
        await w.get('[data-testid="move-menu-1"]').trigger('click');
        document.body.querySelector('[role="menu"]')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await w.vm.$nextTick();
        expect(document.body.querySelector('[role="menu"]')).toBeNull();
    });

    it('emits open on card click and toggle-select on the checkbox', async () => {
        const w = mountBoard({ selected: [2] });
        await w.get('[data-testid="kanban-card-1"] [data-testid="prospect-card"]').trigger('click');
        expect(w.emitted('open')).toEqual([[1]]);
        expect((w.get('[data-testid="select-2"]').element as HTMLInputElement).checked).toBe(true);
        await w.get('[data-testid="select-1"]').trigger('change');
        expect(w.emitted('toggle-select')).toEqual([[1]]);
    });

    it('shows skeleton cards while loading with no data, and marks refreshing as busy', () => {
        const empty = mountBoard({ columns: normalizeColumns(null), loading: true });
        expect(empty.findAll('[data-testid="kanban-skeleton"]').length).toBeGreaterThan(0);
        expect(empty.get('[data-testid="kanban-board"]').attributes('aria-busy')).toBe('true');
        empty.unmount();
        const refreshing = mountBoard({ refreshing: true });
        expect(refreshing.get('[data-testid="kanban-board"]').attributes('aria-busy')).toBe('true');
        expect(refreshing.find('[data-testid="kanban-skeleton"]').exists()).toBe(false);
    });
});
