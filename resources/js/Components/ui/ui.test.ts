import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import CommandPalette, { type CommandItem } from './CommandPalette.vue';
import Drawer from './Drawer.vue';
import KpiCounter from './KpiCounter.vue';
import ErrorState from './ErrorState.vue';
import EmptyState from './EmptyState.vue';
import Button from './Button.vue';
import { mockMatchMedia } from '@/lib/testing';

let wrapper: VueWrapper | null = null;
afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.useRealTimers();
});
beforeEach(() => mockMatchMedia(false));

describe('KpiCounter', () => {
    it('shows the final value immediately under reduced motion', () => {
        mockMatchMedia(true);
        const raf = vi.spyOn(window, 'requestAnimationFrame');
        wrapper = mount(KpiCounter, { props: { value: 9421, label: 'Prospek aktif' } });
        expect(wrapper.get('[data-testid="kpi-value"]').text()).toBe('9.421');
        expect(raf).not.toHaveBeenCalled();
        raf.mockRestore();
    });

    it('counts up to the value when motion is allowed', async () => {
        let t = 0;
        const callbacks: FrameRequestCallback[] = [];
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => {
            callbacks.push(cb);
            return callbacks.length;
        });
        vi.spyOn(performance, 'now').mockImplementation(() => t);
        wrapper = mount(KpiCounter, { props: { value: 1000, duration: 1000 } });
        expect(wrapper.get('[data-testid="kpi-value"]').text()).toBe('0');
        t = 500;
        callbacks.shift()!(500);
        await flushPromises();
        const mid = Number(wrapper.get('[data-testid="kpi-value"]').text().replace(/\./g, ''));
        expect(mid).toBeGreaterThan(0);
        expect(mid).toBeLessThan(1000);
        t = 1000;
        callbacks.shift()!(1000);
        await flushPromises();
        expect(wrapper.get('[data-testid="kpi-value"]').text()).toBe('1.000');
        vi.restoreAllMocks();
    });
});

describe('Drawer', () => {
    const Host = defineComponent({
        setup() {
            const open = ref(false);
            return () =>
                h('div', [
                    h('button', { id: 'opener', onClick: () => (open.value = true) }, 'buka'),
                    h(Drawer, { modelValue: open.value, 'onUpdate:modelValue': (v: boolean) => (open.value = v), title: 'Detail' }, () => [h('button', { id: 'first' }, 'a'), h('button', { id: 'last' }, 'b')]),
                ]);
        },
    });

    it('is a labelled modal dialog, traps focus, closes on Esc and returns focus', async () => {
        wrapper = mount(Host, { attachTo: document.body });
        const opener = document.getElementById('opener')!;
        opener.focus();
        await opener.click();
        await flushPromises();
        const dialog = document.querySelector('[role="dialog"]')!;
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.getAttribute('aria-labelledby')).toBeTruthy();
        expect(document.getElementById(dialog.getAttribute('aria-labelledby')!)!.textContent).toBe('Detail');

        // first focusable inside is the close button
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Tutup panel');
        const last = document.getElementById('last')!;
        last.focus();
        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Tutup panel');

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(document.activeElement).toBe(opener);
    });
});

describe('CommandPalette', () => {
    const items: CommandItem[] = [
        { id: 'a', label: 'Peta', group: 'Navigasi', hint: 'Radar' },
        { id: 'b', label: 'Pipeline', group: 'Navigasi' },
        { id: 'c', label: 'Ganti tema', group: 'Aksi' },
    ];

    it('filters, navigates with arrows and selects with Enter', async () => {
        const action = vi.fn();
        wrapper = mount(CommandPalette, { props: { open: true, items: [...items.slice(0, 2), { ...items[2], action }] }, attachTo: document.body });
        const input = wrapper.get('input[role="combobox"]');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(3);
        expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true');

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(wrapper.findAll('[role="option"]')[1].attributes('aria-selected')).toBe('true');
        expect(input.attributes('aria-activedescendant')).toBe(wrapper.findAll('[role="option"]')[1].attributes('id'));

        await input.setValue('tema');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
        await input.trigger('keydown', { key: 'Enter' });
        expect(action).toHaveBeenCalled();
        expect(wrapper.emitted('select')?.[0][0]).toMatchObject({ id: 'c' });
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('closes on Escape and shows an empty message', async () => {
        wrapper = mount(CommandPalette, { props: { open: true, items }, attachTo: document.body });
        const input = wrapper.get('input');
        await input.setValue('zzz');
        expect(wrapper.text()).toContain('Tidak ada hasil');
        await input.trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('toggles with Ctrl+K', async () => {
        wrapper = mount(CommandPalette, { props: { open: false, items }, attachTo: document.body });
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true, cancelable: true }));
        expect(wrapper.emitted('update:open')?.[0]).toEqual([true]);
    });

    it('runs the debounced async search and merges remote results', async () => {
        vi.useFakeTimers();
        const search = vi.fn(async (q: string): Promise<CommandItem[]> => [{ id: `p-${q}`, label: `Kopi ${q}`, group: 'Prospek' }]);
        wrapper = mount(CommandPalette, { props: { open: true, items, search, debounceMs: 250 }, attachTo: document.body });
        const input = wrapper.get('input');
        await input.setValue('se');
        await input.setValue('sen');
        expect(search).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(300);
        expect(search).toHaveBeenCalledTimes(1);
        expect(search).toHaveBeenCalledWith('sen');
        await flushPromises();
        expect(wrapper.text()).toContain('Kopi sen');
    });
});

describe('states and buttons', () => {
    it('ErrorState emits retry and is an alert', async () => {
        wrapper = mount(ErrorState, { props: { message: 'Gagal' } });
        expect(wrapper.attributes('role')).toBe('alert');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });

    it('EmptyState renders title and description', () => {
        wrapper = mount(EmptyState, { props: { title: 'Belum ada prospek', description: 'Ubah filter.' } });
        expect(wrapper.text()).toContain('Belum ada prospek');
    });

    it('Button is busy and disabled while loading', () => {
        wrapper = mount(Button, { props: { loading: true }, slots: { default: 'Simpan' } });
        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.attributes('aria-busy')).toBe('true');
    });

    it('icon-only Button passes aria-label through', () => {
        wrapper = mount(Button, { props: { iconOnly: true }, attrs: { 'aria-label': 'Tutup' } });
        expect(wrapper.attributes('aria-label')).toBe('Tutup');
    });
});
