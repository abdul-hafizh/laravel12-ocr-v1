<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    scan: Object,
    mesins: Array,
    tokens: Array,
    scanTypes: Array,
});

const emit = defineEmits(['close', 'updated']);

const FIELD_CONFIG = {
    electricity: [
        { key: 'kwh', label: 'kWh', type: 'text' },
        { key: 'nomor_meter', label: 'Nomor Meter', type: 'text' },
        { key: 'nomor_token', label: 'Nomor Token / IDPEL', type: 'text' },
        { key: 'barcode', label: 'Barcode', type: 'text' },
        { key: 'lokasi', label: 'Lokasi', type: 'text' },
    ],
    printer: [
        { key: 'bw_a3', label: 'BW A3', type: 'number' },
        { key: 'bw_a4', label: 'BW A4', type: 'number' },
        { key: 'color_a3', label: 'Color A3', type: 'number' },
        { key: 'color_a4', label: 'Color A4', type: 'number' },
        { key: 'total_long_sheet', label: 'Total Long Sheet', type: 'number' },
        { key: 'bw_long_sheet', label: 'BW Long Sheet', type: 'number' },
        { key: 'color_long_sheet', label: 'Color Long Sheet', type: 'number' },
    ],
    cea: [
        { key: 'total_counter_mesin', label: 'Total Counter (101)', type: 'number' },
        { key: 'print_counter', label: 'Print Counter (301)', type: 'number' },
        { key: 'copy_counter', label: 'Copy Counter (201)', type: 'number' },
    ],
    asaba: [
        { key: 'total_counter', label: 'Total Counter', type: 'number' },
        { key: 'printer_counter', label: 'Printer Counter', type: 'number' },
        { key: 'copy_counter', label: 'Copy Counter', type: 'number' },
        { key: 'scan_counter', label: 'Scan Counter', type: 'number' },
        { key: 'feed_paper_counter', label: 'Feed Paper Counter', type: 'number' },
        { key: 'output_paper_counter', label: 'Output Paper Counter', type: 'number' },
        { key: 'full_color_counter', label: 'Full Color Counter', type: 'number' },
        { key: 'single_color_counter', label: 'Single Color Counter', type: 'number' },
        { key: 'black_counter', label: 'Black Counter', type: 'number' },
    ],
    part_maintenance: [
        { key: 'nominal_chat', label: 'Nominal Biaya (Rp)', type: 'number' },
        { key: 'jenis_gambar', label: 'Jenis Gambar', type: 'text' },
        { key: 'deskripsi_gambar', label: 'Deskripsi Gambar', type: 'text' },
        { key: 'catatan', label: 'Catatan', type: 'text' },
    ],
    online_receipt: [
        { key: 'total_pembayaran', label: 'Total Pembayaran', type: 'number' },
        { key: 'jenis_struk', label: 'Jenis Struk', type: 'text' },
        { key: 'bank_atau_aplikasi', label: 'Bank / Aplikasi', type: 'text' },
        { key: 'nomor_transaksi', label: 'Nomor Transaksi', type: 'text' },
        { key: 'nomor_referensi', label: 'Nomor Referensi', type: 'text' },
        { key: 'nama_toko', label: 'Nama Toko', type: 'text' },
        { key: 'produk', label: 'Produk', type: 'text' },
        { key: 'catatan', label: 'Catatan', type: 'text' },
    ],
};

const MACHINE_TYPES = ['printer', 'cea', 'asaba', 'part_maintenance'];

const dataPenting = computed(() => {
    const result = props.scan.analysis_result;
    if (!result) return {};
    const parsed = typeof result === 'object' ? result : JSON.parse(result);
    return parsed.data_penting || {};
});

const selectedScanType = ref(props.scan.scan_type);

const fields = computed(() => FIELD_CONFIG[selectedScanType.value] || []);

const values = ref(
    Object.fromEntries(fields.value.map((f) => [f.key, dataPenting.value[f.key] ?? '']))
);

const onScanTypeChange = () => {
    values.value = Object.fromEntries(
        fields.value.map((f) => [
            f.key,
            selectedScanType.value === props.scan.scan_type ? (dataPenting.value[f.key] ?? '') : '',
        ]),
    );
    relinkMesinId.value = selectedScanType.value === props.scan.scan_type ? (props.scan.master_mesin_id || '') : '';
    relinkTokenId.value = selectedScanType.value === props.scan.scan_type ? (dataPenting.value.master_token_listrik?.id || '') : '';
};

