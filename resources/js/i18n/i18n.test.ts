import { afterEach, describe, expect, it } from 'vitest';
import { LOCALES, MESSAGES, setLocale, t, tn } from './index';

afterEach(() => setLocale('id'));

const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

describe('message catalogues', () => {
    it('have exactly the same keys in Indonesian and English', () => {
        const id = Object.keys(MESSAGES.id).sort();
        const en = Object.keys(MESSAGES.en).sort();
        expect(id.filter((k) => !(k in MESSAGES.en)), 'missing in English').toEqual([]);
        expect(en.filter((k) => !(k in MESSAGES.id)), 'missing in Indonesian').toEqual([]);
    });

    it('have no empty strings and the same {placeholders} in both languages', () => {
        for (const [key, id] of Object.entries(MESSAGES.id)) {
            const en = MESSAGES.en[key];
            expect(id.trim(), `id ${key}`).not.toBe('');
            expect(en?.trim(), `en ${key}`).not.toBe('');
            expect(placeholders(en ?? ''), `placeholders of ${key}`).toEqual(placeholders(id));
        }
    });
});

describe('t()', () => {
    it('defaults to Indonesian and switches language reactively', () => {
        expect(t('nav.map')).toBe('Peta');
        setLocale('en');
        expect(t('nav.map')).toBe('Map');
    });

    it('interpolates placeholders and returns the key when it is unknown', () => {
        expect(t('shell.user_menu_named', { name: 'Ayu' })).toBe('Menu pengguna, Ayu');
        expect(t('nope.missing')).toBe('nope.missing');
    });

    it('persists the language and ignores unsupported values', () => {
        setLocale('en');
        expect(localStorage.getItem('hunter.locale')).toBe('en');
        setLocale('fr' as never);
        expect(t('nav.map')).toBe('Map');
        expect(LOCALES).toEqual(['id', 'en']);
    });
});

describe('tn()', () => {
    it('picks .one for 1 and .other otherwise when the language has both forms', () => {
        MESSAGES.id['test.items.other'] = '{n} item';
        MESSAGES.en['test.items.one'] = '{n} item';
        MESSAGES.en['test.items.other'] = '{n} items';
        setLocale('en');
        expect(tn('test.items', 1)).toBe('1 item');
        expect(tn('test.items', 3)).toBe('3 items');
        setLocale('id');
        expect(tn('test.items', 1)).toBe('1 item');
        delete MESSAGES.id['test.items.other'];
        delete MESSAGES.en['test.items.one'];
        delete MESSAGES.en['test.items.other'];
    });
});
