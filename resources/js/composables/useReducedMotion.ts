import { getCurrentScope, onScopeDispose, ref, type Ref } from 'vue';

const QUERY = '(prefers-reduced-motion: reduce)';

/** Reactive `prefers-reduced-motion: reduce`. Safe where matchMedia is missing (SSR, jsdom). */
export function useReducedMotion(): Ref<boolean> {
    const reduced = ref(false);
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return reduced;
    const mql = window.matchMedia(QUERY);
    reduced.value = !!mql?.matches;
    const onChange = (e: MediaQueryListEvent) => {
        reduced.value = e.matches;
    };
    mql?.addEventListener?.('change', onChange);
    if (getCurrentScope()) onScopeDispose(() => mql?.removeEventListener?.('change', onChange));
    return reduced;
}