const cabangMesins = computed(() => {
    return props.mesins.filter((m) => Number(m.master_cabang_id) === Number(props.scan.cabang_id));
});

const cabangTokens = computed(() => {
    return (props.tokens || []).filter((t) => Number(t.master_cabang_id) === Number(props.scan.cabang_id));
});

const relinkMesinId = ref(props.scan.master_mesin_id || '');
const relinkTokenId = ref(dataPenting.value.master_token_listrik?.id || '');

const toDatetimeLocal = (value) => {
    if (!value) return '';
    const d = new Date(value);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const createdAt = ref(toDatetimeLocal(props.scan.created_at));

const submitting = ref(false);
const errorMessage = ref('');

const submit = async () => {
    submitting.value = true;
    errorMessage.value = '';

    try {
        const payload = { fields: values.value };

        if (selectedScanType.value !== props.scan.scan_type) {
            payload.scan_type = selectedScanType.value;
        }

        if (selectedScanType.value === 'electricity' && relinkTokenId.value) {
            payload.master_token_listrik_id = relinkTokenId.value;
        }

        if (MACHINE_TYPES.includes(selectedScanType.value) && relinkMesinId.value) {
            payload.master_mesin_id = relinkMesinId.value;
        }

        if (createdAt.value) {
            payload.created_at = createdAt.value;
        }

        const res = await axios.put(`/manual/${props.scan.id}`, payload, {
            withCredentials: true,
        });

        emit('updated', res.data.scan);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Gagal menyimpan perubahan.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="emit('close')">
        <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">Edit Data Scan #{{ scan.id }}</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-slate-700 font-bold">TUTUP [X]</button>
            </div>

            <div class="mb-4 rounded-xl bg-slate-50 p-3 text-xs font-bold text-slate-500">
                Cabang: <span class="text-[#1E293B]">{{ scan.cabang?.nama_cabang || '-' }}</span> ·
                User: <span class="text-[#1E293B]">{{ scan.user?.name || '-' }}</span>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Tanggal & Waktu Upload</label>
                    <input v-model="createdAt" type="datetime-local" class="w-full rounded-xl border-slate-300 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Tipe Scan</label>
                    <select v-model="selectedScanType" @change="onScanTypeChange" class="w-full rounded-xl border-slate-300 text-sm">
                        <option v-for="type in scanTypes" :key="type.key" :value="type.key">{{ type.label }}</option>
                    </select>
                    <p v-if="selectedScanType !== scan.scan_type" class="mt-1 text-[10px] font-bold text-amber-600">
                        Tipe scan diubah dari "{{ scanTypes.find((t) => t.key === scan.scan_type)?.label || scan.scan_type }}". Isian di bawah akan dikosongkan dan menimpa data lama.
                    </p>
                </div>

                <div v-if="selectedScanType === 'electricity'">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Relink Token Listrik (opsional)</label>
                    <select v-model="relinkTokenId" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="">-- tidak diubah --</option>
                        <option v-for="t in cabangTokens" :key="t.id" :value="t.id">{{ t.nomor_meter }} ({{ t.nama_pelanggan }})</option>
                    </select>
                </div>

                <div v-if="MACHINE_TYPES.includes(selectedScanType)">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Relink Mesin{{ selectedScanType !== scan.scan_type ? ' (wajib)' : ' (opsional)' }}</label>
                    <select v-model="relinkMesinId" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="">-- tidak diubah --</option>
                        <option v-for="m in cabangMesins" :key="m.id" :value="m.id">{{ m.nama_mesin }} (SN: {{ m.serial_number }})</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div v-for="field in fields" :key="field.key">
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">
                            {{ field.label }}
                        </label>
                        <input v-model="values[field.key]" :type="field.type" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                </div>

                <div v-if="errorMessage" class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-600">
                    {{ errorMessage }}
                </div>

                <button @click="submit" :disabled="submitting"
                    class="w-full rounded-xl bg-[#1E293B] py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-[#2DD4BF] disabled:opacity-40">
                    {{ submitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
            </div>
        </div>
    </div>
</template>
