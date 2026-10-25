<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import Icon from '@/Components/ui/Icon.vue';
import LocaleSwitcher from '@/Components/ui/LocaleSwitcher.vue';
import { useTheme } from '@/composables/useTheme';
import { useI18n } from '@/i18n';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login');
const { isDark, toggle } = useTheme();
const { t } = useI18n();

const field =
    'mt-1.5 h-11 w-full rounded-chip border border-line bg-ink/60 px-3 text-base text-fg placeholder:text-muted';
</script>

<template>
    <Head :title="t('auth.head')" />
    <div class="radar-bg relative flex min-h-dvh items-center justify-center overflow-hidden p-4">
        <!-- Rotating radar sweep (static under reduced motion) -->
        <div
            class="pointer-events-none absolute left-1/2 top-1/2 h-[140vmax] w-[140vmax] -translate-x-1/2 -translate-y-1/2 animate-sweep rounded-full opacity-25"
            style="background: conic-gradient(from 0deg, transparent 0deg 300deg, var(--c-hot) 360deg)"
            aria-hidden="true"
        />
        <div class="absolute right-4 top-4 flex items-center gap-2">
            <LocaleSwitcher class="glass" />
            <button type="button" class="glass grid h-10 w-10 cursor-pointer place-items-center rounded-full text-fg/80 hover:text-fg" :aria-label="isDark ? t('shell.theme_to_light') : t('shell.theme_to_dark')" @click="toggle">
                <Icon :name="isDark ? 'sun' : 'moon'" />
            </button>
        </div>

        <main class="relative w-full max-w-sm">
            <GlassPanel as="form" class="space-y-5 p-6" aria-labelledby="login-title" @submit.prevent="submit">
                <header class="space-y-1">
                    <p class="hud-label">{{ t('auth.eyebrow') }}</p>
                    <h1 id="login-title" class="font-display text-3xl font-bold leading-tight tracking-tight">POS Merchant<br /><span class="text-hot">Hunter</span></h1>
                    <p class="text-sm text-muted">{{ t('auth.subtitle') }}</p>
                </header>

                <label class="block text-sm font-medium">
                    {{ t('auth.email') }}
                    <input v-model="form.email" type="email" autocomplete="username" required :placeholder="t('auth.email_placeholder')" :class="field" />
                </label>
                <label class="block text-sm font-medium">
                    {{ t('auth.password') }}
                    <input v-model="form.password" type="password" autocomplete="current-password" required :class="field" />
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-muted">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 accent-[var(--c-focus)]" />
                    {{ t('auth.remember') }}
                </label>

                <p v-if="form.errors.email" class="rounded-chip border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ form.errors.email }}</p>

                <Button type="submit" variant="hot" size="lg" class="w-full" :loading="form.processing">{{ t('auth.submit') }}</Button>
            </GlassPanel>
        </main>
    </div>
</template>
