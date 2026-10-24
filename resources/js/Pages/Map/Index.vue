<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Peta" />
    <main class="p-6">
        <p>Peta (placeholder, dibangun di Sesi 3).</p>
        <button class="mt-4 rounded bg-white/10 px-3 py-1" @click="router.post('/logout')">Keluar</button>
    </main>
</template>
