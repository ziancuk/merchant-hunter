<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import MasterTabs from '@/Components/master/MasterTabs.vue';
import PageHeader from '@/Components/master/PageHeader.vue';
import Panel from '@/Components/master/Panel.vue';
import {
    diffParams,
    getPath,
    mergeParams,
    PARAM_FIELDS,
    SCORING_DEFAULTS,
    setPath,
    WEIGHT_FIELDS,
    type ScoringField,
    type ScoringParams,
} from '@/Components/master/scoring';
import Switch from '@/Components/master/Switch.vue';
import TextField from '@/Components/master/TextField.vue';
import type { AppSettings } from '@/Components/master/types';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToasts } from '@/composables/useToasts';
import { api, normalizeError } from '@/lib/api';

const props = defineProps<{ settings: AppSettings }>();
const { push } = useToasts();

const defaults = SCORING_DEFAULTS as unknown as ScoringParams;

function toForm(s: AppSettings) {
    return {
        contact: { ...s.contact },
        digest: { ...s.digest },
        sync: { ...s.sync },
        enrichment: { ...s.enrichment },
        demo: { ...s.demo },
        scoring: mergeParams(defaults, s.scoring?.params ?? {}),
    };
}
const form = reactive(toForm(props.settings));
const saved = ref(JSON.stringify(toForm(props.settings)));
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const message = ref<string | null>(null);

const dirty = computed(() => JSON.stringify(form) !== saved.value);
const weightSum = computed(() => WEIGHT_FIELDS.reduce((a, f) => a + Number(getPath(form.scoring, f.path) ?? 0), 0));
const err = (path: string) => errors.value[path]?.[0] ?? null;

function numberField(group: 'contact' | 'digest' | 'sync', key: string): number | null {
    return (form[group] as Record<string, unknown>)[key] as number | null;
}
function setNumber(group: 'contact' | 'digest' | 'sync', key: string, v: unknown): void {
    (form[group] as Record<string, unknown>)[key] = v;
}
const scoringGet = (f: ScoringField) => getPath(form.scoring, f.path) as number | null;
const scoringSet = (f: ScoringField, v: unknown) => setPath(form.scoring, f.path, v);
const neighbors = computed(() => form.scoring.neighbor_values as (number | null)[]);
const scoringModified = computed(() => Object.keys(diffParams(form.scoring, defaults)).length > 0);

function resetScoring(): void {
    form.scoring = mergeParams(defaults, {});
}

async function save(): Promise<void> {
    saving.value = true;
    errors.value = {};
    message.value = null;
    const body = {
        contact: form.contact,
        digest: { ...form.digest, email_to: form.digest.email_to ? String(form.digest.email_to).trim() : null },
        sync: form.sync,
        enrichment: form.enrichment,
        demo: form.demo,
        scoring: { params: diffParams(form.scoring, defaults) },
    };
    try {
        const { data } = await api.put<AppSettings>('/api/settings', body);
        const fresh = toForm(data);
        Object.assign(form, fresh);
        saved.value = JSON.stringify(fresh);
        push('Pengaturan disimpan.', 'success', 3000);
    } catch (e) {
        const er = normalizeError(e);
        errors.value = er.errors;
        message.value = er.status === 422 ? 'Ada isian di luar rentang yang diizinkan. Periksa yang ditandai.' : er.message;
    } finally {
        saving.value = false;
    }
}

const CONTACT = [
    { key: 'warn_days', label: 'Peringatan kontak terakhir', suffix: 'hari', hint: 'Tampilkan peringatan bila prospek dihubungi dalam rentang ini (0–90).' },
    { key: 'min_interval_days', label: 'Jarak minimum antar kontak', suffix: 'hari', hint: 'Kontak lebih cepat butuh alasan override (0–365).' },
    { key: 'max_overrides', label: 'Maksimum override per prospek', suffix: 'kali', hint: '0–20.' },
] as const;
const DIGEST = [
    { key: 'hot_threshold', label: 'Ambang skor panas', suffix: 'poin', hint: 'Prospek dengan skor ≥ ini masuk daftar panas (1–100).' },
    { key: 'follow_up_days', label: 'Perlu follow-up setelah', suffix: 'hari', hint: 'Tanpa aktivitas selama rentang ini (1–90).' },
] as const;
</script>

