<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import CreateManualScanModal from './Partials/CreateManualScanModal.vue';
import EditManualScanModal from './Partials/EditManualScanModal.vue';

const props = defineProps({
    scans: Object,
    cabangs: Array,
    mesins: Array,
    tokens: Array,
    scanTypes: Array,
    filters: Object,
});

const title = 'Manual Upload';
const page = usePage();

const canDeleteScan = computed(() => {
    return page.props.auth?.permissions?.includes('DELETE_IMAGE_SCAN');
});

const filters = ref({
    search: props.filters?.search || '',
    scan_type: props.filters?.scan_type || '',
    cabang_id: props.filters?.cabang_id || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
});

const applyFilters = () => {
    router.get('/manual', filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filters.value = {
        search: '',
        scan_type: '',
        cabang_id: '',
        date_from: '',
        date_to: '',
    };

    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const scanTypeLabel = (key) => {
    return props.scanTypes.find((s) => s.key === key)?.label || key;
};

const groupedScans = computed(() => {
    const groups = [];
    let current = null;

    for (const item of props.scans.data) {
        const cabangId = item.cabang_id ?? 'no-cabang';

        if (!current || current.cabangId !== cabangId) {
            current = {
                cabangId,
                cabang: item.cabang,
                items: [],
            };
            groups.push(current);
        }

        current.items.push(item);
    }

    return groups;
});

const deleting = ref(false);

const deleteScan = async (item) => {
    if (!confirm(`Yakin ingin menghapus data scan #${item.id}?`)) {
        return;
    }

    try {
        deleting.value = true;
        await axios.delete(`/image-scans/${item.id}`);
        router.reload({ only: ['scans'] });
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

const parseResult = (item) => {
    try {
        if (!item.analysis_result) return null;

        if (typeof item.analysis_result === 'object') {
            return item.analysis_result;
        }

        return JSON.parse(item.analysis_result);
    } catch (e) {
        return null;
    }
};

const getDataPenting = (item) => {
    return parseResult(item)?.data_penting || {};
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

const getStatusClass = (status) => {
    return {
        'bg-yellow-100 text-yellow-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'processing',
        'bg-green-100 text-green-700': status === 'success',
        'bg-red-100 text-red-700': status === 'failed',
    };
};

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingScan = ref(null);

const openEdit = (item) => {
    editingScan.value = item;
    showEditModal.value = true;
};

const onCreated = () => {
    showCreateModal.value = false;
    router.reload({ only: ['scans'] });
};

const onUpdated = () => {
    showEditModal.value = false;
    editingScan.value = null;
    router.reload({ only: ['scans'] });
};
</script>

<template>

    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Manual <span class="text-[#2DD4BF]">Upload</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-3xl border border-slate-200">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black text-[#1E293B] uppercase tracking-tight">
                                Hasil Upload Lapangan
                            </h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                Digroup per cabang, bisa upload manual dan edit data hasil OCR
                            </p>
                        </div>

                        <button @click="showCreateModal = true"
                            class="px-5 py-3 rounded-xl bg-[#2DD4BF] text-white text-[11px] font-black uppercase tracking-widest hover:bg-[#1E293B] transition">
                            + Buat Baru
                        </button>
                    </div>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
                        <input v-model="filters.search" type="text" placeholder="Cari user / cabang..."
                            class="rounded-xl border-slate-300 text-sm md:col-span-2" @keyup.enter="applyFilters" />

                        <select v-model="filters.scan_type" class="rounded-xl border-slate-300 text-sm">
                            <option value="">Semua Jenis Data</option>
                            <option v-for="type in scanTypes" :key="type.key" :value="type.key">
                                {{ type.label }}
                            </option>
                        </select>

                        <select v-model="filters.cabang_id" class="rounded-xl border-slate-300 text-sm">
                            <option value="">Semua Cabang</option>
                            <option v-for="cabang in cabangs" :key="cabang.id" :value="cabang.id">
                                {{ cabang.nama_cabang }}
                            </option>
                        </select>

                        <input v-model="filters.date_from" type="date" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="filters.date_to" type="date" class="rounded-xl border-slate-300 text-sm" />

                        <button @click="applyFilters"
                            class="px-4 py-2 rounded-xl bg-[#1E293B] text-white text-[10px] font-black uppercase tracking-widest">
                            Filter
                        </button>

                        <button @click="resetFilters"
                            class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest">
                            Reset
                        </button>
                    </div>

                    <div v-if="groupedScans.length === 0" class="py-16 text-center text-sm font-bold text-slate-400">
                        Belum ada data hasil upload.
                    </div>

                    <div v-else class="space-y-8">
                        <div v-for="group in groupedScans" :key="group.cabangId">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="rounded-full bg-[#1E293B] px-4 py-1.5 text-xs font-black uppercase tracking-widest text-white">
                                    {{ group.cabang?.nama_cabang || 'Tanpa Cabang' }}
                                </span>
                                <span class="text-xs font-bold text-slate-400">
                                    {{ group.items.length }} data
                                </span>
                            </div>

                            <div class="grid gap-4">
                                <div v-for="item in group.items" :key="item.id"
                                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex flex-col gap-5 lg:flex-row">
                                        <div class="w-full lg:w-36 flex-shrink-0 cursor-pointer"
                                            @click="openImagePreview(item.image_path ? '/storage/' + item.image_path : '/no-image.png')">
                                            <img :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                                class="h-36 w-full lg:w-36 rounded-2xl border border-slate-200 object-cover hover:opacity-80 transition-opacity" />
                                        </div>

                                        <div class="flex-1 space-y-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                                        #{{ item.id }} · {{ scanTypeLabel(item.scan_type) }}
                                                    </span>

                                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                                        :class="getStatusClass(item.status)">
                                                        {{ item.status }}
                                                    </span>

                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-600">
                                                        {{ formatDate(item.created_at) }}
                                                    </span>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <button @click="openEdit(item)"
                                                        class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition">
                                                        Edit
                                                    </button>

                                                    <button v-if="canDeleteScan" @click="deleteScan(item)" :disabled="deleting"
                                                        class="px-4 py-2 rounded-xl bg-red-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-red-600 transition disabled:opacity-50">
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                                                <div class="rounded-xl bg-slate-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">User</div>
                                                    <div class="font-black text-[#1E293B] text-xs">{{ item.user?.name || '-' }}</div>
                                                </div>

                                                <div v-if="item.scan_type === 'electricity'" class="rounded-xl bg-emerald-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-emerald-600">kWh</div>
                                                    <div class="font-black text-emerald-700 text-xs">{{ getValue(item, 'kwh') }}</div>
                                                </div>
                                                <div v-if="item.scan_type === 'electricity'" class="rounded-xl bg-slate-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">No Meter</div>
                                                    <div class="font-black text-[#1E293B] text-xs">{{ getValue(item, 'nomor_meter') }}</div>
                                                </div>

                                                <div v-if="['printer','cea','asaba'].includes(item.scan_type)" class="rounded-xl bg-slate-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Mesin</div>
                                                    <div class="font-black text-[#1E293B] text-xs">{{ item.mesin?.nama_mesin || '-' }}</div>
                                                </div>
                                                <div v-if="['printer','cea','asaba'].includes(item.scan_type)" class="rounded-xl bg-slate-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">SN</div>
                                                    <div class="font-black text-[#1E293B] text-xs">{{ item.mesin?.serial_number || '-' }}</div>
                                                </div>

                                                <div v-if="item.scan_type === 'part_maintenance'" class="rounded-xl bg-slate-50 p-3">
                                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Nominal</div>
                                                    <div class="font-black text-[#1E293B] text-xs">{{ getValue(item, 'nominal_chat') }}</div>
                                                </div>
                                            </div>

                                            <div v-if="item.error_message" class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-600">
                                                {{ item.error_message }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="scans.data.length > 0"
                        class="mt-6 flex flex-col md:flex-row items-center justify-between gap-3 border-t border-slate-200 pt-5">
                        <div class="text-xs font-bold text-slate-500">
                            Menampilkan {{ scans.from || 0 }} - {{ scans.to || 0 }} dari {{ scans.total }} data
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="goToPage(scans.prev_page_url)" :disabled="!scans.prev_page_url"
                                class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase disabled:opacity-40">
                                Prev
                            </button>

                            <div class="text-xs font-black text-slate-600">
                                Page {{ scans.current_page }} / {{ scans.last_page }}
                            </div>

                            <button @click="goToPage(scans.next_page_url)" :disabled="!scans.next_page_url"
                                class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase disabled:opacity-40">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            @click="showImageModal = false">
            <div class="relative max-w-4xl w-full" @click.stop>
                <button @click="showImageModal = false"
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 font-bold">
                    TUTUP [X]
                </button>
                <img :src="selectedImageUrl" class="w-full h-auto max-h-[80vh] object-contain rounded-2xl shadow-2xl" />
            </div>
        </div>

        <CreateManualScanModal v-if="showCreateModal" :cabangs="cabangs" :mesins="mesins" :scan-types="scanTypes"
            @close="showCreateModal = false" @created="onCreated" />

        <EditManualScanModal v-if="showEditModal && editingScan" :scan="editingScan" :mesins="mesins" :tokens="tokens"
            @close="showEditModal = false" @updated="onUpdated" />
    </AuthenticatedLayout>
</template>
