<script setup lang="ts">
import Tooltip from '@/Components/ui/Tooltip.vue';
import type { MapMode } from '@/types';
import { MAP_MODES } from './constants';

defineProps<{ modelValue: MapMode }>();
const emit = defineEmits<{ 'update:modelValue': [mode: MapMode] }>();
</script>

<template>
    <div class="glass grid grid-cols-4 gap-0.5 rounded-full p-1 sm:w-auto" role="radiogroup" aria-label="Mode peta" data-testid="map-modes">
        <Tooltip v-for="m in MAP_MODES" :key="m.key" :text="`${m.hint} (${m.shortcut})`" placement="bottom">
            <button
                type="button"
                role="radio"
                :aria-checked="modelValue === m.key"
                class="h-9 w-full cursor-pointer whitespace-nowrap rounded-full px-2 text-2xs font-medium transition sm:px-3 sm:text-xs"
                :class="modelValue === m.key ? 'bg-fg text-ink' : 'text-fg/80 hover:bg-fg/10 hover:text-fg'"
                :data-testid="`map-mode-${m.key}`"
                @click="emit('update:modelValue', m.key)"
            >
                {{ m.label }}
            </button>
        </Tooltip>
    </div>
</template>
