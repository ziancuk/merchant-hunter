import { shallowRef } from 'vue';

export type ToastTone = 'success' | 'error' | 'info';
export interface ToastItem {
    id: number;
    tone: ToastTone;
    message: string;
}

const toasts = shallowRef<ToastItem[]>([]);
let nextId = 1;

/** Global toast stack rendered by AppShell. Auto-dismisses after `ttl` ms (0 = sticky). */
export function useToasts() {
    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }
    function push(message: string, tone: ToastTone = 'info', ttl = 5000): number {
        const id = nextId++;
        toasts.value = [...toasts.value, { id, tone, message }].slice(-4);
        if (ttl > 0) setTimeout(() => dismiss(id), ttl);
        return id;
    }
    return { toasts, push, dismiss };
}
