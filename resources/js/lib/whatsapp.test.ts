import { describe, expect, it } from 'vitest';
import { buildWhatsAppUrl, normalizePhone } from './whatsapp';

describe('normalizePhone', () => {
    it.each([
        ['0812-5555-0101', '6281255550101'],
        ['+62 812 5555 0101', '6281255550101'],
        ['6281255550101', '6281255550101'],
        ['81255550101', '6281255550101'],
    ])('normalises %s', (input, expected) => expect(normalizePhone(input)).toBe(expected));

    it.each(['021-555-1234', '', null, undefined, '12345', 'abc'])('rejects %s', (input) =>
        expect(normalizePhone(input as string | null)).toBeNull());
});

describe('buildWhatsAppUrl', () => {
    it('encodes the message', () =>
        expect(buildWhatsAppUrl('6281', 'Halo & apa kabar')).toBe('https://wa.me/6281?text=Halo%20%26%20apa%20kabar'));
});
