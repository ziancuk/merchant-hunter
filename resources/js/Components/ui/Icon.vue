<script lang="ts">
/** Inline stroke icons (24x24 grid). Add paths here; no icon library is installed. */
export const ICON_PATHS = {
    map: 'M9 4 3 6.5v13L9 17m0-13 6 2.5M9 4v13m6-10.5L21 4v13l-6 2.5m0-13v13',
    kanban: 'M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z',
    chart: 'M4 20V10m6 10V4m6 16v-7m5 7H3',
    coins: 'M12 7c4.4 0 8-1.1 8-2.5S16.4 2 12 2 4 3.1 4 4.5 7.6 7 12 7ZM4 4.5v5C4 10.9 7.6 12 12 12s8-1.1 8-2.5v-5M4 9.5v5c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5v-5M4 14.5v5C4 20.9 7.6 22 12 22s8-1.1 8-2.5v-5',
    database: 'M12 8c4.4 0 8-1.1 8-2.5S16.4 3 12 3 4 4.1 4 5.5 7.6 8 12 8ZM4 5.5v13C4 19.9 7.6 21 12 21s8-1.1 8-2.5v-13M4 12c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-14v5l3 2',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm5-2 5 5',
    sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-13v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4',
    moon: 'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z',
    close: 'M6 6l12 12M18 6 6 18',
    check: 'm5 12.5 4.5 4.5L19 7.5',
    alert: 'M12 9v4m0 4h.01M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-10v5m0-8h.01',
    phone: 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z',
    whatsapp: 'M21 12a9 9 0 0 1-13.4 7.8L3 21l1.3-4.4A9 9 0 1 1 21 12Zm-12-3c0 3.3 3.2 6.5 6.5 6.5l1.5-1.5-2-1-1 .8a4 4 0 0 1-2-2l.8-1-1-2L9 9Z',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9Z',
    star: 'm12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z',
    pin: 'M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
    ban: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM5.6 5.6l12.8 12.8',
    logout: 'M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5m4-4 4-4-4-4m4 4H9',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0',
    command: 'M9 9V6a3 3 0 1 0-3 3h3Zm0 0h6m-6 0v6m6-6V6a3 3 0 1 1 3 3h-3Zm0 0v6m0 0h3a3 3 0 1 1-3 3v-3Zm0 0H9m0 0v3a3 3 0 1 1-3-3h3Z',
    filter: 'M4 5h16l-6 8v6l-4-2v-4L4 5Z',
    'chevron-down': 'm6 9 6 6 6-6',
    'chevron-right': 'm9 6 6 6-6 6',
    sparkle: 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Zm7 12 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z',
    refresh: 'M20 11a8 8 0 0 0-14.5-3M4 5v4h4m-4 4a8 8 0 0 0 14.5 3M20 19v-4h-4',
    keyboard: 'M3 6h18a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm4 4h.01M11 10h.01M15 10h.01M19 10h.01M7 14h10',
    store: 'M4 9 5.5 4h13L20 9M4 9h16M4 9v11h16V9M9 20v-6h6v6',
    navigation: 'm3 11 18-8-8 18-2-8-8-2Z',
    note: 'M5 4h14v16H5zM8 9h8M8 13h8M8 17h4',
    send: 'm4 12 16-8-5 16-3-7-8-1Z',
    flame: 'M12 22c4 0 7-2.7 7-6.5 0-3-2-5-3.5-7-.4 1.700-1.500 2.500-2.500 2.500 0-3-1.500-6-4.500-8 .5 3-1 4.500-2.500 6.500C4.800 11.800 5 14 5 15.500 5 19.300 8 22 12 22Z',
} as const;

export type IconName = keyof typeof ICON_PATHS;
</script>

<script setup lang="ts">
withDefaults(defineProps<{ name: IconName; size?: number | string; label?: string }>(), { size: 18, label: undefined });
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        :role="label ? 'img' : undefined"
        :aria-label="label"
        :aria-hidden="label ? undefined : 'true'"
        focusable="false"
        class="shrink-0"
    >
        <path :d="ICON_PATHS[name]" />
    </svg>
</template>
