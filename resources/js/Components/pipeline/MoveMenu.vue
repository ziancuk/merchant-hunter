<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import { STATUS_CLASSES } from '@/lib/colors';
import { PIPELINE_STATUSES, type PipelineStatus } from '@/types';
import { COLUMN_LABEL } from '@/lib/labels';

const props = defineProps<{ prospectId: number; name: string; current: PipelineStatus }>();
const emit = defineEmits<{ move: [to: PipelineStatus] }>();

const open = ref(false);
const trigger = ref<HTMLButtonElement | null>(null);
const menu = ref<HTMLElement | null>(null);
const pos = ref({ top: 0, left: 0 });
const menuId = `move-menu-${useId()}`;
const targets = computed(() => PIPELINE_STATUSES.filter((s) => s !== props.current));

function place(): void {
    const r = trigger.value?.getBoundingClientRect();
    if (!r) return;
    const width = 192;
    const height = targets.value.length * 40 + 36;
    const left = Math.min(Math.max(8, r.right - width), window.innerWidth - width - 8);
    const top = r.bottom + height + 8 > window.innerHeight ? Math.max(8, r.top - height - 4) : r.bottom + 4;
    pos.value = { top, left };
}

function items(): HTMLElement[] {
    return Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? []);
}

async function show(): Promise<void> {
    place();
    open.value = true;
    document.addEventListener('click', onOutside, true);
    window.addEventListener('scroll', dismiss, true);
    window.addEventListener('resize', dismiss);
    await nextTick();
    items()[0]?.focus();
}

function hide(returnFocus = false): void {
    if (!open.value) return;
    open.value = false;
    document.removeEventListener('click', onOutside, true);
    window.removeEventListener('scroll', dismiss, true);
    window.removeEventListener('resize', dismiss);
    if (returnFocus) trigger.value?.focus();
}

function dismiss(): void {
    hide(false);
}

function onOutside(e: MouseEvent): void {
    const t = e.target as Node;
    if (menu.value?.contains(t) || trigger.value?.contains(t)) return;
    hide();
}

function choose(to: PipelineStatus): void {
    hide(true);
    emit('move', to);
}

function onKeydown(e: KeyboardEvent): void {
    const list = items();
    const i = list.indexOf(document.activeElement as HTMLElement);
    if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        hide(true);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        list[(i + 1) % list.length]?.focus();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        list[(i - 1 + list.length) % list.length]?.focus();
    } else if (e.key === 'Home') {
        e.preventDefault();
        list[0]?.focus();
    } else if (e.key === 'End') {
        e.preventDefault();
        list[list.length - 1]?.focus();
    } else if (e.key === 'Tab') {
        hide();
    }
}

onBeforeUnmount(() => hide());
</script>

<template>
    <span class="inline-flex">
        <button
            ref="trigger"
            type="button"
            class="grid h-8 w-8 cursor-pointer place-items-center rounded-full text-muted transition hover:bg-fg/10 hover:text-fg"
            :aria-label="`Pindahkan ${name} ke…`"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="open ? menuId : undefined"
            :data-testid="`move-menu-${prospectId}`"
            @click.stop="open ? hide(true) : show()"
        >
            <Icon name="chevron-down" :size="16" />
        </button>
        <Teleport to="body">
            <div
                v-if="open"
                :id="menuId"
                ref="menu"
                role="menu"
                :aria-label="`Pindahkan ${name} ke`"
                class="glass fixed z-50 w-48 rounded-chip p-1 shadow-pop"
                :style="{ top: `${pos.top}px`, left: `${pos.left}px` }"
                @keydown="onKeydown"
            >
                <p class="hud-label px-2 py-1.5" aria-hidden="true">Pindahkan ke…</p>
                <button
                    v-for="s in targets"
                    :key="s"
                    type="button"
                    role="menuitem"
                    class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-chip px-2 text-left text-sm text-fg hover:bg-fg/10 focus-visible:bg-fg/10"
                    :data-testid="`move-to-${s}`"
                    @click="choose(s)"
                >
                    <span class="h-2 w-2 rounded-full" :class="STATUS_CLASSES[s].bg" aria-hidden="true" />
                    {{ COLUMN_LABEL[s] }}
                </button>
            </div>
        </Teleport>
    </span>
</template>
