<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CommandPalette, { type CommandItem } from '@/Components/ui/CommandPalette.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon, { type IconName } from '@/Components/ui/Icon.vue';
import ShortcutHelp from '@/Components/ui/ShortcutHelp.vue';
import Toast from '@/Components/ui/Toast.vue';
import Tooltip from '@/Components/ui/Tooltip.vue';
import { shortcutHelpOpen, useShortcuts } from '@/composables/useShortcuts';
import { useTheme } from '@/composables/useTheme';
import { useToasts } from '@/composables/useToasts';
import { isNavActive, NAV_ITEMS } from '@/lib/nav';
import type { SharedProps } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Full-bleed content (map): no scrolling, no padding. Otherwise the content area scrolls under the floating HUD. */
        fullBleed?: boolean;
        /** Extra command-palette entries contributed by the page. */
        commandItems?: CommandItem[];
        /** Async remote search for the palette (GET /api/search). */
        search?: (query: string) => Promise<CommandItem[]>;
    }>(),
    { fullBleed: false, commandItems: () => [], search: undefined },
);
const emit = defineEmits<{ 'command-select': [item: CommandItem] }>();

const page = usePage();
const shared = computed(() => page.props as unknown as SharedProps);
const { isDark, toggle: toggleTheme } = useTheme();
const { toasts, push, dismiss } = useToasts();

const paletteOpen = ref(false);
const userMenuOpen = ref(false);
const userMenuRoot = ref<HTMLElement | null>(null);

const user = computed(() => shared.value.auth?.user ?? null);
const appName = computed(() => shared.value.app?.name ?? 'POS Merchant Hunter');
const initial = computed(() => (user.value?.name?.trim().charAt(0) || '?').toUpperCase());
const modKey = computed(() => (typeof navigator !== 'undefined' && /mac|iphone|ipad/i.test(navigator.platform || navigator.userAgent) ? '⌘' : 'Ctrl'));

function logout(): void {
    userMenuOpen.value = false;
    router.post('/logout');
}

const paletteItems = computed<CommandItem[]>(() => [
    ...NAV_ITEMS.map((n) => ({ id: `nav:${n.href}`, label: n.label, hint: n.hint, group: 'Navigasi', action: () => router.visit(n.href) })),
    ...props.commandItems,
    { id: 'act:theme', label: isDark.value ? 'Ganti ke tema terang' : 'Ganti ke tema gelap', group: 'Aksi', action: toggleTheme },
    { id: 'act:shortcuts', label: 'Lihat pintasan keyboard', hint: '?', group: 'Aksi', action: () => (shortcutHelpOpen.value = true) },
    { id: 'act:logout', label: 'Keluar', group: 'Aksi', action: logout },
]);

useShortcuts([
    { keys: '?', description: 'Tampilkan pintasan keyboard', group: 'Umum', handler: () => (shortcutHelpOpen.value = !shortcutHelpOpen.value) },
    { keys: '/', description: 'Cari (buka palet perintah)', group: 'Umum', handler: () => (paletteOpen.value = true) },
    { keys: 't', description: 'Ganti tema gelap/terang', group: 'Umum', handler: toggleTheme },
]);

watch(
    () => [shared.value.flash?.success, shared.value.flash?.error] as const,
    ([ok, err]) => {
        if (ok) push(ok, 'success');
        if (err) push(err, 'error', 8000);
    },
    { immediate: true },
);

function onDocClick(e: MouseEvent): void {
    if (userMenuOpen.value && userMenuRoot.value && !userMenuRoot.value.contains(e.target as Node)) userMenuOpen.value = false;
}
function onDocKey(e: KeyboardEvent): void {
    if (e.key === 'Escape' && userMenuOpen.value) userMenuOpen.value = false;
}
onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onDocKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onDocKey);
});

const hudBtn = 'grid h-10 w-10 cursor-pointer place-items-center rounded-full text-fg/80 transition hover:bg-fg/10 hover:text-fg';
</script>