<template>
    <Head title="Pengaturan" />
    <AppShell>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader eyebrow="Master data" title="Pengaturan" description="Aturan kontak, ringkasan harian, sinkronisasi, skor, dan mode demo.">
                <Button variant="primary" size="sm" :disabled="!dirty" :loading="saving" data-testid="settings-save" @click="save">
                    <Icon name="check" :size="14" />
                    Simpan pengaturan
                </Button>
            </PageHeader>
            <MasterTabs current="settings" />

            <p v-if="message" class="mb-4 rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert" data-testid="settings-error">{{ message }}</p>

            <form class="grid gap-4" novalidate @submit.prevent="save">
                <Panel title="Aturan kontak" hint="Mencegah prospek dihubungi berulang oleh banyak akun.">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <TextField
                            v-for="f in CONTACT"
                            :key="f.key"
                            :model-value="numberField('contact', f.key)"
                            numeric
                            type="number"
                            :label="f.label"
                            :suffix="f.suffix"
                            :hint="f.hint"
                            :error="err(`contact.${f.key}`)"
                            :data-testid="`setting-contact-${f.key}`"
                            @update:model-value="setNumber('contact', f.key, $event)"
                        />
                    </div>
                </Panel>

                <Panel title="Ringkasan harian (digest)">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <TextField v-model="form.digest.email_to" type="email" label="Kirim ke email" hint="Kosongkan untuk tidak mengirim email." :error="err('digest.email_to')" data-testid="setting-digest-email_to" />
                        <TextField
                            v-for="f in DIGEST"
                            :key="f.key"
                            :model-value="numberField('digest', f.key)"
                            numeric
                            type="number"
                            :label="f.label"
                            :suffix="f.suffix"
                            :hint="f.hint"
                            :error="err(`digest.${f.key}`)"
                            :data-testid="`setting-digest-${f.key}`"
                            @update:model-value="setNumber('digest', f.key, $event)"
                        />
                    </div>
                </Panel>

                <Panel title="Sinkronisasi">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <TextField
                            :model-value="numberField('sync', 'max_outlets_per_run')"
                            numeric
                            type="number"
                            label="Maksimum outlet per run"
                            suffix="outlet"
                            hint="Membatasi beban satu run penjadwal (1–1000)."
                            :error="err('sync.max_outlets_per_run')"
                            data-testid="setting-sync-max_outlets_per_run"
                            @update:model-value="setNumber('sync', 'max_outlets_per_run', $event)"
                        />
                    </div>
                </Panel>

                <Panel title="Enrichment & demo">
                    <ul class="m-0 grid list-none gap-4 p-0">
                        <li class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium">Data simulasi (rating, ulasan, telepon)</p>
                                <p class="text-xs text-muted">Bila aktif, kolom yang tidak tersedia dari sumber diisi data simulasi dan diberi label "Simulasi".</p>
                            </div>
                            <Switch v-model="form.enrichment.simulated" label="Data simulasi" data-testid="setting-enrichment-simulated" />
                        </li>
                        <li class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium">Izinkan WhatsApp ke nomor simulasi</p>
                                <p class="text-xs text-muted">Hanya untuk demo/E2E. Nomor simulasi memakai rentang tidak aktif (0999-…).</p>
                            </div>
                            <Switch v-model="form.demo.allow_simulated_whatsapp" label="Izinkan WhatsApp ke nomor simulasi" data-testid="setting-demo-allow_simulated_whatsapp" />
                        </li>
                    </ul>
                </Panel>

                <Panel title="Skor prospek" hint="Nilai default ditampilkan sebagai petunjuk. Hanya nilai yang Anda ubah yang disimpan.">
                    <template #actions>
                        <Button size="sm" variant="ghost" :disabled="!scoringModified" data-testid="scoring-reset" @click="resetScoring">Kembalikan default</Button>
                    </template>
                    <h3 class="hud-label">Poin maksimum per komponen <span class="num ml-2 normal-case" :class="weightSum === 100 ? 'text-st-won' : 'text-warm'" data-testid="weight-sum">total {{ weightSum }}</span></h3>
                    <p v-if="weightSum !== 100" class="-mt-2 text-xs text-warm">Skor dibatasi 0–100; total poin selain 100 membuat skor tidak seimbang.</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <TextField
                            v-for="f in WEIGHT_FIELDS"
                            :key="f.path"
                            :model-value="scoringGet(f)"
                            numeric
                            type="number"
                            :label="f.label"
                            :hint="`${f.hint} · default ${getPath(defaults, f.path)}`"
                            :min="f.min"
                            :max="f.max"
                            :step="f.step"
                            :error="err(`scoring.params.${f.path}`)"
                            :data-testid="`scoring-${f.path}`"
                            @update:model-value="scoringSet(f, $event)"
                        />
                    </div>
                    <h3 class="hud-label mt-2">Parameter perhitungan</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <TextField
                            v-for="f in PARAM_FIELDS"
                            :key="f.path"
                            :model-value="scoringGet(f)"
                            numeric
                            type="number"
                            :label="f.label"
                            :hint="`${f.hint} · default ${getPath(defaults, f.path)}`"
                            :min="f.min"
                            :max="f.max"
                            :step="f.step"
                            :error="err(`scoring.params.${f.path}`)"
                            :data-testid="`scoring-${f.path}`"
                            @update:model-value="scoringSet(f, $event)"
                        />
                    </div>
                    <h3 class="hud-label mt-2">Nilai kepadatan tetangga (0, 1, 2, 3+ tetangga dalam 500 m)</h3>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <TextField
                            v-for="(_, i) in neighbors"
                            :key="i"
                            v-model="neighbors[i]"
                            numeric
                            type="number"
                            :label="`${i}${i === 3 ? '+' : ''} tetangga`"
                            min="0"
                            max="1"
                            step="0.05"
                            :hint="`default ${(SCORING_DEFAULTS.neighbor_values as readonly number[])[i]}`"
                            :data-testid="`scoring-neighbor-${i}`"
                        />
                    </div>
                </Panel>

                <div class="flex justify-end">
                    <Button type="submit" variant="primary" :disabled="!dirty" :loading="saving">Simpan pengaturan</Button>
                </div>
            </form>
        </div>
    </AppShell>
</template>
