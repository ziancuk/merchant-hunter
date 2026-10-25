import { expect, test as base, type APIRequestContext, type BrowserContext, type Page } from '@playwright/test';
import { PASSWORD, USERS, resetData, setSetting, sql, type UserKey } from './env';

/** Console/network noise that is not an application error (documented here, one place). */
export const IGNORED_CONSOLE_ERRORS: RegExp[] = [
    // Offline sandbox: external web fonts (Google Fonts / Bunny) cannot be fetched; purely cosmetic.
    /fonts\.(googleapis|gstatic)\.com/i,
    /fonts\.bunny\.net/i,
    // Chromium reports blocked/failed sub-resources as a generic console error without the URL; font fetches are the only ones expected offline.
    /Failed to load resource: net::ERR_(INTERNET_DISCONNECTED|NAME_NOT_RESOLVED|CONNECTION_REFUSED|BLOCKED_BY_CLIENT)/i,
];

export interface Collected {
    errors: string[];
    pageErrors: string[];
    dialogs: string[];
}

function attachCollectors(page: Page): Collected {
    const c: Collected = { errors: [], pageErrors: [], dialogs: [] };
    page.on('console', (m) => {
        if (m.type() !== 'error') return;
        const text = m.text();
        if (IGNORED_CONSOLE_ERRORS.some((re) => re.test(text) || re.test(m.location().url ?? ''))) return;
        c.errors.push(`${text} @ ${m.location().url ?? ''}`);
    });
    page.on('pageerror', (e) => c.pageErrors.push(String(e)));
    page.on('dialog', (d) => {
        c.dialogs.push(`${d.type()}: ${d.message()}`);
        void d.dismiss();
    });
    return c;
}

/** Logs in with the real login endpoint (CSRF cookie round-trip) so the page and its request context share the session. */
export async function loginAs(context: BrowserContext, who: UserKey = 'ayu', password = PASSWORD): Promise<void> {
    const request = context.request;
    await request.get('/login');
    const res = await request.post('/login', {
        form: { email: USERS[who].email, password },
        headers: { 'X-XSRF-TOKEN': await xsrf(context) },
        maxRedirects: 0,
    });
    expect([302, 303]).toContain(res.status());
}

async function xsrf(context: BrowserContext): Promise<string> {
    const cookies = await context.cookies();
    const c = cookies.find((x) => x.name === 'XSRF-TOKEN');
    if (!c) throw new Error('XSRF-TOKEN cookie not set');
    return decodeURIComponent(c.value);
}

/** JSON call against the API with the session + CSRF of `context`. */
export async function api<T = unknown>(
    context: BrowserContext,
    method: 'GET' | 'POST' | 'PUT' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<{ status: number; json: T; text: string }> {
    const request: APIRequestContext = context.request;
    const res = await request.fetch(url, {
        method,
        data: body,
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': await xsrf(context), 'X-Requested-With': 'XMLHttpRequest' },
        maxRedirects: 0,
    });
    const text = await res.text();
    let json: unknown = null;
    try {
        json = JSON.parse(text);
    } catch {
        /* non-JSON response */
    }
    return { status: res.status(), json: json as T, text };
}

export const test = base.extend<{ collected: Collected }>({
    collected: async ({ page }, use) => {
        await use(attachCollectors(page));
    },
});

export { expect };

/** Call at the top of each spec file: restores the pristine snapshot before the file's tests (D: files are independent). */
export function freshData(): void {
    test.beforeAll(() => {
        resetData();
    });
}

export function enableSimulatedWhatsApp(): void {
    setSetting('demo.allow_simulated_whatsapp', true);
}

export interface PickedProspect {
    id: number;
    name: string;
    phone: string;
    lat: number;
    lng: number;
    score: number;
    category_id: number;
    province_id: number | null;
    city: string | null;
}

/** Deterministic pick: highest-score open, uncontacted prospect with a simulated mobile number (skips `offset` rows). */
export function pickContactable(offset = 0): PickedProspect {
    const rows = sql<PickedProspect>(
        `SELECT id, name, phone, lat, lng, score, category_id, province_id, city FROM prospects
         WHERE lifecycle_status = 'open' AND pipeline_status = 'new' AND do_not_contact = 0 AND phone LIKE '0999%'
           AND phone_simulated = 1 AND lat IS NOT NULL AND name NOT LIKE '%<%'
         ORDER BY score DESC, id ASC LIMIT 1 OFFSET ?`,
        [offset],
    );
    if (rows.length === 0) throw new Error('No contactable prospect in seed');
    return rows[0];
}

export function expectedWaNumber(phone: string): string {
    const digits = phone.replace(/\D/g, '');
    return digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
}

/** The `n` open prospects nearest (haversine-free squared degrees is fine at city scale) to `from`, including `from`. */
export function nearbyProspects(from: PickedProspect, n: number): { id: number; name: string; lat: number; lng: number }[] {
    return sql<{ id: number; name: string; lat: number; lng: number }>(
        `SELECT id, name, lat, lng FROM prospects WHERE lifecycle_status = 'open' AND lat IS NOT NULL AND name NOT LIKE '%<%'
         ORDER BY ((lat - ?) * (lat - ?) + (lng - ?) * (lng - ?)) ASC, id ASC LIMIT ?`,
        [from.lat, from.lat, from.lng, from.lng, n],
    );
}
