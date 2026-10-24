// Test helpers (jsdom has no matchMedia). Only imported by *.test.ts files.
import type { ContactCheck, PipelineCard, ProspectDetail } from '@/types';

export function mockMatchMedia(reduced: boolean): void {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: (query: string) => ({
            matches: reduced && query.includes('prefers-reduced-motion'),
            media: query,
            onchange: null,
            addEventListener: () => {},
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
            dispatchEvent: () => false,
        }),
    });
}

/** ProspectDetail from the CONTRACTS.md §4 example. */
export function prospectFixture(overrides: Partial<ProspectDetail> = {}): ProspectDetail {
    return {
        id: 101,
        name: 'Kopi Senja',
        category: { id: 1, key: 'cafe', name: 'Kafe' },
        address: 'Jl. Melati No. 4, Menteng, Jakarta Pusat',
        address_source: 'reverse_geocode',
        lat: -6.21,
        lng: 106.81,
        phone: '0812-5555-0101',
        phone_simulated: false,
        whatsapp_url: 'https://wa.me/6281255550101',
        website: null,
        rating: 4.6,
        rating_simulated: true,
        review_count: 312,
        reviews_simulated: true,
        data_updated_at: '2026-10-24T18:00:00Z',
        first_seen_at: '2026-09-01T18:00:00Z',
        score: 82,
        score_band: 'hot',
        score_breakdown: [
            { key: 'category', label: 'Kecocokan kategori', value: 0.8, points: 20, max: 25, reason: 'Kafe: bobot 80' },
            { key: 'rating', label: 'Rating', value: 0.5, points: 7.5, max: 15, reason: 'Rating 4,6' },
        ],
        nearest_outlet: { id: 3, name: 'Toko Sinar Jaya', distance_m: 640 },
        neighbor_count_500m: 2,
        area_density: 18,
        pipeline_status: 'new',
        rejection_reason: null,
        handled_by: null,
        last_contacted_at: null,
        last_contacted_by: null,
        contact_count: 0,
        do_not_contact: false,
        badges: ['new_open', 'simulated'],
        lifecycle_status: 'open',
        latest_note: null,
        activities: [{ id: 1, type: 'created', user: null, body: 'Ditemukan oleh sync', created_at: '2026-09-01T18:00:00Z' }],
        contact_warning: null,
        ...overrides,
    };
}

/** ContactCheck from the CONTRACTS.md §4 example. */
export function checkFixture(overrides: Partial<ContactCheck> = {}): ContactCheck {
    return {
        allowed: true,
        blocked_reason: null,
        warning: null,
        min_interval_violation: false,
        phone_normalized: '6281255550101',
        message: 'Halo Kopi Senja, kami dari Hitachi.',
        template_category: 'cafe',
        override_count: 0,
        max_overrides: 2,
        ...overrides,
    };
}

export function cardFixture(overrides: Partial<PipelineCard> = {}): PipelineCard {
    return {
        id: 101,
        name: 'Kopi Senja',
        category: 'Kafe',
        city: 'Jakarta Pusat',
        score: 82,
        score_band: 'hot',
        phone_present: true,
        last_contacted_at: null,
        handled_by: null,
        badges: ['new_open', 'simulated'],
        do_not_contact: false,
        ...overrides,
    };
}

export const flush = () => new Promise((r) => setTimeout(r, 0));
