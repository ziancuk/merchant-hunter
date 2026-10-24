import { afterEach, describe, expect, it, vi } from 'vitest';
import { isEditableTarget, listShortcuts, matchShortcut, registerShortcut } from './useShortcuts';

const key = (k: string, init: KeyboardEventInit = {}) => new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });

describe('matchShortcut', () => {
    it('matches mod+k with ctrl or meta only', () => {
        expect(matchShortcut(key('k', { ctrlKey: true }), 'mod+k')).toBe(true);
        expect(matchShortcut(key('k', { metaKey: true }), 'mod+k')).toBe(true);
        expect(matchShortcut(key('k'), 'mod+k')).toBe(false);
        expect(matchShortcut(key('k', { ctrlKey: true }), 'k')).toBe(false);
    });
    it('matches ? despite shift', () => {
        expect(matchShortcut(key('?', { shiftKey: true }), '?')).toBe(true);
    });
});

describe('registerShortcut', () => {
    const offs: (() => void)[] = [];
    afterEach(() => {
        offs.splice(0).forEach((o) => o());
    });

    it('fires on window keydown and unregisters', () => {
        const handler = vi.fn();
        const off = registerShortcut({ keys: 'g', description: 'x', handler });
        offs.push(off);
        window.dispatchEvent(key('g'));
        expect(handler).toHaveBeenCalledTimes(1);
        off();
        window.dispatchEvent(key('g'));
        expect(handler).toHaveBeenCalledTimes(1);
        expect(listShortcuts().find((s) => s.keys === 'g')).toBeUndefined();
    });

    it('ignores plain keys typed in inputs but not modifier shortcuts', () => {
        const plain = vi.fn();
        const modded = vi.fn();
        offs.push(registerShortcut({ keys: 'j', description: 'plain', handler: plain }), registerShortcut({ keys: 'mod+j', description: 'mod', handler: modded }));
        const input = document.createElement('input');
        document.body.appendChild(input);
        input.dispatchEvent(key('j'));
        input.dispatchEvent(key('j', { ctrlKey: true }));
        expect(plain).not.toHaveBeenCalled();
        expect(modded).toHaveBeenCalledTimes(1);
        expect(isEditableTarget(input)).toBe(true);
        input.remove();
    });
});
