import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import WhatsAppComposer from './WhatsAppComposer.vue';
import { api } from '@/lib/api';
import { checkFixture, mockMatchMedia, prospectFixture } from '@/lib/testing';

vi.mock('@/lib/api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/api')>()),
    api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

const post = vi.mocked(api.post);
let openSpy: ReturnType<typeof vi.fn>;
let fetchSpy: ReturnType<typeof vi.fn>;

async function mountWith(check: ReturnType<typeof checkFixture>, props: Record<string, unknown> = {}) {
    post.mockResolvedValueOnce({ data: check } as never);
    const w = mount(WhatsAppComposer, { props: { prospectId: 101, aiEnabled: false, ...props } });
    await flushPromises();
    return w;
}

beforeEach(() => {
    mockMatchMedia(false);
    post.mockReset();
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => null) as unknown as ReturnType<typeof vi.fn>;
    fetchSpy = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ prospect: prospectFixture({ pipeline_status: 'contacted' }) }) });
    vi.stubGlobal('fetch', fetchSpy);
});
afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('WhatsAppComposer', () => {
    it('loads the contact check and fills the editable message', async () => {
        const w = await mountWith(checkFixture());
        expect(post).toHaveBeenCalledWith('/api/prospects/101/contact-check', {});
        expect((w.get('[data-testid="wa-message"]').element as HTMLTextAreaElement).value).toBe('Halo Kopi Senja, kami dari Hitachi.');
        expect(w.find('[data-testid="wa-warning"]').exists()).toBe(false);
        expect(w.find('[data-testid="wa-ai"]').exists()).toBe(false);
    });

    it('opens wa.me synchronously in the click handler, then logs with keepalive', async () => {
        const w = await mountWith(checkFixture());
        await w.get('[data-testid="wa-message"]').setValue('Halo & selamat siang');
        w.get('[data-testid="wa-send"]').element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        // No await between the click and the assertion: window.open must already have been called.
        expect(openSpy).toHaveBeenCalledTimes(1);
        expect(openSpy.mock.calls[0][0]).toBe('https://wa.me/6281255550101?text=Halo%20%26%20selamat%20siang');
        expect(openSpy.mock.invocationCallOrder[0]).toBeLessThan(fetchSpy.mock.invocationCallOrder[0]);
        await flushPromises();
        const [url, init] = fetchSpy.mock.calls[0];
        expect(url).toBe('/api/prospects/101/contact');
        expect(init.keepalive).toBe(true);
        expect(init.method).toBe('POST');
        expect(JSON.parse(init.body)).toEqual({ message: 'Halo & selamat siang' });
        expect(w.emitted('contacted')?.[0][0]).toMatchObject({ pipeline_status: 'contacted' });
        expect(w.find('[data-testid="wa-sent"]').exists()).toBe(true);
    });

    it('renders the recent-contact warning with who and when', async () => {
        const w = await mountWith(checkFixture({ warning: { type: 'recent_contact', by: 'Ayu', at: '2026-10-22T03:00:00Z', days_ago: 2 } }));
        const text = w.get('[data-testid="wa-warning"]').text();
        expect(text).toContain('Ayu');
        expect(text).toContain('2 hari lalu');
        expect(text).toContain('22 Okt 2026, 10.00 WIB');
    });

    it('requires an override reason for min-interval violations and shows overrides left', async () => {
        const w = await mountWith(checkFixture({ min_interval_violation: true, override_count: 1, max_overrides: 2 }));
        expect(w.get('[data-testid="wa-overrides-left"]').text()).toContain('Sisa override: 1 dari 2');
        const send = w.get('[data-testid="wa-send"]');
        expect(send.attributes('disabled')).toBeDefined();
        await w.get('[data-testid="wa-override-reason"]').setValue('Pelanggan minta dihubungi');
        expect(w.get('[data-testid="wa-send"]').attributes('disabled')).toBeUndefined();
        await w.get('[data-testid="wa-send"]').trigger('click');
        await flushPromises();
        expect(JSON.parse(fetchSpy.mock.calls[0][1].body)).toEqual({ message: 'Halo Kopi Senja, kami dari Hitachi.', override_reason: 'Pelanggan minta dihubungi' });
    });

    it('blocks sending when overrides are exhausted', async () => {
        const w = await mountWith(checkFixture({ min_interval_violation: true, override_count: 2, max_overrides: 2 }));
        expect(w.get('[data-testid="wa-override"]').text()).toContain('Batas override');
        expect(w.get('[data-testid="wa-send"]').attributes('disabled')).toBeDefined();
        expect(openSpy).not.toHaveBeenCalled();
    });

    it('shows blocked reasons and no send button', async () => {
        const w = await mountWith(checkFixture({ allowed: false, blocked_reason: 'do_not_contact', phone_normalized: null }));
        expect(w.get('[data-testid="wa-blocked"]').text()).toContain('opt-out');
        expect(w.find('[data-testid="wa-send"]').exists()).toBe(false);
    });

    it('shows the AI button only when enabled and fills the message', async () => {
        const w = await mountWith(checkFixture(), { aiEnabled: true });
        post.mockResolvedValueOnce({ data: { message: 'Pesan dari AI' } } as never);
        await w.get('[data-testid="wa-ai"]').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenLastCalledWith('/api/prospects/101/message-ai', {});
        expect((w.get('[data-testid="wa-message"]').element as HTMLTextAreaElement).value).toBe('Pesan dari AI');
    });

    it('offers retry when logging fails after wa.me was opened', async () => {
        fetchSpy.mockResolvedValueOnce({ ok: false, status: 422, json: async () => ({ message: 'Batas frekuensi' }) });
        const w = await mountWith(checkFixture());
        await w.get('[data-testid="wa-send"]').trigger('click');
        await flushPromises();
        expect(openSpy).toHaveBeenCalledTimes(1);
        expect(w.get('[data-testid="wa-log-error"]').text()).toContain('Batas frekuensi');
    });

    it('shows an error state with retry when the check fails', async () => {
        post.mockRejectedValueOnce(new Error('boom'));
        const w = mount(WhatsAppComposer, { props: { prospectId: 101, aiEnabled: false } });
        await flushPromises();
        expect(w.find('[data-testid="error-state"]').exists()).toBe(true);
    });
});
