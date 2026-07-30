<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const title = 'Hasil Upload Token Listrik';
const page = usePage();

const dataList = ref([]);
const loading = ref(false);

const canDeleteScan = computed(() => {
    return page.props.auth?.permissions?.includes(
        'DELETE_IMAGE_SCAN'
    );
});

const deleting = ref(false);

const deleteData = async (item) => {
    if (!confirm(`Yakin ingin menghapus data scan #${item.id}?`)) {
        return;
    }

    try {
        deleting.value = true;

        await axios.delete(`/image-scans/${item.id}`);

        dataList.value = dataList.value.filter(
            row => row.id !== item.id
        );

        alert('Data berhasil dihapus');
    } catch (error) {
        console.error(error);
        alert('Gagal menghapus data');
    } finally {
        deleting.value = false;
    }
};

const showImageModal = ref(false);
const selectedImageUrl = ref('');

const openImagePreview = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
};

const filters = ref({
    search: '',
    status: '',
    date_from: '',
    date_to: '',
    per_page: 10,
});

const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null,
    to: null,
});

const getData = async (pageNumber = 1) => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            withCredentials: true,
            params: {
                scan_type: 'electricity',
                page: pageNumber,
                search: filters.value.search,
                status: filters.value.status,
                date_from: filters.value.date_from,
                date_to: filters.value.date_to,
                per_page: filters.value.per_page,
            },
        });

        dataList.value = res.data.data || [];
        meta.value = res.data.meta || meta.value;
    } catch (error) {
        console.error('Gagal mengambil data token listrik:', error);
    } finally {
        loading.value = false;
    }
};

const resetFilter = () => {
    filters.value = {
        search: '',
        status: '',
        date_from: '',
        date_to: '',
        per_page: 10,
    };

    getData(1);
};

const nextPage = () => {
    if (meta.value.current_page < meta.value.last_page) {
        getData(meta.value.current_page + 1);
    }
};

const prevPage = () => {
    if (meta.value.current_page > 1) {
        getData(meta.value.current_page - 1);
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

                        <button @click="getData"
                            class="px-4 py-2 rounded-xl bg-[#1E293B] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#2DD4BF] transition">
                            Refresh
                        </button>
                    </div>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Cari user, cabang, teks..."
                            class="rounded-xl border-slate-300 text-sm"
                            @keyup.enter="getData(1)"
                        />

                        <select
                            v-model="filters.status"
                            class="rounded-xl border-slate-300 text-sm"
                        >
                            <option value="">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>

                        <input
                            v-model="filters.date_from"
                            type="date"
                            class="rounded-xl border-slate-300 text-sm"
                        />

                        <input
                            v-model="filters.date_to"
                            type="date"
                            class="rounded-xl border-slate-300 text-sm"
                        />

                        <select
                            v-model="filters.per_page"
                            class="rounded-xl border-slate-300 text-sm"
                        >
                            <option :value="5">5 Data</option>
                            <option :value="10">10 Data</option>
                            <option :value="25">25 Data</option>
                            <option :value="50">50 Data</option>
                        </select>

                        <button
                            @click="getData(1)"
                            class="px-4 py-2 rounded-xl bg-[#2DD4BF] text-white text-[10px] font-black uppercase tracking-widest"
                        >
                            Filter
                        </button>

                        <button
                            @click="resetFilter"
                            class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest"
                        >
                            Reset
                        </button>
                    </div>

                    <div v-if="loading" class="py-16 text-center text-sm font-bold text-slate-400">
                        Memuat data...
                    </div>

                    <div v-else class="grid gap-5">
                        <div v-for="(item, index) in dataList" :key="item.id"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-5 lg:flex-row">
                                <div class="w-full lg:w-40 flex-shrink-0 cursor-pointer"
                                    @click="openImagePreview(item.image_path ? '/storage/' + item.image_path : '/no-image.png')">
                                    <img :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                        class="h-40 w-full lg:w-40 rounded-2xl border border-slate-200 object-cover hover:opacity-80 transition-opacity" />
                                </div>

                                <div class="flex-1 space-y-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded-full bg-[#1E293B] px-3 py-1 text-[10px] font-black uppercase text-white">
                                                No. {{ (meta.from ?? 1) + index }}
                                            </span>

                                            <span
                                                class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                                ID Scan: #{{ item.id }}
                                            </span>

                                            <span
                                                class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                                :class="getStatusClass(item.status)">
                                                {{ item.status }}
                                            </span>

                                            <span
                                                class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                                {{ formatDate(item.created_at) }}
                                            </span>
                                        </div>

                                        <button
                                            v-if="canDeleteScan"
                                            @click="deleteData(item)"
                                            :disabled="deleting"
                                            class="px-4 py-2 rounded-xl bg-red-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-red-600 transition disabled:opacity-50">
                                            Hapus
                                        </button>
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
                                                kWh
                                            </div>
                                            <div class="mt-1 text-xl font-black text-emerald-700">
                                                {{ formatValue(getValue(item, 'kwh')) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4
                                                class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Informasi Token
                                            </h4>

                                            <div class="space-y-2">
                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Tanggal</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'tanggal')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Nomor Meter</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'barcode')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">IDPEL</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'nomor_token')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                    <span class="font-bold text-emerald-600">kWh</span>
                                                    <span class="font-black text-emerald-700 text-right">
                                                        {{ formatValue(getValue(item, 'kwh')) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4
                                                class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Lokasi
                                            </h4>

                                            <div class="space-y-2">
                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Lokasi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'lokasi')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Kecamatan</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'kecamatan')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Kota</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'kota')) }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Provinsi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'provinsi')) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
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
                            Belum ada data hasil upload token listrik.
                        </div>

                        <div
                            v-if="dataList.length > 0"
                            class="mt-6 flex flex-col md:flex-row items-center justify-between gap-3 border-t border-slate-200 pt-5"
                        >
                            <div class="text-xs font-bold text-slate-500">
                                Menampilkan {{ meta.from || 0 }} - {{ meta.to || 0 }}
                                dari {{ meta.total }} data
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="prevPage"
                                    :disabled="meta.current_page <= 1 || loading"
                                    class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase disabled:opacity-40"
                                >
                                    Prev
                                </button>

                                <div class="text-xs font-black text-slate-600">
                                    Page {{ meta.current_page }} / {{ meta.last_page }}
                                </div>

                                <button
                                    @click="nextPage"
                                    :disabled="meta.current_page >= meta.last_page || loading"
                                    class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showImageModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            @click="showImageModal = false">

            <div class="relative max-w-4xl w-full" @click.stop>
                <button @click="showImageModal = false"
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 font-bold">
                    TUTUP [X]
                </button>

                <img :src="selectedImageUrl" class="w-full h-auto max-h-[80vh] object-contain rounded-2xl shadow-2xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>