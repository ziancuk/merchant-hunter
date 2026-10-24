import { onScopeDispose, ref, watch, getCurrentScope, type Ref } from 'vue';

export interface DebouncedFn<A extends unknown[]> {
    (...args: A): void;
    cancel: () => void;
    flush: () => void;
}

/** Debounced function; the pending call is cancelled when the owning scope is disposed. */
export function useDebounceFn<A extends unknown[]>(fn: (...args: A) => void, ms = 250): DebouncedFn<A> {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let lastArgs: A | null = null;
    const cancel = () => {
        if (timer) clearTimeout(timer);
        timer = null;
        lastArgs = null;
    };
    const debounced = ((...args: A) => {
        lastArgs = args;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            const a = lastArgs;
            lastArgs = null;
            if (a) fn(...a);
        }, ms);
    }) as DebouncedFn<A>;
    debounced.cancel = cancel;
    debounced.flush = () => {
        if (timer && lastArgs) {
            const a = lastArgs;
            cancel();
            fn(...a);
        }
    };
    if (getCurrentScope()) onScopeDispose(cancel);
    return debounced;
}

/** Ref that follows `source` after it has been stable for `ms`. */
export function useDebouncedRef<T>(source: Ref<T>, ms = 250): Ref<T> {
    const out = ref(source.value) as Ref<T>;
    const update = useDebounceFn((v: T) => {
        out.value = v;
    }, ms);
    watch(source, (v) => update(v));
    return out;
}
