import { describe, expect, it, vi } from 'vitest';
import { effectScope, ref } from 'vue';
import { useDismissable } from './useDismissable';

function setup(options = {}) {
    const inside = document.createElement('div');
    const outside = document.createElement('div');
    document.body.append(inside, outside);
    const onDismiss = vi.fn();
    const scope = effectScope();
    scope.run(() => useDismissable(ref(inside), onDismiss, options));
    return { inside, outside, onDismiss, scope, cleanup: () => (inside.remove(), outside.remove()) };
}

describe('useDismissable', () => {
    it('dismisses on outside click and Escape but not on inside click', () => {
        const s = setup();
        s.inside.click();
        expect(s.onDismiss).not.toHaveBeenCalled();
        s.outside.click();
        expect(s.onDismiss).toHaveBeenCalledTimes(1);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(s.onDismiss).toHaveBeenCalledTimes(2);
        s.scope.stop();
        s.cleanup();
    });

    it('stops listening when the scope is disposed', () => {
        const s = setup();
        s.scope.stop();
        s.outside.click();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(s.onDismiss).not.toHaveBeenCalled();
        s.cleanup();
    });

    it('can ignore Escape and only listens while enabled', async () => {
        const inside = document.createElement('div');
        document.body.append(inside);
        const onDismiss = vi.fn();
        const enabled = ref(false);
        const scope = effectScope();
        scope.run(() => useDismissable(ref(inside), onDismiss, { enabled, escape: false }));
        document.body.click();
        expect(onDismiss).not.toHaveBeenCalled();
        enabled.value = true;
        await Promise.resolve();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(onDismiss).not.toHaveBeenCalled();
        document.body.click();
        expect(onDismiss).toHaveBeenCalledTimes(1);
        scope.stop();
        inside.remove();
    });
});
