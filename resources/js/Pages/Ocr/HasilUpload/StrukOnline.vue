<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const title = 'Hasil Upload Struk Online';

const dataList = ref([]);
const loading = ref(false);

const showImageModal = ref(false);
const selectedImageUrl = ref('');

const openImagePreview = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
};

const isZoomed = ref(false);
const toggleZoom = () => {
    isZoomed.value = !isZoomed.value;
};

const getData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            params: { scan_type: 'online_receipt' },
        });

        dataList.value = res.data.data || [];
    } catch (error) {
        console.error('Gagal mengambil data struk online:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(getData);

const parseResult = (item) => {
    try {
        if (!item.analysis_result) return null;
        if (typeof item.analysis_result === 'object') return item.analysis_result;
        return JSON.parse(item.analysis_result);
    } catch (e) {
        console.error('JSON parse error:', e);
        return null;
    }
};

const getDataPenting = (item) => {
    const parsed = parseResult(item);
    return parsed?.data_penting || parsed || {};
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

const formatRupiah = (value) => {
    if (value === null || value === undefined || value === '') return '-';

    const number = Number(value);
    if (isNaN(number)) return value;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(number);
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
                Hasil Upload <span class="text-[#2DD4BF]">Struk Online</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-3xl border border-slate-200">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">
                                Data Struk Online
                            </h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                Invoice / bukti transaksi / pembelian token listrik
                            </p>
                        </div>

                        <button @click="getData"
                            class="px-4 py-2 rounded-xl bg-[#1E293B] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#2DD4BF] transition">
                            Refresh
                        </button>
                    </div>

                    <div v-if="loading" class="py-16 text-center text-sm font-bold text-slate-400">
                        Memuat data...
                    </div>

                    <div v-else class="grid gap-5">
                        <div v-for="item in dataList" :key="item.id"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-5 lg:flex-row">
                                <div class="w-full lg:w-40 flex-shrink-0 cursor-pointer"
                                    @click="openImagePreview(item.image_path ? '/storage/' + item.image_path : '/no-image.png')">
                                    <img :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                        class="h-40 w-full lg:w-40 rounded-2xl border border-slate-200 object-cover hover:opacity-80 transition-opacity" />
                                </div>

                                <div class="flex-1 space-y-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                            ID Scan: #{{ item.id }}
                                        </span>

                                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                            :class="getStatusClass(item.status)">
                                            {{ item.status }}
                                        </span>

                                        <span
                                            class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                            {{ formatDate(item.created_at) }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div
                                                class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                User Upload
                                            </div>
                                            <div class="mt-1 text-sm font-black text-[#1E293B]">
                                                {{ item.user_name || item.user?.name || '-' }}
                                            </div>
                                            <div class="text-xs font-bold text-slate-400">
                                                {{ item.user_phone || item.user?.phone || item.user_email ||
                                                    item.user?.email ||
                                                    '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div
                                                class="text-[10px] font-black uppercase tracking-widest text-slate-400">
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
                                            <div
                                                class="text-[10px] font-black uppercase tracking-widest text-emerald-600">
                                                Total Pembayaran
                                            </div>
                                            <div class="mt-1 text-xl font-black text-emerald-700">
                                                {{ formatRupiah(getValue(item, 'total_pembayaran', 0)) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4
                                                class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Informasi Transaksi
                                            </h4>

                                            <div class="space-y-2">
                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Jenis Struk</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'jenis_struk')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Bank / Aplikasi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'bank_atau_aplikasi')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Status</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'status_transaksi')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Tanggal</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'tanggal_transaksi')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Waktu</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'waktu_transaksi')) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4
                                                class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Detail Pembayaran
                                            </h4>

                                            <div class="space-y-2">
                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Rekening Sumber</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'rekening_sumber')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Nama Rekening</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'nama_rekening_sumber')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Metode Pembayaran</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'metode_pembayaran')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                    <span class="font-bold text-emerald-600">Total Pembayaran</span>
                                                    <span class="font-black text-emerald-700 text-right">
                                                        {{ formatRupiah(getValue(item, 'total_pembayaran', 0)) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 p-4">
                                        <h4
                                            class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                            Data Pembelian
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Terminal</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'terminal')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Jenis Pembelian</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'jenis_pembelian')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Nomor Transaksi</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'nomor_transaksi')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Nomor Struk</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'nomor_struk')) }}
                                                </span>
                                            </div>

                                            <div
                                                class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm md:col-span-2">
                                                <span class="font-bold text-slate-500">Nomor Referensi</span>
                                                <span class="font-black text-[#1E293B] text-right break-all">
                                                    {{ formatValue(getValue(item, 'nomor_referensi')) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 p-4">
                                        <h4
                                            class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                            Data Token Listrik PLN
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">No Meter</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'nomor_meter')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">ID Pelanggan</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'id_pelanggan')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Nama Pelanggan</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'nama_pelanggan')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Tarif / Daya</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatValue(getValue(item, 'tarif_daya')) }}
                                                </span>
                                            </div>

                                            <div
                                                class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                <span class="font-bold text-emerald-600">Jumlah kWh</span>
                                                <span class="font-black text-emerald-700 text-right">
                                                    {{ formatValue(getValue(item, 'jumlah_kwh')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-yellow-50 p-3 text-sm">
                                                <span class="font-bold text-yellow-600">Stroom / Token</span>
                                                <span class="font-black text-yellow-700 text-right break-all">
                                                    {{ formatValue(getValue(item, 'stroom_token')) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 p-4">
                                        <h4
                                            class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                            Rincian Biaya PLN
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">RP Bayar</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'rp_bayar')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Materai</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'materai')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">PPN</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'ppn')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">PPJ-TL</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'ppj_tl')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Angsuran</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'angsuran')) }}
                                                </span>
                                            </div>

                                            <div
                                                class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                <span class="font-bold text-emerald-600">RP Stroom / Token</span>
                                                <span class="font-black text-emerald-700 text-right">
                                                    {{ formatRupiah(getValue(item, 'rp_stroom_token')) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                <span class="font-bold text-slate-500">Admin Bank</span>
                                                <span class="font-black text-[#1E293B] text-right">
                                                    {{ formatRupiah(getValue(item, 'admin_bank')) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="getValue(item, 'catatan', null)"
                                        class="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-600">
                                        {{ getValue(item, 'catatan') }}
                                    </div>

                                    <div v-if="item.error_message"
                                        class="rounded-2xl bg-rose-50 p-4 text-sm font-bold text-rose-600">
                                        {{ item.error_message }}
                                    </div>

                                    <div v-if="item.status === 'pending' || item.status === 'processing'"
                                        class="rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-600">
                                        Gambar sedang dianalisis...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="dataList.length === 0" class="py-16 text-center text-sm font-bold text-slate-400">
                            Belum ada data hasil upload struk online.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showImageModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
            @click="showImageModal = false">

            <div class="relative w-full h-full overflow-auto flex items-start justify-center p-10" @click.stop>

                <button @click="showImageModal = false"
                    class="fixed top-6 right-6 text-white bg-white/10 hover:bg-white/20 p-3 rounded-full backdrop-blur-md font-black uppercase text-xs">
                    Tutup [ESC]
                </button>

                <img :src="selectedImageUrl"
                    class="w-full max-w-2xl h-auto object-contain cursor-zoom-in hover:scale-[2] transition-transform duration-300 origin-top"
                    @click="toggleZoom" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>