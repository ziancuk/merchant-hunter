<script setup lang="ts">
defineProps<{ modelValue: boolean; label: string; disabled?: boolean; busy?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        :aria-busy="busy || undefined"
        :disabled="disabled || busy"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-line transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-medium/40' : 'bg-fg/10'"
        @click="emit('update:modelValue', !modelValue)"
    >
        <span class="absolute left-0.5 rounded-full bg-fg transition-transform duration-150" :class="modelValue ? 'translate-x-5' : 'translate-x-0'" :style="{ height: '1.125rem', width: '1.125rem' }" />
    </button>
</template>
