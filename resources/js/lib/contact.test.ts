import { describe, expect, it } from 'vitest';
import { whatsappAvailability } from './contact';
import { isNavActive } from './nav';

const base = { phone: '0812-5555-0101', phoneSimulated: false, whatsappUrl: 'https://wa.me/6281255550101', doNotContact: false, lifecycleStatus: 'open' as const };

describe('whatsappAvailability', () => {
    it('is enabled for a real mobile number', () => {
        expect(whatsappAvailability(base)).toMatchObject({ enabled: true, reason: null, kind: 'ok' });
    });
    it('opt-out wins over everything', () => {
        expect(whatsappAvailability({ ...base, doNotContact: true, lifecycleStatus: 'closed' })).toMatchObject({ enabled: false, kind: 'opt_out' });
    });
    it('blocks closed businesses', () => {
        expect(whatsappAvailability({ ...base, lifecycleStatus: 'closed' }).kind).toBe('closed');
    });
    it('blocks missing phone', () => {
        expect(whatsappAvailability({ ...base, phone: null, whatsappUrl: null }).reason).toBe('Tidak ada nomor telepon');
    });
    it('shows "Nomor simulasi" for simulated numbers without url', () => {
        const r = whatsappAvailability({ ...base, phone: '0999-1234-5678', phoneSimulated: true, whatsappUrl: null });
        expect(r).toMatchObject({ enabled: false, reason: 'Nomor simulasi', kind: 'simulated' });
    });
    it('allows simulated numbers when the demo flag supplies a url', () => {
        const r = whatsappAvailability({ ...base, phone: '0999-1234-5678', phoneSimulated: true, whatsappUrl: 'https://wa.me/62999' });
        expect(r).toMatchObject({ enabled: true, reason: 'Nomor simulasi' });
    });
    it('flags landlines as invalid', () => {
        expect(whatsappAvailability({ ...base, phone: '021-5551234', whatsappUrl: null }).kind).toBe('invalid_phone');
    });
});

describe('isNavActive', () => {
    it('matches root exactly', () => {
        expect(isNavActive('/', '/')).toBe(true);
        expect(isNavActive('/pipeline', '/')).toBe(false);
    });
    it('matches sections with query strings and children', () => {
        expect(isNavActive('/pipeline?view=table', '/pipeline')).toBe(true);
        expect(isNavActive('/master/outlets', '/master/data-sources')).toBe(true);
        expect(isNavActive('/dashboard', '/pipeline')).toBe(false);
    });
});
