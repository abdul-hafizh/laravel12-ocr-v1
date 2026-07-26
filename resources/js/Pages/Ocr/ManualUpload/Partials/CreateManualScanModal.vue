<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    cabangs: Array,
    mesins: Array,
    scanTypes: Array,
});

const emit = defineEmits(['close', 'created']);

const MACHINE_TYPES = ['printer', 'cea', 'asaba', 'part_maintenance'];

const now = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const form = ref({
    scan_type: '',
    cabang_id: '',
    user_id: '',
    created_at: now(),
    master_mesin_id: '',
    master_mesin_part_id: '',
    nominal: '',
});

const imageFile = ref(null);
const imagePreview = ref('');

const errorMessage = ref('');
const submitting = ref(false);

const step = ref('form');
const scanId = ref(null);
const candidates = ref([]);
const correctionType = ref('');
const selectedCandidateId = ref('');
const kwhInput = ref('');
const detectedNomorMeter = ref('');

const selectedCabang = computed(() => {
    return props.cabangs.find((c) => Number(c.id) === Number(form.value.cabang_id)) || null;
});

const cabangUsers = computed(() => selectedCabang.value?.users || []);

const cabangMesins = computed(() => {
    return props.mesins.filter((m) => Number(m.master_cabang_id) === Number(form.value.cabang_id));
});

const selectedMesin = computed(() => {
    return cabangMesins.value.find((m) => Number(m.id) === Number(form.value.master_mesin_id)) || null;
});

const mesinParts = computed(() => selectedMesin.value?.maintenance_parts || []);

const needsMachine = computed(() => MACHINE_TYPES.includes(form.value.scan_type));
const needsPart = computed(() => form.value.scan_type === 'part_maintenance');

const onCabangChange = () => {
    form.value.user_id = '';
    form.value.master_mesin_id = '';
    form.value.master_mesin_part_id = '';
};

const onMesinChange = () => {
    form.value.master_mesin_part_id = '';
};

const onFileChange = (event) => {
    const file = event.target.files?.[0];
    imageFile.value = file || null;
    imagePreview.value = file ? URL.createObjectURL(file) : '';
};

const canSubmit = computed(() => {
    if (!form.value.scan_type || !form.value.cabang_id || !form.value.user_id || !form.value.created_at) {
        return false;
    }

    if (needsMachine.value && !form.value.master_mesin_id) {
        return false;
    }

    if (!imageFile.value) {
        return false;
    }

    return true;
});

