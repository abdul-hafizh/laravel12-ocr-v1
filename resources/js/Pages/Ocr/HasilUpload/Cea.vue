<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const title = 'Hasil Upload CEA';
const page = usePage();

const dataList = ref([]);
const loading = ref(false);
const deleting = ref(false);

const showImageModal = ref(false);
const selectedImageUrl = ref('');

const canDeleteScan = computed(() => {
    return page.props.auth?.permissions?.includes('DELETE_IMAGE_SCAN');
});

const getData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            params: {
                scan_type: 'cea',
            },
        });

        dataList.value = res.data.data || [];
    } catch (error) {
        console.error('Gagal mengambil data CEA:', error);
    } finally {
        loading.value = false;
    }
};

const deleteData = async (item) => {
    if (!confirm(`Yakin ingin menghapus data scan #${item.id}?`)) return;

    try {
        deleting.value = true;

        await axios.delete(`/image-scans/${item.id}`);

        dataList.value = dataList.value.filter(row => row.id !== item.id);

        alert('Data berhasil dihapus');
    } catch (error) {
        console.error(error);
        alert('Gagal menghapus data');
    } finally {
        deleting.value = false;
    }
};

const openImagePreview = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
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

const getValidStatus = (item) => {
    const parsed = parseResult(item);
    return parsed?.valid;
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

const getValidClass = (valid) => {
    return valid === true
        ? 'bg-emerald-100 text-emerald-700'
        : valid === false
            ? 'bg-rose-100 text-rose-700'
            : 'bg-slate-100 text-slate-600';
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Hasil Upload <span class="text-[#2DD4BF]">CEA</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-3xl border border-slate-200">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">
                                Data Counter Mesin CEA
                            </h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                Counter 101 Total, 301 Print, dan 201 Copy
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
                                <div
                                    class="w-full lg:w-44 flex-shrink-0 cursor-pointer"
                                    @click="openImagePreview(item.image_path ? '/storage/' + item.image_path : '/no-image.png')"
                                >
                                    <img
                                        :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                        class="h-44 w-full lg:w-44 rounded-2xl border border-slate-200 object-cover hover:opacity-80 transition-opacity"
                                    />

                                    <div class="mt-2 text-center text-[10px] font-black uppercase text-slate-400">
                                        Klik untuk lihat gambar
                                    </div>
                                </div>

                                <div class="flex-1 space-y-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
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

                                            <span
                                                class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                                :class="getValidClass(getValidStatus(item))"
                                            >
                                                {{ getValidStatus(item) === true ? 'Valid' : getValidStatus(item) === false ? 'Tidak Valid' : 'Belum Dianalisa' }}
                                            </span>

                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                                {{ formatDate(item.created_at) }}
                                            </span>
                                        </div>

                                        <button
                                            v-if="canDeleteScan"
                                            @click="deleteData(item)"
                                            :disabled="deleting"
                                            class="px-4 py-2 rounded-xl bg-red-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-red-600 transition disabled:opacity-50"
                                        >
                                            Hapus
                                        </button>
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

                                        <div class="rounded-2xl bg-[#2DD4BF]/10 p-4 border border-[#2DD4BF]/20">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-[#0F766E]">
                                                Serial Number
                                            </div>
                                            <div class="mt-1 text-lg font-black text-[#1E293B] uppercase">
                                                {{ getValue(item, 'serial_number') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Informasi Mesin
                                            </h4>

                                            <div class="space-y-2">
                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Tanggal</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ getValue(item, 'tanggal') }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Lokasi</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ getValue(item, 'lokasi') }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">Nama / Tipe Mesin</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ getValue(item, 'nama_mesin') }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-[#2DD4BF]/10 p-3 text-sm">
                                                    <span class="font-bold text-[#0F766E]">Serial Number</span>
                                                    <span class="font-black text-[#1E293B] uppercase text-right">
                                                        {{ getValue(item, 'serial_number') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <h4 class="mb-3 text-[11px] font-black uppercase tracking-widest text-slate-500">
                                                Counter CEA
                                            </h4>

                                            <div class="space-y-2">
                                                <div class="flex justify-between gap-4 rounded-xl bg-emerald-50 p-3 text-sm">
                                                    <span class="font-bold text-emerald-600">101 Total Counter Mesin</span>
                                                    <span class="font-black text-emerald-700 text-right">
                                                        {{ formatValue(getValue(item, 'total_counter_mesin', 0)) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">301 Print Counter</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'print_counter', 0)) }}
                                                    </span>
                                                </div>

                                                <div class="flex justify-between gap-4 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <span class="font-bold text-slate-500">201 Copy Counter</span>
                                                    <span class="font-black text-[#1E293B] text-right">
                                                        {{ formatValue(getValue(item, 'copy_counter', 0)) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="parseResult(item)?.message"
                                        class="rounded-2xl bg-amber-50 p-4 text-sm font-bold text-amber-700"
                                    >
                                        {{ parseResult(item)?.message }}
                                    </div>

                                    <div
                                        v-if="item.error_message"
                                        class="rounded-2xl bg-rose-50 p-4 text-sm font-bold text-rose-600"
                                    >
                                        {{ item.error_message }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="dataList.length === 0"
                            class="py-16 text-center text-sm font-bold text-slate-400"
                        >
                            Belum ada data hasil upload CEA.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showImageModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            @click="showImageModal = false"
        >
            <div class="relative max-w-4xl w-full" @click.stop>
                <button
                    @click="showImageModal = false"
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 font-bold"
                >
                    TUTUP [X]
                </button>

                <img
                    :src="selectedImageUrl"
                    class="w-full h-auto max-h-[80vh] object-contain rounded-2xl shadow-2xl"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>