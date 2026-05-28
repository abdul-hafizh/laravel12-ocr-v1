<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const title = 'Hasil Upload Token Listrik';

const dataList = ref([]);
const loading = ref(false);

const getData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            params: {
                scan_type: 'electricity',
            },
        });

        dataList.value = res.data.data || [];
    } catch (error) {
        console.error('Gagal mengambil data token listrik:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(getData);

const parseResult = (item) => {
    try {
        if (!item.analysis_result) return null;

        if (typeof item.analysis_result === 'object') {
            return item.analysis_result;
        }

        return JSON.parse(item.analysis_result);
    } catch (e) {
        console.error('JSON parse error:', e);
        return null;
    }
};

const getDataPenting = (item) => {
    const parsed = parseResult(item);

    if (parsed?.data_penting) {
        return parsed.data_penting;
    }

    return parsed || {};
};

const getValue = (item, key, fallback = '-') => {
    const data = getDataPenting(item);

    return item[key] ?? data[key] ?? fallback;
};

const formatDate = (value) => {
    if (!value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '-';
    return value;
};

const getStatusClass = (status) => {
    return {
        'bg-yellow-100 text-yellow-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'processing',
        'bg-green-100 text-green-700': status === 'success',
        'bg-red-100 text-red-700': status === 'failed',
    };
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Hasil Upload <span class="text-[#2DD4BF]">Token Listrik</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-3xl border border-slate-200">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">
                                Data Token Listrik
                            </h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                Khusus hasil scan token listrik / meteran kWh
                            </p>
                        </div>

                        <button
                            @click="getData"
                            class="px-4 py-2 rounded-xl bg-[#1E293B] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#2DD4BF] transition"
                        >
                            Refresh
                        </button>
                    </div>

                    <div v-if="loading" class="py-16 text-center text-sm font-bold text-slate-400">
                        Memuat data...
                    </div>

                    <div v-else class="grid gap-5">
                        <div
                            v-for="item in dataList"
                            :key="item.id"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex flex-col gap-5 lg:flex-row">
                                <div class="w-full lg:w-40 flex-shrink-0">
                                    <img
                                        :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                        class="h-40 w-full lg:w-40 rounded-2xl border border-slate-200 object-cover"
                                    />
                                </div>

                                <div class="flex-1 space-y-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                            ID Scan: #{{ item.id }}
                                        </span>

                                        <span
                                            class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                            :class="getStatusClass(item.status)"
                                        >
                                            {{ item.status }}
                                        </span>

                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                            {{ formatDate(item.created_at) }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                User Upload
                                            </div>
                                            <div class="mt-1 text-sm font-black text-[#1E293B]">
                                                {{ item.user_name || item.user?.name || '-' }}
                                            </div>
                                            <div class="text-xs font-bold text-slate-400">
                                                {{ item.user_phone || item.user?.phone || item.user_email || item.user?.email || '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Cabang
                                            </div>
                                            <div class="mt-1 text-sm font-black text-[#1E293B]">
                                                {{ item.nama_cabang || item.cabang?.nama_cabang || '-' }}
                                            </div>
                                            <div class="text-xs font-bold text-slate-400">
                                                {{ item.kode_cabang || item.cabang?.kode_cabang || '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-emerald-50 p-4 border border-emerald-100">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-emerald-600">
                                                kWh
                                            </div>
                                            <div class="mt-1 text-xl font-black text-emerald-700">
                                                {{ formatValue(getValue(item, 'kwh')) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Informasi Token
                                            </h4>

                                            <div class="space-y-2">
                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Tanggal</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'tanggal')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Nomor Meter</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'barcode')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">IDPEL</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'nomor_meter')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                    <span class="font-bold text-emerald-600">kWh</span>
                                                    <span class="font-black text-emerald-700 text-right">
                                                        {{ formatValue(getValue(item, 'kwh')) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Lokasi
                                            </h4>

                                            <div class="space-y-2">
                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Lokasi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'lokasi')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Kecamatan</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'kecamatan')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Kota</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'kota')) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Provinsi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'provinsi')) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="item.error_message"
                                        class="rounded-2xl bg-rose-50 p-4 text-sm font-bold text-rose-600"
                                    >
                                        {{ item.error_message }}
                                    </div>

                                    <div
                                        v-if="item.status === 'pending' || item.status === 'processing'"
                                        class="rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-600"
                                    >
                                        Gambar sedang dianalisis...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="dataList.length === 0" class="py-16 text-center text-sm font-bold text-slate-400">
                            Belum ada data hasil upload token listrik.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>