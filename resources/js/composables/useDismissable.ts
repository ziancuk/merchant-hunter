import { onScopeDispose, ref, watch, type Ref } from 'vue';

export interface DismissableOptions {
    /** Listeners are attached only while this is true (default: always). */
    enabled?: Ref<boolean>;
    /** Dismiss on Escape (default true). Turn off when the component handles the key itself. */
    escape?: boolean;
    /** Use the capture phase for the outside click, so clicks that stop propagation are still seen. */
    capture?: boolean;
}

type El = { readonly value: HTMLElement | null | undefined };

/**
 * Calls `onDismiss` on a click outside `targets` or on Escape. Listeners exist only while `enabled`, and are removed
 * when the owning scope is disposed.
 */
export function useDismissable(targets: El | El[], onDismiss: () => void, options: DismissableOptions = {}): void {
    const list = Array.isArray(targets) ? targets : [targets];
    const enabled = options.enabled ?? ref(true);
    const escape = options.escape ?? true;
    const capture = options.capture ?? false;

    function onClick(e: MouseEvent): void {
        const t = e.target as Node | null;
        if (!t) return;
        if (list.some((r) => r.value?.contains(t))) return;
        onDismiss();
    }
    function onKey(e: KeyboardEvent): void {
        if (e.key === 'Escape') onDismiss();
    }

    function detach(): void {
        document.removeEventListener('click', onClick, capture);
        document.removeEventListener('keydown', onKey);
    }

    watch(
        enabled,
        (on) => {
            detach();
            if (!on) return;
            document.addEventListener('click', onClick, capture);
            if (escape) document.addEventListener('keydown', onKey);
        },
        { immediate: true },
    );

    onScopeDispose(detach);
}
