import { t } from '@/i18n';

export interface NavItem {
    href: string;
    icon: string;
    key: string;
    /** Translated at read time so the nav follows the active language. */
    readonly label: string;
    readonly hint: string;
}

function item(href: string, icon: string, key: string): NavItem {
    return {
        href,
        icon,
        key,
        get label() {
            return t(`nav.${key}`);
        },
        get hint() {
            return t(`nav.${key}_hint`);
        },
    };
}

export const NAV_ITEMS: NavItem[] = [
    item('/', 'map', 'map'),
    item('/pipeline', 'kanban', 'pipeline'),
    item('/reports', 'table', 'report'),
    item('/dashboard', 'chart', 'dashboard'),
    item('/impact', 'coins', 'impact'),
    item('/master/data-sources', 'database', 'master'),
    item('/scheduler', 'clock', 'scheduler'),
];

/** Whether the Inertia page url belongs to the nav item (`/` only matches exactly). */
export function isNavActive(url: string, href: string): boolean {
    const path = url.split(/[?#]/)[0] || '/';
    if (href === '/') return path === '/';
    if (href === '/master/data-sources') return path.startsWith('/master');
    return path === href || path.startsWith(`${href}/`);
}
