<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useI18n } from '@/i18n';

defineProps<{ latestNote: string | null; busy: boolean }>();
const emit = defineEmits<{ save: [] }>();
const { t } = useI18n();
const noteText = defineModel<string>({ required: true });
</script>

<template>
    <section aria-labelledby="sec-note" class="space-y-2">
        <h3 id="sec-note" class="hud-label">{{ t('prospect.note.title') }}</h3>
        <p v-if="latestNote" class="whitespace-pre-line rounded-chip bg-fg/5 p-3 text-sm" data-testid="latest-note">{{ latestNote }}</p>
        <label class="sr-only" for="note-input">{{ t('prospect.note.add_label') }}</label>
        <textarea id="note-input" v-model="noteText" rows="2" maxlength="2000" :placeholder="t('prospect.note.placeholder')" class="w-full rounded-chip border border-line bg-ink/60 p-2 text-sm text-fg" />
        <Button size="sm" variant="secondary" :disabled="!noteText.trim()" :loading="busy" data-testid="save-note" @click="emit('save')"><Icon name="note" :size="14" /> {{ t('prospect.note.save') }}</Button>
    </section>
</template>
