import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api');
    return { ...actual, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

import { api } from '@/lib/api';
import CategoryEditor from './CategoryEditor.vue';
import DataSourceRow from './DataSourceRow.vue';
import OutletImport from './OutletImport.vue';
import { validateTemplate } from './template';
import { diffParams, mergeParams, SCORING_DEFAULTS, type ScoringParams } from './scoring';
import { formatDuration } from './duration';
import type { CategoryRow, DataSource } from './types';

const mocked = api as unknown as Record<'get' | 'post' | 'put' | 'delete', ReturnType<typeof vi.fn>>;
let wrapper: VueWrapper | null = null;
beforeEach(() => Object.values(mocked).forEach((f) => f.mockReset()));
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

const google = (over: Partial<DataSource> = {}): DataSource => ({
    id: 2,
    key: 'google',
    name: 'Google Places',
    is_active: false,
    priority: 5,
    has_key: false,
    key_hint: null,
    monthly_quota: 5000,
    calls_this_month: 0,
    cost_search_per_1000: 32,
    cost_details_per_1000: 17,
    last_run_at: null,
    last_status: null,
    last_error: null,
    config: null,
    ...over,
});
const unprocessable = (errors: Record<string, string[]>, message = 'invalid') => ({ isAxiosError: true, response: { status: 422, data: { message, errors } } });

describe('DataSourceRow', () => {
    it('never shows the key after saving: input is cleared and only the hint is displayed', async () => {
        mocked.put.mockResolvedValue({ data: google({ has_key: true, key_hint: '…1234' }) });
        wrapper = mount(DataSourceRow, { props: { source: google() } });
        const input = wrapper.get('[data-testid="ds-key-input-google"]');
        expect(input.attributes('type')).toBe('password');
        await input.setValue('SUPER-SECRET-1234');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(mocked.put).toHaveBeenCalledWith('/api/master/data-sources/2', { api_key: 'SUPER-SECRET-1234' });
        expect((input.element as HTMLInputElement).value).toBe('');
        expect(wrapper.html()).not.toContain('SUPER-SECRET-1234');
        await wrapper.setProps({ source: google({ has_key: true, key_hint: '…1234' }) });
        expect(wrapper.get('[data-testid="ds-keystate-google"]').text()).toContain('…1234');
        expect(wrapper.html()).not.toContain('SUPER-SECRET-1234');
    });

    it('clears the typed key even when saving fails', async () => {
        mocked.put.mockRejectedValue(unprocessable({ api_key: ['Key tidak valid.'] }));
        wrapper = mount(DataSourceRow, { props: { source: google() } });
        await wrapper.get('[data-testid="ds-key-input-google"]').setValue('BAD-KEY-9999');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect((wrapper.get('[data-testid="ds-key-input-google"]').element as HTMLInputElement).value).toBe('');
        expect(wrapper.html()).not.toContain('BAD-KEY-9999');
    });

    it('toggle sends is_active and shows the 422 reason when Google has no key', async () => {
        mocked.put.mockRejectedValue(unprocessable({ is_active: ['Google Places tidak dapat diaktifkan tanpa API key.'] }));
        wrapper = mount(DataSourceRow, { props: { source: google() } });
        await wrapper.get('[data-testid="ds-toggle-google"]').trigger('click');
        await flushPromises();
        expect(mocked.put).toHaveBeenCalledWith('/api/master/data-sources/2', { is_active: true });
        expect(wrapper.get('[data-testid="ds-error-google"]').text()).toContain('tanpa API key');
        expect(wrapper.get('[data-testid="ds-toggle-google"]').attributes('aria-checked')).toBe('false');
        expect(wrapper.emitted('updated')).toBeUndefined();
    });

    it('toggle emits the updated source on success', async () => {
        mocked.put.mockResolvedValue({ data: google({ is_active: true, has_key: true, key_hint: '…1234' }) });
        wrapper = mount(DataSourceRow, { props: { source: google({ has_key: true, key_hint: '…1234' }) } });
        await wrapper.get('[data-testid="ds-toggle-google"]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('updated')?.[0][0]).toMatchObject({ is_active: true });
    });

    it('tests the connection: loading, then ok result with latency; and failure text', async () => {
        let resolve!: (v: unknown) => void;
        mocked.post.mockReturnValue(new Promise((r) => (resolve = r)));
        wrapper = mount(DataSourceRow, { props: { source: google({ has_key: true, key_hint: '…1234' }) } });
        await wrapper.get('[data-testid="ds-test-google"]').trigger('click');
        expect(wrapper.get('[data-testid="ds-test-result-google"]').text()).toContain('Menguji koneksi');
        resolve({ data: { ok: true, message: 'Terhubung', latency_ms: 142 } });
        await flushPromises();
        const text = wrapper.get('[data-testid="ds-test-result-google"]').text();
        expect(text).toContain('Berhasil');
        expect(text).toContain('142 ms');
        expect(mocked.post).toHaveBeenCalledWith('/api/master/data-sources/2/test', {});

        mocked.post.mockRejectedValue({ isAxiosError: true, response: { status: 429, data: { message: 'Terlalu banyak.' } } });
        await wrapper.get('[data-testid="ds-test-google"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="ds-test-result-google"]').text()).toContain('Gagal');
    });
});

describe('OutletImport', () => {
    it('uploads multipart and renders the summary and row errors', async () => {
        mocked.post.mockResolvedValue({
            data: { created: 3, updated: 1, geocoded: 2, needs_review: 1, failed: 1, errors: [{ row: 5, message: 'Provinsi tidak dikenal.' }] },
        });
        wrapper = mount(OutletImport);
        const file = new File(['code,name\n'], 'outlet.csv', { type: 'text/csv' });
        const input = wrapper.get('[data-testid="import-file"]');
        Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
        await input.trigger('change');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        const [url, body] = mocked.post.mock.calls[0];
        expect(url).toBe('/api/master/outlets/import');
        expect((body as FormData).get('file')).toBeInstanceOf(File);
        expect(wrapper.get('[data-testid="import-created"]').text()).toContain('3');
        expect(wrapper.get('[data-testid="import-needs_review"]').text()).toContain('1');
        expect(wrapper.get('[data-testid="import-errors"]').text()).toContain('Baris 5');
        expect(wrapper.get('[data-testid="import-errors"]').text()).toContain('Provinsi tidak dikenal.');
        expect(wrapper.emitted('imported')).toHaveLength(1);
    });

    it('rejects files over 2 MB before uploading and links a sample format', async () => {
        wrapper = mount(OutletImport);
        const big = { size: 3 * 1024 * 1024, name: 'big.csv' } as File;
        const input = wrapper.get('[data-testid="import-file"]');
        Object.defineProperty(input.element, 'files', { value: [big], configurable: true });
        await input.trigger('change');
        expect(wrapper.get('[data-testid="import-error"]').text()).toContain('2 MB');
        expect(wrapper.get('[data-testid="import-submit"]').attributes('disabled')).toBeDefined();
        expect(decodeURIComponent(wrapper.get('[data-testid="import-sample"]').attributes('href')!)).toContain('code,name,address,city,province,lat,lng');
    });
});

describe('Category template validation', () => {
    it.each([
        ['Halo {nama_bisnis}', null],
        ['Halo {nama_bisnis} dari {nama_pengirim} soal {kategori}', null],
    ])('accepts %s', (t, expected) => expect(validateTemplate(t)).toBe(expected));

    it('rejects empty, placeholder-less and unknown placeholders', () => {
        expect(validateTemplate('')).toContain('wajib');
        expect(validateTemplate('Halo saja')).toContain('minimal satu placeholder');
        expect(validateTemplate('Halo {nama_bisnis} {harga}')).toContain('{harga}');
    });

    const row: CategoryRow = { id: 1, key: 'cafe', name: 'Kafe', weight: 80, whatsapp_template: 'Halo {nama_bisnis}', osm_filters: [], google_types: null, is_active: true, sort_order: 1 };

    it('does not call the API when the template or weight is invalid', async () => {
        wrapper = mount(CategoryEditor, { props: { category: row } });
        await wrapper.get('[data-testid="category-template"]').setValue('Halo {salah}');
        await wrapper.get('[data-testid="category-weight"]').setValue('150');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(mocked.put).not.toHaveBeenCalled();
        const html = wrapper.text();
        expect(html).toContain('Placeholder tidak dikenal: {salah}');
        expect(html).toContain('Bobot harus bilangan bulat 0–100');
    });

    it('saves a valid category with the full payload', async () => {
        mocked.put.mockResolvedValue({ data: { ...row, weight: 90 } });
        wrapper = mount(CategoryEditor, { props: { category: row } });
        await wrapper.get('[data-testid="category-weight"]').setValue('90');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(mocked.put).toHaveBeenCalledWith('/api/master/categories/1', { key: 'cafe', name: 'Kafe', weight: 90, whatsapp_template: 'Halo {nama_bisnis}', is_active: true });
        expect(wrapper.emitted('saved')?.[0]).toEqual([{ ...row, weight: 90 }, false]);
    });
});

describe('scoring params', () => {
    it('diff keeps only overrides and merge restores the defaults', () => {
        const defaults = SCORING_DEFAULTS as unknown as ScoringParams;
        const merged = mergeParams(defaults, { weights: { category: 30 }, new_days: 14 });
        expect((merged.weights as Record<string, number>).category).toBe(30);
        expect((merged.weights as Record<string, number>).quality).toBe(20);
        expect(diffParams(merged, defaults)).toEqual({ weights: { category: 30 }, new_days: 14 });
        expect(diffParams(mergeParams(defaults, {}), defaults)).toEqual({});
    });
});

describe('formatDuration', () => {
    it('formats ms', () => {
        expect(formatDuration(null)).toBe('–');
        expect(formatDuration(65_000)).toBe('1 mnt 5 dtk');
        expect(formatDuration(9_000)).toBe('9 dtk');
    });
});
