<script setup lang="ts">
import { computed, reactive, ref, useId } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import GlassPanel from '@/Components/ui/GlassPanel.vue';
import { api, normalizeError } from '@/lib/api';
import Switch from './Switch.vue';
import TextField from './TextField.vue';
import { PLACEHOLDERS, renderPreview, validateTemplate } from './template';
import type { CategoryRow } from './types';

const props = defineProps<{ category: CategoryRow | null }>();
const emit = defineEmits<{ saved: [category: CategoryRow, created: boolean]; cancel: [] }>();

const uid = useId();
const form = reactive({
    key: props.category?.key ?? '',
    name: props.category?.name ?? '',
    weight: (props.category?.weight ?? 50) as number | null,
    whatsapp_template: props.category?.whatsapp_template ?? 'Halo {nama_bisnis}, saya {nama_pengirim} dari Hitachi. ',
    is_active: props.category?.is_active ?? true,
});
const saving = ref(false);
const serverErrors = ref<Record<string, string[]>>({});
const message = ref<string | null>(null);
const touched = ref(false);
const area = ref<HTMLElement | null>(null);

const isNew = computed(() => !props.category);
const localErrors = computed(() => {
    const e: Record<string, string> = {};
    if (isNew.value && !/^[a-z0-9_]+$/.test(form.key)) e.key = 'Kunci hanya huruf kecil, angka, dan garis bawah.';
    if (form.name.trim() === '') e.name = 'Nama wajib diisi.';
    if (form.weight === null || !Number.isInteger(form.weight) || form.weight < 0 || form.weight > 100) e.weight = 'Bobot harus bilangan bulat 0–100.';
    const t = validateTemplate(form.whatsapp_template);
    if (t) e.whatsapp_template = t;
    return e;
});
const err = (k: string) => (touched.value ? localErrors.value[k] : undefined) ?? serverErrors.value[k]?.[0] ?? null;
const preview = computed(() => (validateTemplate(form.whatsapp_template) ? null : renderPreview(form.whatsapp_template, form.name)));
const dirty = computed(
    () =>
        isNew.value ||
        form.name !== props.category?.name ||
        form.weight !== props.category?.weight ||
        form.whatsapp_template !== props.category?.whatsapp_template ||
        form.is_active !== props.category?.is_active,
);

function insert(ph: string): void {
    const el = area.value?.querySelector('textarea');
    if (!el) {
        form.whatsapp_template += ph;
        return;
    }
    const s = el.selectionStart ?? form.whatsapp_template.length;
    const e = el.selectionEnd ?? s;
    form.whatsapp_template = form.whatsapp_template.slice(0, s) + ph + form.whatsapp_template.slice(e);
    requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(s + ph.length, s + ph.length);
    });
}

async function save(): Promise<void> {
    touched.value = true;
    serverErrors.value = {};
    message.value = null;
    if (Object.keys(localErrors.value).length) {
        message.value = 'Periksa isian yang ditandai.';
        return;
    }
    saving.value = true;
    const body = { key: form.key, name: form.name.trim(), weight: form.weight, whatsapp_template: form.whatsapp_template, is_active: form.is_active };
    try {
        const res = props.category ? await api.put<CategoryRow>(`/api/master/categories/${props.category.id}`, body) : await api.post<CategoryRow>('/api/master/categories', body);
        touched.value = false;
        emit('saved', res.data, isNew.value);
    } catch (e) {
        const er = normalizeError(e);
        serverErrors.value = er.errors;
        message.value = Object.keys(er.errors).length ? 'Periksa isian yang ditandai.' : er.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <GlassPanel as="article" :data-testid="isNew ? 'category-new' : `category-${category!.key}`" :class="!isNew && !form.is_active ? 'opacity-80' : ''">
        <form class="grid gap-4" novalidate @submit.prevent="save">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold leading-tight">{{ isNew ? 'Kategori baru' : category!.name }}</h2>
                <div class="flex items-center gap-2 text-sm">
                    <Badge v-if="!isNew" tone="neutral" class="num">{{ category!.key }}</Badge>
                    <span :id="`${uid}-active`" class="text-muted">{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <Switch v-model="form.is_active" :label="`Kategori aktif`" :data-testid="`category-active-${category?.key ?? 'new'}`" />
                </div>
            </header>

            <p v-if="message" class="rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">{{ message }}</p>

            <div class="grid gap-4 sm:grid-cols-3">
                <TextField v-if="isNew" v-model="form.key" label="Kunci" hint="huruf kecil, angka, _" data-testid="category-key" :error="err('key')" />
                <TextField v-model="form.name" label="Nama" data-testid="category-name" :error="err('name')" />
                <div class="flex flex-col gap-1">
                    <TextField v-model="form.weight" numeric type="number" min="0" max="100" step="1" label="Bobot (0–100)" data-testid="category-weight" :error="err('weight')" hint="Menentukan 25 poin skor kecocokan kategori." />
                    <input v-model.number="form.weight" type="range" min="0" max="100" step="1" aria-label="Geser bobot" class="w-full accent-[var(--c-medium)]" />
                </div>
            </div>

            <div ref="area" class="grid gap-2">
                <TextField v-model="form.whatsapp_template" multiline :rows="4" label="Template WhatsApp" data-testid="category-template" :error="err('whatsapp_template')" />
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Sisipkan placeholder">
                    <span class="text-2xs text-muted">Sisipkan:</span>
                    <button v-for="p in PLACEHOLDERS" :key="p" type="button" class="num h-7 cursor-pointer rounded-full border border-line px-2.5 text-xs transition hover:bg-fg/10" @click="insert(p)">{{ p }}</button>
                </div>
                <p v-if="preview" class="rounded-chip bg-raised/50 px-3 py-2 text-xs text-muted" data-testid="category-preview"><span class="hud-label mr-2">Pratinjau</span>{{ preview }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <Button v-if="isNew" variant="ghost" @click="emit('cancel')">Batal</Button>
                <Button type="submit" variant="primary" :loading="saving" :disabled="!dirty" :data-testid="`category-save-${category?.key ?? 'new'}`">{{ isNew ? 'Tambah kategori' : 'Simpan' }}</Button>
            </div>
        </form>
    </GlassPanel>
</template>
