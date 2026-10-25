// Tiny i18n runtime (Indonesian default, English optional). No dependency: messages are flat "namespace.key" strings, one namespace per file in locales/<lang>/.
import { computed, ref } from 'vue';

export type Locale = 'id' | 'en';
export const LOCALES: readonly Locale[] = ['id', 'en'] as const;
export const DEFAULT_LOCALE: Locale = 'id';
export const LOCALE_STORAGE_KEY = 'hunter.locale';
/** Cookie read by the server (SetLocale middleware) so page loads, exports and JSON errors follow the same language. */
export const LOCALE_COOKIE = 'hunter_locale';

type Tree = { [key: string]: string | Tree };
type Flat = Record<string, string>;

function flatten(tree: Tree, prefix = '', out: Flat = {}): Flat {
    for (const [k, v] of Object.entries(tree)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (typeof v === 'string') out[key] = v;
        else flatten(v, key, out);
    }
    return out;
}

/** Every file in locales/<lang>/*.ts is one namespace (file name = key prefix). Adding a file needs no registration. */
function load(modules: Record<string, { default: Tree }>): Flat {
    const out: Flat = {};
    for (const [path, mod] of Object.entries(modules)) {
        const ns = path.replace(/^.*\//, '').replace(/\.ts$/, '');
        flatten(mod.default, ns, out);
    }
    return out;
}

export const MESSAGES: Record<Locale, Flat> = {
    id: load(import.meta.glob<{ default: Tree }>('./locales/id/*.ts', { eager: true })),
    en: load(import.meta.glob<{ default: Tree }>('./locales/en/*.ts', { eager: true })),
};

export function isLocale(value: unknown): value is Locale {
    return value === 'id' || value === 'en';
}

function readStored(): Locale | null {
    try {
        const v = localStorage.getItem(LOCALE_STORAGE_KEY);
        return isLocale(v) ? v : null;
    } catch {
        return null;
    }
}

function initial(): Locale {
    if (typeof document !== 'undefined') {
        const attr = document.documentElement.getAttribute('lang');
        const stored = readStored();
        if (stored) return stored;
        if (isLocale(attr)) return attr;
    }
    // Never guess from the browser language: Indonesian is the product default.
    return DEFAULT_LOCALE;
}

const locale = ref<Locale>(initial());

function apply(value: Locale): void {
    if (typeof document === 'undefined') return;
    document.documentElement.setAttribute('lang', value);
    document.cookie = `${LOCALE_COOKIE}=${value}; path=/; max-age=31536000; samesite=lax`;
}
if (typeof document !== 'undefined') apply(locale.value);

export function setLocale(value: Locale): void {
    if (!isLocale(value) || value === locale.value) return;
    locale.value = value;
    apply(value);
    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, value);
    } catch {
        /* storage unavailable: keep in memory + cookie */
    }
}

export function currentLocale(): Locale {
    return locale.value;
}

/** `{name}` placeholders. A missing key falls back to Indonesian, then to the key itself (visible in dev). */
export function t(key: string, params?: Record<string, string | number>): string {
    const raw = MESSAGES[locale.value][key] ?? MESSAGES.id[key] ?? key;
    if (!params) return raw;
    return raw.replace(/\{(\w+)\}/g, (m, name: string) => (name in params ? String(params[name]) : m));
}

/** Count-aware message: uses `<key>.one` when n === 1 (English) and `<key>.other` otherwise. Indonesian needs only `.other`. */
export function tn(key: string, n: number, params: Record<string, string | number> = {}): string {
    const form = n === 1 && MESSAGES[locale.value][`${key}.one`] !== undefined ? 'one' : 'other';
    return t(`${key}.${form}`, { n, ...params });
}

/** BCP 47 tag for Intl formatters. */
export function intlLocale(): string {
    return locale.value === 'en' ? 'en-GB' : 'id-ID';
}

export function useI18n() {
    return { t, tn, locale: computed(() => locale.value), setLocale, intlLocale };
}
