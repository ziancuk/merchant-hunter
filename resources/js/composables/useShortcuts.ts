import { getCurrentScope, onScopeDispose, ref, shallowReactive } from 'vue';

export interface ShortcutDef {
    /** e.g. `mod+k`, `?`, `alt+shift+p`. `mod` = Ctrl on Windows/Linux, Cmd on macOS (either is accepted). */
    keys: string;
    description: string;
    group?: string;
    handler: (event: KeyboardEvent) => void;
    /** Fire even when focus is in an input/textarea/select (modifier shortcuts always do). */
    allowInInput?: boolean;
}

interface Registered extends ShortcutDef {
    id: number;
}

const registry = shallowReactive<Registered[]>([]);
/** Whether the shortcut help overlay is open (rendered by AppShell). */
export const shortcutHelpOpen = ref(false);
let nextId = 1;
let listening = false;

export function isEditableTarget(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;
    if (!el || typeof el.tagName !== 'string') return false;
    const tag = el.tagName.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable === true;
}

/** Pure matcher, exported for tests. */
export function matchShortcut(event: KeyboardEvent, spec: string): boolean {
    const parts = spec.toLowerCase().split('+');
    const key = parts[parts.length - 1] === '' ? '+' : parts[parts.length - 1];
    const mods = new Set(parts.slice(0, -1));
    const wantMod = mods.has('mod') || mods.has('ctrl') || mods.has('cmd') || mods.has('meta');
    const hasMod = event.ctrlKey || event.metaKey;
    if (wantMod !== hasMod) return false;
    if (mods.has('alt') !== event.altKey) return false;
    const eventKey = (event.key ?? '').toLowerCase();
    if (eventKey !== key) return false;
    if (mods.has('shift') !== event.shiftKey && /^[a-z0-9]$/.test(key)) return false;
    return true;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.defaultPrevented || event.isComposing) return;
    const modified = event.ctrlKey || event.metaKey;
    const editable = isEditableTarget(event.target);
    for (const s of [...registry]) {
        if (editable && !modified && !s.allowInInput) continue;
        if (matchShortcut(event, s.keys)) {
            event.preventDefault();
            s.handler(event);
            return;
        }
    }
}

function sync(): void {
    if (typeof window === 'undefined') return;
    if (registry.length > 0 && !listening) {
        window.addEventListener('keydown', onKeydown);
        listening = true;
    } else if (registry.length === 0 && listening) {
        window.removeEventListener('keydown', onKeydown);
        listening = false;
    }
}

/** Registers a global shortcut; returns the unregister function. */
export function registerShortcut(def: ShortcutDef): () => void {
    const id = nextId++;
    registry.push({ ...def, id });
    sync();
    return () => {
        const i = registry.findIndex((s) => s.id === id);
        if (i >= 0) registry.splice(i, 1);
        sync();
    };
}

/** Read-only view of every registered shortcut (for the `?` help list). */
export function listShortcuts(): readonly Registered[] {
    return registry;
}

/** Registers shortcuts for the current component scope and unregisters them on unmount. */
export function useShortcuts(defs: ShortcutDef[] = []) {
    const offs = defs.map(registerShortcut);
    const register = (def: ShortcutDef) => {
        const off = registerShortcut(def);
        offs.push(off);
        return off;
    };
    if (getCurrentScope()) onScopeDispose(() => offs.forEach((off) => off()));
    return {
        register,
        shortcuts: registry as readonly Registered[],
        helpOpen: shortcutHelpOpen,
        toggleHelp: () => {
            shortcutHelpOpen.value = !shortcutHelpOpen.value;
        },
    };
}
