<script setup lang="ts">
import { useI18n, LOCALES, type Locale } from '@/i18n';

const { t, locale, setLocale } = useI18n();
const labels: Record<Locale, string> = { id: 'ID', en: 'EN' };
</script>

<template>
    <div class="flex items-center rounded-full bg-fg/5 p-0.5" role="group" :aria-label="t('shell.language')" data-testid="locale-switcher">
        <button
            v-for="l in LOCALES"
            :key="l"
            type="button"
            class="h-8 min-w-9 cursor-pointer rounded-full px-2 text-xs font-semibold transition"
            :class="locale === l ? 'bg-fg text-ink' : 'text-fg/70 hover:text-fg'"
            :aria-pressed="locale === l"
            :data-testid="`locale-${l}`"
            @click="setLocale(l)"
        >
            {{ labels[l] }}<span class="sr-only"> – {{ t(`shell.lang_${l}`) }}</span>
        </button>
    </div>
</template>
