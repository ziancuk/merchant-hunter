<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login');
</script>

<template>
    <Head title="Masuk" />
    <main class="flex min-h-screen items-center justify-center p-4">
        <form class="w-full max-w-sm space-y-4 rounded-xl border border-white/10 bg-white/5 p-6" @submit.prevent="submit">
            <h1 class="text-xl font-semibold">POS Merchant Hunter</h1>
            <label class="block text-sm">
                Email
                <input v-model="form.email" type="email" autocomplete="username" required class="mt-1 w-full rounded bg-black/40 p-2" />
            </label>
            <label class="block text-sm">
                Kata sandi
                <input v-model="form.password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded bg-black/40 p-2" />
            </label>
            <p v-if="form.errors.email" class="text-sm text-red-400" role="alert">{{ form.errors.email }}</p>
            <button type="submit" :disabled="form.processing" class="w-full rounded bg-amber-500 p-2 font-medium text-black">Masuk</button>
        </form>
    </main>
</template>