<template>
    <div class="fixed inset-0 overflow-hidden bg-ink text-fg">
        <a href="#main" class="sr-only-focusable glass absolute left-3 top-3 z-[90] rounded-chip px-3 py-2 text-sm">Lewati ke konten utama</a>

        <main id="main" tabindex="-1" class="absolute inset-0 outline-none" :class="fullBleed ? '' : 'overflow-y-auto px-4 pb-28 pt-20 md:pb-10'">
            <slot />
        </main>

        <!-- Floating HUD layer: only its children capture pointer events, so the map stays interactive. -->
        <div class="pointer-events-none absolute inset-0 z-20">
            <!-- Brand -->
            <GlassPanel as="div" rounded="full" :padded="false" class="pointer-events-auto absolute left-3 top-3 flex h-12 items-center gap-2.5 pl-3 pr-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="shrink-0">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.35" stroke-width="1.5" />
                    <circle cx="12" cy="12" r="5.5" stroke="currentColor" stroke-opacity="0.55" stroke-width="1.5" />
                    <path d="M12 12 19 6.5" stroke="var(--c-hot)" stroke-width="2" stroke-linecap="round" />
                    <circle cx="12" cy="12" r="1.6" fill="var(--c-hot)" />
                </svg>
                <span class="font-display text-sm font-semibold leading-none tracking-tight">{{ appName }}</span>
            </GlassPanel>

            <!-- Nav dock (desktop/tablet: top centre) -->
            <nav aria-label="Navigasi utama" class="pointer-events-auto absolute left-1/2 top-3 hidden -translate-x-1/2 md:block">
                <GlassPanel as="ul" rounded="full" :padded="false" class="m-0 flex list-none items-center gap-1 p-1">
                    <li v-for="n in NAV_ITEMS" :key="n.href">
                        <Tooltip :text="n.hint ?? n.label" placement="bottom">
                            <Link
                                :href="n.href"
                                class="flex h-10 items-center gap-2 rounded-full px-3 text-sm font-medium transition"
                                :class="isNavActive(page.url, n.href) ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10 hover:text-fg'"
                                :aria-current="isNavActive(page.url, n.href) ? 'page' : undefined"
                            >
                                <Icon :name="n.icon as IconName" :size="16" />
                                <span class="hidden lg:inline">{{ n.label }}</span>
                                <span class="sr-only lg:hidden">{{ n.label }}</span>
                            </Link>
                        </Tooltip>
                    </li>
                </GlassPanel>
            </nav>

            <!-- Controls (top right) -->
            <div class="pointer-events-auto absolute right-3 top-3 flex items-center gap-2">
                <GlassPanel as="div" rounded="full" :padded="false" class="flex items-center gap-0.5 p-1">
                    <button type="button" class="flex h-10 cursor-pointer items-center gap-2 rounded-full px-3 text-sm text-fg/80 transition hover:bg-fg/10 hover:text-fg" aria-label="Buka palet perintah" @click="paletteOpen = true">
                        <Icon name="search" :size="16" />
                        <span class="num hidden text-xs text-muted sm:inline">{{ modKey }} K</span>
                    </button>
                    <button type="button" :class="hudBtn" :aria-label="isDark ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'" @click="toggleTheme">
                        <Icon :name="isDark ? 'sun' : 'moon'" />
                    </button>
                    <button type="button" :class="hudBtn" aria-label="Pintasan keyboard" @click="shortcutHelpOpen = true">
                        <Icon name="keyboard" />
                    </button>
                    <div v-if="user" ref="userMenuRoot" class="relative">
                        <button
                            type="button"
                            class="grid h-10 w-10 cursor-pointer place-items-center rounded-full bg-fg/10 font-display text-sm font-semibold transition hover:bg-fg/20"
                            :aria-label="`Menu pengguna, ${user.name}`"
                            aria-haspopup="menu"
                            :aria-expanded="userMenuOpen"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            {{ initial }}
                        </button>
                        <Transition name="pop">
                            <div v-if="userMenuOpen" role="menu" aria-label="Menu pengguna" class="glass absolute right-0 top-12 w-60 rounded-panel p-2">
                                <div class="border-b border-line px-3 pb-2 pt-1">
                                    <p class="truncate text-sm font-medium">{{ user.name }}</p>
                                    <p class="truncate text-xs text-muted">{{ user.email }}</p>
                                </div>
                                <button type="button" role="menuitem" class="mt-1 flex w-full cursor-pointer items-center gap-2 rounded-chip px-3 py-2 text-left text-sm hover:bg-fg/10" @click="logout">
                                    <Icon name="logout" :size="16" /> Keluar
                                </button>
                            </div>
                        </Transition>
                    </div>
                </GlassPanel>
            </div>

            <!-- Corner HUD slots -->
            <div v-if="$slots['top-left']" class="pointer-events-auto absolute left-3 top-[4.5rem] max-w-[calc(100vw-1.5rem)]"><slot name="top-left" /></div>
            <div v-if="$slots['top-right']" class="pointer-events-auto absolute right-3 top-[4.5rem] max-w-[calc(100vw-1.5rem)]"><slot name="top-right" /></div>
            <div v-if="$slots['bottom-left']" class="pointer-events-auto absolute bottom-24 left-3 max-w-[calc(100vw-1.5rem)] md:bottom-3"><slot name="bottom-left" /></div>
            <div v-if="$slots['bottom-right']" class="pointer-events-auto absolute bottom-24 right-3 max-w-[calc(100vw-1.5rem)] md:bottom-3"><slot name="bottom-right" /></div>

            <!-- Nav dock (phone: bottom) -->
            <nav aria-label="Navigasi utama (ponsel)" class="pointer-events-auto absolute inset-x-3 bottom-3 md:hidden">
                <GlassPanel as="ul" rounded="full" :padded="false" class="m-0 flex list-none items-center justify-between p-1">
                    <li v-for="n in NAV_ITEMS" :key="n.href" class="flex-1">
                        <Link
                            :href="n.href"
                            class="flex h-12 flex-col items-center justify-center gap-0.5 rounded-full text-2xs transition"
                            :class="isNavActive(page.url, n.href) ? 'bg-fg text-ink' : 'text-fg/80'"
                            :aria-current="isNavActive(page.url, n.href) ? 'page' : undefined"
                        >
                            <Icon :name="n.icon as IconName" :size="18" />
                            <span>{{ n.label }}</span>
                        </Link>
                    </li>
                </GlassPanel>
            </nav>
        </div>

        <CommandPalette v-model:open="paletteOpen" :items="paletteItems" :search="search" @select="emit('command-select', $event)" />
        <ShortcutHelp />
        <Toast :items="toasts" @dismiss="dismiss" />
    </div>
</template>
