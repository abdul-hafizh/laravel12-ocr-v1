<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    title: {
        type: String,
        default: 'Hasil Upload Mesin Cetak',
    },
    scanType: {
        type: String,
        default: 'printer',
    },
});

const dataList = ref([]);
const loading = ref(false);

const getData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            params: {
                scan_type: props.scanType,
            },
        });

        dataList.value = res.data.data || [];
    } catch (error) {
        console.error('Gagal mengambil data:', error);
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
        console.log('JSON parse error:', e, item.analysis_result);
        return null;
    }
};

const getDataPenting = (item) => {
    return parseResult(item) || {};
};

const getSerialNumber = (item) => {
    return getDataPenting(item).serial_number || '-';
};

const getCounter = (item) => {
    const data = getDataPenting(item);

    return {
        black_white_large: data.total_black_white_large ?? 0,
        black_white_small: data.total_black_white_small ?? 0,
        full_color_large: data.total_full_color_large ?? 0,
        full_color_small: data.total_full_color_small ?? 0,
        long_sheet_total: data.total_long_sheet ?? 0,
        bw: data.total_black_white ?? 0,
        color: data.total_color ?? 0,
        total: data.total ?? 0,
    };
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '-';

    if (!isNaN(value)) {
        return Number(value).toLocaleString('id-ID');
    }

    return value;
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
                Hasil Upload <span class="text-[#2DD4BF]">Mesin Cetak</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-3xl border border-slate-200">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">
                                Data Counter Mesin
                            </h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                Perhitungan berdasarkan serial number dan master harga mesin
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
                                                {{ item.user?.name || '-' }}
                                            </div>
                                            <div class="text-xs font-bold text-slate-400">
                                                {{ item.user?.phone || item.user?.email || '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Cabang
                                            </div>
                                            <div class="mt-1 text-sm font-black text-[#1E293B]">
                                                {{ item.cabang?.nama_cabang || '-' }}
                                            </div>
                                            <div class="text-xs font-bold text-slate-400">
                                                {{ item.cabang?.kode_cabang || '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-[#2DD4BF]/10 p-4 border border-[#2DD4BF]/20">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-[#0F766E]">
                                                Serial Number
                                            </div>
                                            <div class="mt-1 text-lg font-black text-[#1E293B] uppercase">
                                                {{ getSerialNumber(item) }}
                                            </div>
                                        </div>
                                    </div>

                                    <template v-if="parseResult(item)">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <div class="rounded-2xl border border-slate-200 p-4">
                                                <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                    Informasi Mesin
                                                </h4>

                                                <div class="space-y-2">
                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Tanggal</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ getDataPenting(item).tanggal || '-' }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Lokasi</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ getDataPenting(item).lokasi || '-' }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Nama Mesin</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ getDataPenting(item).nama_mesin || '-' }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-[#2DD4BF]/10 p-3 text-sm">
                                                        <span class="font-bold text-[#0F766E]">Serial Number</span>
                                                        <span class="font-black text-[#1E293B] uppercase">
                                                            {{ getSerialNumber(item) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 p-4">
                                                <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                    Summary Counter
                                                </h4>

                                                <div class="space-y-2">
                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Total Black & White</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ formatValue(getCounter(item).bw) }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Total Color</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ formatValue(getCounter(item).color) }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                        <span class="font-bold text-slate-500">Total Long Sheet</span>
                                                        <span class="font-black text-[#1E293B]">
                                                            {{ formatValue(getCounter(item).long_sheet_total) }}
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between rounded-xl bg-emerald-50 p-3 text-sm">
                                                        <span class="font-bold text-emerald-600">Total Keseluruhan</span>
                                                        <span class="font-black text-emerald-700">
                                                            {{ formatValue(getCounter(item).total) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Detail Counter
                                            </h4>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Black & White Large</span>
                                                    <span class="font-black text-[#1E293B]">
                                                        {{ formatValue(getCounter(item).black_white_large) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Black & White Small</span>
                                                    <span class="font-black text-[#1E293B]">
                                                        {{ formatValue(getCounter(item).black_white_small) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Full Color Large</span>
                                                    <span class="font-black text-[#1E293B]">
                                                        {{ formatValue(getCounter(item).full_color_large) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Full Color Small</span>
                                                    <span class="font-black text-[#1E293B]">
                                                        {{ formatValue(getCounter(item).full_color_small) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between rounded-xl bg-slate-50 p-3 text-sm md:col-span-2">
                                                    <span class="font-bold text-slate-500">Long Sheet</span>
                                                    <span class="font-black text-[#1E293B]">
                                                        {{ formatValue(getCounter(item).long_sheet_total) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div v-if="item.status === 'pending' || item.status === 'processing'" class="rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-600">
                                            Gambar sedang dianalisis...
                                        </div>

                                        <div v-else class="rounded-2xl bg-rose-50 p-4 text-sm font-bold text-rose-600">
                                            Data hasil analisis tidak valid.
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div v-if="dataList.length === 0" class="py-16 text-center text-sm font-bold text-slate-400">
                            Belum ada data hasil upload mesin cetak.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>