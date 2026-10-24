<script setup lang="ts">
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null | undefined;
        label: string;
        type?: string;
        hint?: string;
        error?: string | null;
        prefix?: string;
        suffix?: string;
        /** Emit numbers (null when empty) instead of strings. */
        numeric?: boolean;
        multiline?: boolean;
        rows?: number;
    }>(),
    { type: 'text', hint: undefined, error: null, prefix: undefined, suffix: undefined, numeric: false, multiline: false, rows: 4 },
);
const emit = defineEmits<{ 'update:modelValue': [value: string | number | null] }>();

const id = `tf-${useId()}`;
const describedBy = computed(() => [props.hint ? `${id}-hint` : '', props.error ? `${id}-err` : ''].filter(Boolean).join(' ') || undefined);

function onInput(e: Event): void {
    const raw = (e.target as HTMLInputElement | HTMLTextAreaElement).value;
    if (props.numeric) {
        if (raw.trim() === '') emit('update:modelValue', null);
        else {
            const n = Number(raw);
            emit('update:modelValue', Number.isNaN(n) ? null : n);
        }
    } else emit('update:modelValue', raw);
}
const control =
    'w-full rounded-chip border bg-surface/60 px-3 text-base text-fg placeholder:text-muted/70 sm:text-sm disabled:opacity-50';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1">
        <label :for="id" class="text-xs font-medium text-muted">{{ label }}</label>
        <div class="flex items-stretch gap-1.5">
            <span v-if="prefix" class="num grid place-items-center text-xs text-muted">{{ prefix }}</span>
            <textarea
                v-if="multiline"
                v-bind="$attrs"
                :id="id"
                :value="(modelValue as string) ?? ''"
                :rows="rows"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :class="[control, 'py-2', error ? 'border-danger' : 'border-line']"
                @input="onInput"
            />
            <input
                v-else
                v-bind="$attrs"
                :id="id"
                :type="type"
                :value="modelValue ?? ''"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :class="[control, 'h-10', numeric ? 'num' : '', error ? 'border-danger' : 'border-line']"
                @input="onInput"
            />
            <span v-if="suffix" class="num grid place-items-center whitespace-nowrap text-xs text-muted">{{ suffix }}</span>
        </div>
        <p v-if="hint" :id="`${id}-hint`" class="text-2xs text-muted">{{ hint }}</p>
        <p v-if="error" :id="`${id}-err`" class="text-xs text-danger" role="alert">{{ error }}</p>
    </div>
</template>