const submit = async () => {
    errorMessage.value = '';
    submitting.value = true;

    try {
        const data = new FormData();
        data.append('scan_type', form.value.scan_type);
        data.append('cabang_id', form.value.cabang_id);
        data.append('user_id', form.value.user_id);
        data.append('created_at', form.value.created_at);
        data.append('image', imageFile.value);

        if (form.value.master_mesin_id) {
            data.append('master_mesin_id', form.value.master_mesin_id);
        }

        if (form.value.master_mesin_part_id) {
            data.append('master_mesin_part_id', form.value.master_mesin_part_id);
        }

        if (form.value.nominal) {
            data.append('nominal', form.value.nominal);
        }

        const res = await axios.post('/manual', data, {
            withCredentials: true,
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        handleResponse(res.data);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ||
            Object.values(error.response?.data?.errors || {}).flat().join(', ') ||
            'Gagal mengirim data.';
    } finally {
        submitting.value = false;
    }
};

const handleResponse = (payload) => {
    if (payload.status === 'success') {
        emit('created', payload.scan);
        return;
    }

    if (payload.status === 'needs_correction') {
        scanId.value = payload.scan_id;
        correctionType.value = payload.correction;

        if (payload.correction === 'electricity_meter') {
            candidates.value = payload.candidates || [];
            selectedCandidateId.value = '';
            step.value = 'correction_meter';
        } else if (payload.correction === 'electricity_kwh') {
            detectedNomorMeter.value = payload.nomor_meter || '';
            kwhInput.value = '';
            step.value = 'correction_kwh';
        }

        return;
    }

    errorMessage.value = payload.message || 'Gambar tidak sesuai.';
};

const submitMeterCorrection = async () => {
    if (!selectedCandidateId.value) return;

    errorMessage.value = '';
    submitting.value = true;

    try {
        const res = await axios.post(
            `/manual/${scanId.value}/correct`,
            { master_token_listrik_id: selectedCandidateId.value },
            { withCredentials: true }
        );

        handleResponse(res.data);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Gagal menyimpan koreksi.';
    } finally {
        submitting.value = false;
    }
};

const submitKwhCorrection = async () => {
    if (!kwhInput.value) return;

    errorMessage.value = '';
    submitting.value = true;

    try {
        const res = await axios.post(
            `/manual/${scanId.value}/correct`,
            { kwh: kwhInput.value },
            { withCredentials: true }
        );

        handleResponse(res.data);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Gagal menyimpan koreksi.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="emit('close')">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">Buat Upload Manual</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-slate-700 font-bold">TUTUP [X]</button>
            </div>

            <div v-if="step === 'form'" class="space-y-4">
                <div>
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">1. Pilih Menu</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="type in scanTypes" :key="type.key" type="button"
                            @click="form.scan_type = type.key"
                            :class="form.scan_type === type.key ? 'bg-[#2DD4BF] text-white border-[#2DD4BF]' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                            class="rounded-xl border px-3 py-2.5 text-xs font-bold text-left transition">
                            {{ type.label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">2. Cabang</label>
                        <select v-model="form.cabang_id" @change="onCabangChange" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Pilih cabang</option>
                            <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">3. User Cabang</label>
                        <select v-model="form.user_id" :disabled="!form.cabang_id" class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-50">
                            <option value="">Pilih user</option>
                            <option v-for="u in cabangUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">4. Tanggal & Waktu Upload</label>
                    <input v-model="form.created_at" type="datetime-local" class="w-full rounded-xl border-slate-300 text-sm" />
                </div>

                <div v-if="needsMachine" class="grid gap-3" :class="needsPart ? 'grid-cols-2' : 'grid-cols-1'">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">5. Mesin</label>
                        <select v-model="form.master_mesin_id" @change="onMesinChange" :disabled="!form.cabang_id" class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-50">
                            <option value="">Pilih mesin</option>
                            <option v-for="m in cabangMesins" :key="m.id" :value="m.id">{{ m.nama_mesin }} (SN: {{ m.serial_number }})</option>
                        </select>
                    </div>

                    <div v-if="needsPart">
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Part (opsional)</label>
                        <select v-model="form.master_mesin_part_id" :disabled="!form.master_mesin_id" class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-50">
                            <option value="">Tanpa part</option>
                            <option v-for="p in mesinParts" :key="p.id" :value="p.id">{{ p.nama_part }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="needsPart">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Nominal Biaya (Rp)</label>
                    <input v-model="form.nominal" type="number" min="0" placeholder="Contoh: 250000" class="w-full rounded-xl border-slate-300 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">6. Upload Gambar</label>
                    <input type="file" accept="image/*" @change="onFileChange" class="w-full text-sm" />
                    <img v-if="imagePreview" :src="imagePreview" class="mt-2 h-32 w-32 rounded-xl object-cover border border-slate-200" />
                </div>

                <div v-if="errorMessage" class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-600">
                    {{ errorMessage }}
                </div>

                <button @click="submit" :disabled="!canSubmit || submitting"
                    class="w-full rounded-xl bg-[#1E293B] py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-[#2DD4BF] disabled:opacity-40">
                    {{ submitting ? 'Mengirim...' : 'Kirim & Analisa' }}
                </button>
            </div>

            <div v-else-if="step === 'correction_meter'" class="space-y-4">
                <p class="text-sm font-bold text-slate-600">
                    Nomor meter tidak terdeteksi otomatis dengan benar. Silakan pilih nomor meter cabang yang sesuai:
                </p>

                <div class="max-h-72 space-y-2 overflow-y-auto">
                    <label v-for="c in candidates" :key="c.id"
                        class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-bold cursor-pointer hover:border-[#2DD4BF]"
                        :class="{ 'border-[#2DD4BF] bg-[#2DD4BF]/5': selectedCandidateId == c.id }">
                        <input type="radio" :value="c.id" v-model="selectedCandidateId" />
                        {{ c.label }}
                    </label>

                    <div v-if="candidates.length === 0" class="text-xs font-bold text-slate-400">
                        Tidak ada data token listrik aktif untuk cabang ini.
                    </div>
                </div>

                <div v-if="errorMessage" class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-600">
                    {{ errorMessage }}
                </div>

                <button @click="submitMeterCorrection" :disabled="!selectedCandidateId || submitting"
                    class="w-full rounded-xl bg-[#1E293B] py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                    {{ submitting ? 'Menyimpan...' : 'Simpan Pilihan' }}
                </button>
            </div>

            <div v-else-if="step === 'correction_kwh'" class="space-y-4">
                <p class="text-sm font-bold text-slate-600">
                    Nomor meter terdeteksi: <span class="text-[#1E293B]">{{ detectedNomorMeter }}</span>.
                    Namun angka kWh tidak terbaca jelas. Masukkan nilai kWh saat ini:
                </p>

                <input v-model="kwhInput" type="number" step="0.01" min="0" placeholder="Contoh: 239.85"
                    class="w-full rounded-xl border-slate-300 text-sm" />

                <div v-if="errorMessage" class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-600">
                    {{ errorMessage }}
                </div>

                <button @click="submitKwhCorrection" :disabled="!kwhInput || submitting"
                    class="w-full rounded-xl bg-[#1E293B] py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                    {{ submitting ? 'Menyimpan...' : 'Simpan kWh' }}
                </button>
            </div>
        </div>
    </div>
</template>
