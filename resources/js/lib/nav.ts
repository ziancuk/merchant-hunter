export interface NavItem {
    href: string;
    label: string;
    icon: string;
    hint?: string;
}

export const NAV_ITEMS: NavItem[] = [
    { href: '/', label: 'Peta', icon: 'map', hint: 'Radar prospek' },
    { href: '/pipeline', label: 'Pipeline', icon: 'kanban', hint: 'Kanban & tabel' },
    { href: '/reports', label: 'Laporan', icon: 'table', hint: 'Tabel prospek & ekspor' },
    { href: '/dashboard', label: 'Dasbor', icon: 'chart', hint: 'KPI & funnel' },
    { href: '/impact', label: 'Dampak', icon: 'coins', hint: 'Estimasi biaya & hasil' },
    { href: '/master/data-sources', label: 'Master', icon: 'database', hint: 'Sumber data, outlet, kategori' },
    { href: '/scheduler', label: 'Penjadwal', icon: 'clock', hint: 'Riwayat sinkronisasi' },
];

/** Whether the Inertia page url belongs to the nav item (`/` only matches exactly). */
export function isNavActive(url: string, href: string): boolean {
    const path = url.split(/[?#]/)[0] || '/';
    if (href === '/') return path === '/';
    if (href === '/master/data-sources') return path.startsWith('/master');
    return path === href || path.startsWith(`${href}/`);
}
