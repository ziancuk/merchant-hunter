import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import ProspectDrawer from './ProspectDrawer.vue';
import { api } from '@/lib/api';
import { mockMatchMedia, prospectFixture } from '@/lib/testing';

vi.mock('@/lib/api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/api')>()),
    api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

const post = vi.mocked(api.post);
const reasons = [{ id: 7, key: 'price', label: 'Harga terlalu mahal' }];
let wrapper: VueWrapper | null = null;

function mountDrawer(loader: (id: number) => Promise<ReturnType<typeof prospectFixture>>) {
    wrapper = mount(ProspectDrawer, { props: { prospectId: 101, loader, rejectionReasons: reasons, aiEnabled: false }, attachTo: document.body });
    return wrapper;
}

beforeEach(() => {
    mockMatchMedia(false);
    post.mockReset();
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('ProspectDrawer', () => {
    it('shows a loading skeleton, then the prospect', async () => {
        let resolve!: (v: ReturnType<typeof prospectFixture>) => void;
        const loader = vi.fn(() => new Promise<ReturnType<typeof prospectFixture>>((r) => (resolve = r)));
        const w = mountDrawer(loader);
        await flushPromises();
        expect(w.find('[data-testid="drawer-loading"]').exists()).toBe(true);
        resolve(prospectFixture());
        await flushPromises();
        expect(w.find('[data-testid="drawer-loading"]').exists()).toBe(false);
        expect(loader).toHaveBeenCalledWith(101);
        expect(w.get('[role="dialog"]').text()).toContain('Kopi Senja');
    });

    it('renders badges, score breakdown, nearest outlet and simulated fields', async () => {
        const w = mountDrawer(async () => prospectFixture({ badges: ['new_open', 'simulated', 'closed', 'opt_out'], lifecycle_status: 'closed' }));
        await flushPromises();
        const text = w.get('[data-testid="drawer-content"]').text();
        expect(text).toContain('Baru buka');
        expect(text).toContain('Tutup');
        expect(text).toContain('Jangan hubungi');
        expect(text).toContain('Kecocokan kategori');
        expect(text).toContain('Toko Sinar Jaya');
        expect(text).toContain('640 m');
        expect(text).toContain('2 prospek lain dalam 500 m');
        // header badge + rating + reviews are simulated
        expect(w.findAll('[data-testid="simulasi-badge"]').length).toBeGreaterThanOrEqual(3);
    });

    it('shows coordinates and "Alamat belum tersedia" when the address is missing', async () => {
        const w = mountDrawer(async () => prospectFixture({ address: null, address_source: 'none' }));
        await flushPromises();
        const box = w.get('[data-testid="address-missing"]');
        expect(box.text()).toContain('Alamat belum tersedia');
        expect(box.text()).toContain('-6.21000, 106.81000');
    });

    it('shows an error state and retries', async () => {
        const loader = vi.fn().mockRejectedValueOnce(new Error('Gagal jaringan')).mockResolvedValueOnce(prospectFixture());
        const w = mountDrawer(loader);
        await flushPromises();
        const err = w.get('[data-testid="error-state"]');
        expect(err.text()).toContain('Gagal jaringan');
        await err.get('button').trigger('click');
        await flushPromises();
        expect(loader).toHaveBeenCalledTimes(2);
        expect(w.find('[data-testid="drawer-content"]').exists()).toBe(true);
    });

    it('requires a rejection reason when marking as lost', async () => {
        const w = mountDrawer(async () => prospectFixture());
        await flushPromises();
        await w.get('[data-testid="status-lost"]').trigger('click');
        const picker = w.get('[data-testid="lost-picker"]');
        expect(post).not.toHaveBeenCalled();
        expect(picker.get('[data-testid="confirm-lost"]').attributes('disabled')).toBeDefined();
        await picker.get('select').setValue(7);
        post.mockResolvedValueOnce({ data: { prospect: prospectFixture({ pipeline_status: 'lost' }) } } as never);
        await picker.get('[data-testid="confirm-lost"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/101/status', { status: 'lost', rejection_reason_id: 7 });
        expect(w.emitted('updated')?.[0][0]).toMatchObject({ pipeline_status: 'lost' });
    });

    it('changes status directly and shows API errors', async () => {
        const w = mountDrawer(async () => prospectFixture());
        await flushPromises();
        post.mockRejectedValueOnce(new Error('Status ditolak'));
        await w.get('[data-testid="status-demo"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/101/status', { status: 'demo' });
        expect(w.get('[data-testid="action-error"]').text()).toContain('Status ditolak');
    });

    it('saves a note and prepends the activity', async () => {
        const w = mountDrawer(async () => prospectFixture());
        await flushPromises();
        await w.get('#note-input').setValue('Pemilik minta telepon besok');
        post.mockResolvedValueOnce({ data: { activity: { id: 9, type: 'note', user: { id: 1, name: 'Ayu' }, body: 'Pemilik minta telepon besok', created_at: '2026-10-25T01:00:00Z' } } } as never);
        await w.get('[data-testid="save-note"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith('/api/prospects/101/notes', { body: 'Pemilik minta telepon besok' });
        expect(w.get('[data-testid="latest-note"]').text()).toContain('besok');
        expect(w.findAll('[data-testid="activity"]')[0].text()).toContain('Catatan');
    });

    it('disables WhatsApp for opt-out prospects', async () => {
        const w = mountDrawer(async () => prospectFixture({ do_not_contact: true, whatsapp_url: null, badges: ['opt_out'] }));
        await flushPromises();
        expect(w.get('[data-testid="wa-button"]').attributes('disabled')).toBeDefined();
        expect(w.get('[data-testid="wa-reason"]').text()).toContain('Jangan hubungi');
    });

    it('emits close on Escape', async () => {
        const w = mountDrawer(async () => prospectFixture());
        await flushPromises();
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(w.emitted('close')).toBeTruthy();
    });
});
