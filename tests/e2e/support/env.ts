import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));

export const ROOT = path.resolve(HERE, '../../..');
export const DB_FILE = path.join(ROOT, 'database', 'e2e.sqlite');
export const DB_BASE = path.join(ROOT, 'database', 'e2e.base.sqlite');
export const PASSWORD = process.env.DEMO_USER_PASSWORD ?? 'e2e-password-123';

export const USERS = {
    ayu: { email: 'ayu@hunter.test', name: 'Ayu Management' },
    budi: { email: 'budi@hunter.test', name: 'Budi Management' },
    citra: { email: 'citra@hunter.test', name: 'Citra Management' },
} as const;
export type UserKey = keyof typeof USERS;

/** Environment for every artisan child process; mirrors scripts/e2e-serve.sh. */
export const APP_ENV_VARS = {
    ...process.env,
    APP_ENV: 'e2e',
    HUNTER_BASEMAP: 'local',
    DB_DATABASE: DB_FILE,
    DEMO_USER_PASSWORD: PASSWORD,
};

export function artisan(...args: string[]): string {
    return execFileSync('php', ['artisan', ...args, '--no-interaction'], { cwd: ROOT, env: APP_ENV_VARS, encoding: 'utf8' });
}

/** Runs SQL against the E2E database (read rows or write). Parameters are bound, never interpolated. */
export function sql<T = Record<string, unknown>>(query: string, params: unknown[] = []): T[] {
    const out = execFileSync('php', [path.join(HERE, 'query.php'), DB_FILE, query, JSON.stringify(params)], { cwd: ROOT, encoding: 'utf8' });
    return JSON.parse(out) as T[];
}

/** Restores the pristine seeded database (migrate:fresh --seed + hunter:seed-prospects --offline --seed=42 + hunter:demo-reset). */
export function resetData(): void {
    if (!existsSync(DB_BASE)) throw new Error('database/e2e.base.sqlite missing: run scripts/e2e-prepare.sh (the Playwright webServer does this).');
    for (const ext of ['-wal', '-shm', '-journal']) rmSync(DB_FILE + ext, { force: true });
    copyFileSync(DB_BASE, DB_FILE);
}

export function setSetting(key: string, value: unknown): void {
    sql('INSERT INTO settings (key, value, created_at, updated_at) VALUES (?, ?, datetime(\'now\'), datetime(\'now\')) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [key, JSON.stringify(value)]);
}
