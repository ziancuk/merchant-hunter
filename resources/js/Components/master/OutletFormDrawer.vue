<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import { useI18n } from '@/i18n';
import { api, normalizeError } from '@/lib/api';
import Switch from './Switch.vue';
import TextField from './TextField.vue';
import type { OutletRow, ProvinceOption } from './types';

const props = defineProps<{ modelValue: boolean; outlet: OutletRow | null; provinces: ProvinceOption[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [outlet: OutletRow, created: boolean] }>();
const { t } = useI18n();

const blank = () => ({
    code: '',
    name: '',
    address: '',
    city: '',
    province_id: null as number | null,
    lat: null as number | null,
    lng: null as number | null,
    is_active: true,
});
const form = reactive(blank());
const errors = ref<Record<string, string[]>>({});
const message = ref<string | null>(null);
const saving = ref(false);

watch(
    () => [props.modelValue, props.outlet] as const,
    ([open, o]) => {
        if (!open) return;
        errors.value = {};
        message.value = null;
        Object.assign(form, blank(), o ? { code: o.code, name: o.name, address: o.address ?? '', city: o.city, province_id: o.province_id, lat: o.lat, lng: o.lng, is_active: o.is_active } : {});
    },
    { immediate: true },
);

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    message.value = null;
    const body = {
        code: form.code.trim(),
        name: form.name.trim(),
        address: form.address.trim() || null,
        city: form.city.trim(),
        province_id: form.province_id,
        lat: form.lat,
        lng: form.lng,
        is_active: form.is_active,
    };
    try {
        const res = props.outlet ? await api.put<OutletRow>(`/api/master/outlets/${props.outlet.id}`, body) : await api.post<OutletRow>('/api/master/outlets', body);
        emit('saved', res.data, !props.outlet);
        emit('update:modelValue', false);
    } catch (e) {
        const err = normalizeError(e);
        errors.value = err.errors;
        message.value = Object.keys(err.errors).length ? t('master.check_fields') : err.message;
    } finally {
        saving.value = false;
    }
}
const err = (k: string) => errors.value[k]?.[0] ?? null;
</script>

<template>
    <Drawer :model-value="modelValue" :title="outlet ? t('master.outlet_form.edit_title') : t('master.outlet_form.add_title')" :description="t('master.outlet_form.description')" @update:model-value="emit('update:modelValue', $event)">
        <form id="outlet-form" class="grid gap-4 p-4" data-testid="outlet-form" @submit.prevent="submit">
            <p v-if="message" class="rounded-chip border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">{{ message }}</p>
            <TextField v-model="form.code" :label="t('master.outlet_form.code')" data-autofocus data-testid="outlet-code" :error="err('code')" required />
            <TextField v-model="form.name" :label="t('master.outlet_form.name')" data-testid="outlet-name" :error="err('name')" required />
            <TextField v-model="form.address" :label="t('master.outlet_form.address')" :error="err('address')" />
            <TextField v-model="form.city" :label="t('master.outlet_form.city')" data-testid="outlet-city" :error="err('city')" required />
            <div class="flex flex-col gap-1">
                <label for="outlet-province" class="text-xs font-medium text-muted">{{ t('master.outlet_form.province') }}</label>
                <select
                    id="outlet-province"
                    v-model.number="form.province_id"
                    data-testid="outlet-province"
                    required
                    class="h-10 rounded-chip border bg-surface/60 px-3 text-base text-fg sm:text-sm"
                    :class="err('province_id') ? 'border-danger' : 'border-line'"
                >
                    <option :value="null" disabled>{{ t('master.outlet_form.choose_province') }}</option>
                    <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p v-if="err('province_id')" class="text-xs text-danger" role="alert">{{ err('province_id') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <TextField v-model="form.lat" numeric type="number" step="any" :label="t('master.outlet_form.lat')" :error="err('lat')" data-testid="outlet-lat" />
                <TextField v-model="form.lng" numeric type="number" step="any" :label="t('master.outlet_form.lng')" :error="err('lng')" data-testid="outlet-lng" />
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm">{{ t('master.outlet_form.active') }}</span>
                <Switch v-model="form.is_active" :label="t('master.outlet_form.active')" />
            </div>
        </form>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button variant="ghost" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</Button>
                <Button type="submit" form="outlet-form" variant="primary" :loading="saving" data-testid="outlet-save">{{ t('common.save') }}</Button>
            </div>
        </template>
    </Drawer>
</template>
