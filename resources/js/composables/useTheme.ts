import { computed, ref } from 'vue';

export type Theme = 'dark' | 'light';
export const THEME_KEY = 'hunter.theme';

function readStored(): Theme | null {
    try {
        const v = localStorage.getItem(THEME_KEY);
        return v === 'light' || v === 'dark' ? v : null;
    } catch {
        return null;
    }
}

function initial(): Theme {
    if (typeof document !== 'undefined') {
        const cl = document.documentElement.classList;
        if (cl.contains('light')) return 'light';
        if (cl.contains('dark')) return 'dark';
    }
    return readStored() ?? 'dark';
}

const theme = ref<Theme>(initial());

function apply(value: Theme): void {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    root.classList.remove('dark', 'light');
    root.classList.add(value);
    root.style.colorScheme = value;
}

/** Dark-first theme state shared across the app; persists to localStorage `hunter.theme`. */
export function useTheme() {
    function setTheme(value: Theme): void {
        theme.value = value;
        apply(value);
        try {
            localStorage.setItem(THEME_KEY, value);
        } catch {
            /* storage unavailable (private mode): keep in-memory only */
        }
    }
    function toggle(): void {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }
    return { theme, isDark: computed(() => theme.value === 'dark'), setTheme, toggle };
}
