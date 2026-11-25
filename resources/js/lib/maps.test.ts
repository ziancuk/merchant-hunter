import { describe, expect, it } from 'vitest';
import { googleMapsUrl } from './maps';

describe('googleMapsUrl', () => {
    it('searches by name and address', () => {
        const u = new URL(googleMapsUrl({ name: 'Kopi Satu', address: 'Jl. Satu No. 1', lat: -6.2, lng: 106.8 }));
        expect(u.origin + u.pathname).toBe('https://www.google.com/maps/search/');
        expect(u.searchParams.get('api')).toBe('1');
        expect(u.searchParams.get('query')).toBe('Kopi Satu, Jl. Satu No. 1');
    });
    it('falls back to the name plus coordinates without an address', () => {
        const u = new URL(googleMapsUrl({ name: 'Kopi & Teh', address: null, lat: -6.2, lng: 106.8 }));
        expect(u.searchParams.get('query')).toBe('Kopi & Teh -6.2,106.8');
    });
});
