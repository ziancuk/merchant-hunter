import axios, { AxiosError } from 'axios';

export interface ApiError {
    message: string;
    errors: Record<string, string[]>;
    status: number | null;
}

/** Shared axios instance: JSON, session cookie, CSRF through the XSRF-TOKEN cookie (axios default). */
export const api = axios.create({
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

function fallbackMessage(status: number | null, hasResponse: boolean): string {
    if (status === 401) return 'Sesi berakhir. Silakan masuk kembali.';
    if (status === 403) return 'Anda tidak punya akses.';
    if (status === 404) return 'Data tidak ditemukan.';
    if (status === 429) return 'Terlalu banyak permintaan. Coba lagi sebentar lagi.';
    return hasResponse ? 'Terjadi kesalahan pada server.' : 'Tidak dapat terhubung ke server.';
}

/** Turns anything thrown by axios/fetch into `{message, errors, status}` (Indonesian fallbacks). */
export function normalizeError(error: unknown): ApiError {
    if (axios.isAxiosError(error)) {
        const err = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;
        const status = err.response?.status ?? null;
        const data = err.response?.data;
        return { message: data?.message || fallbackMessage(status, !!err.response), errors: data?.errors ?? {}, status };
    }
    if (error && typeof error === 'object' && 'message' in error && 'errors' in error) {
        const e = error as ApiError;
        return { message: e.message, errors: e.errors ?? {}, status: e.status ?? null };
    }
    return { message: error instanceof Error ? error.message : 'Terjadi kesalahan.', errors: {}, status: null };
}

export function readXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * POST with fetch `keepalive` so the request survives navigation (D-65: used right after wa.me opens).
 * Rejects with an ApiError for non-2xx responses.
 */
export async function postKeepalive<T>(url: string, body: unknown): Promise<T> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = readXsrfToken();
    if (token) headers['X-XSRF-TOKEN'] = token;
    let response: Response;
    try {
        response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body), credentials: 'same-origin', keepalive: true });
    } catch {
        const err: ApiError = { message: fallbackMessage(null, false), errors: {}, status: null };
        throw err;
    }
    let data: unknown;
    try {
        data = await response.json();
    } catch {
        data = null;
    }
    if (!response.ok) {
        const d = (data ?? {}) as { message?: string; errors?: Record<string, string[]> };
        const err: ApiError = { message: d.message || 'Gagal mencatat kontak.', errors: d.errors ?? {}, status: response.status };
        throw err;
    }
    return data as T;
}
