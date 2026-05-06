<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    hargaBiayas: Object,
    cabangs: Array,
    vendors: Array,
    filters: Object,
    flash: Object,
});

const kategoriOptions = [
    { value: 'biaya_umum', label: 'Biaya Umum' },
    { value: 'token_listrik', label: 'Token Listrik' },
    { value: 'klik_meter', label: 'Klik Meter' },
    { value: 'part', label: 'Part' },
    { value: 'maintenance_mesin', label: 'Maintenance Mesin' },
    { value: 'lembur', label: 'Lembur' },
    { value: 'kendaraan', label: 'Kendaraan' },
    { value: 'sewa_cabang', label: 'Sewa Cabang' },
    { value: 'skpd', label: 'SKPD' },
];

const tipeHargaOptions = [
    { value: 'warna', label: 'Warna' },
    { value: 'hitam_putih', label: 'Hitam Putih' },
    { value: 'minimum', label: 'Minimum' },
    { value: 'normal', label: 'Normal' },
    { value: 'part', label: 'Part' },
    { value: 'lembur', label: 'Lembur' },
];

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');
const kategoriBiaya = ref(props.filters.kategori_biaya || '');
const isCoa = ref(props.filters.is_coa || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_cabang_id: '',
    master_vendor_id: '',
    kategori_biaya: '',
    nama_biaya: '',
    tipe_harga: '',
    nominal: 0,
    satuan: '',
    is_coa: false,
    kode_coa: '',
    nama_coa: '',
    keterangan: '',
    is_active: true,
});

watch([search, masterCabangId, kategoriBiaya, isCoa], ([s, c, k, coa]) => {
    router.get(
        route('master-harga-biaya.index'),
        { search: s, master_cabang_id: c, kategori_biaya: k, is_coa: coa, page: 1 },
        { preserveState: true, replace: true }
    );
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();
    form.master_cabang_id = item.master_cabang_id || '';
    form.master_vendor_id = item.master_vendor_id || '';
    form.kategori_biaya = item.kategori_biaya || '';
    form.nama_biaya = item.nama_biaya || '';
    form.tipe_harga = item.tipe_harga || '';
    form.nominal = item.nominal || 0;
    form.satuan = item.satuan || '';
    form.is_coa = Boolean(item.is_coa);
    form.kode_coa = item.kode_coa || '';
    form.nama_coa = item.nama_coa || '';
    form.keterangan = item.keterangan || '';
    form.is_active = Boolean(item.is_active);
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (!form.is_coa) {
        form.kode_coa = '';
        form.nama_coa = '';
    }

    if (isEdit.value) {
        form.put(route('master-harga-biaya.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-harga-biaya.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    isDeleting.value = true;
    router.delete(route('master-harga-biaya.destroy', idToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            idToDelete.value = null;
        },
        onFinish: () => isDeleting.value = false,
    });
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const getLabel = (options, value) => {
    return options.find((item) => item.value === value)?.label || value || '-';
};
</script>

<template>

    <Head title="Master Harga / Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Harga & Biaya</span>
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Filter Bar -->
            <div
                class="grid grid-cols-1 md:grid-cols-5 gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="relative md:col-span-1">
                    <input v-model="search" type="text" placeholder="Cari biaya..."
                        class="w-full pl-4 pr-4 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400" />
                </div>

                <select v-model="masterCabangId"
                    class="bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                    <option value="">Semua Cabang</option>
                    <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                </select>

                <select v-model="kategoriBiaya"
                    class="bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                    <option value="">Semua Kategori</option>
                    <option v-for="opt in kategoriOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>

                <select v-model="isCoa"
                    class="bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                    <option value="">Filter COA</option>
                    <option value="1">COA</option>
                    <option value="0">Bukan COA</option>
                </select>

                <button @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all flex items-center justify-center shadow-lg shadow-black/5">
                    + Tambah Biaya
                </button>
            </div>

            <!-- Table Data -->
            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Nama Biaya
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Cabang
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Kategori
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Nominal</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Satuan
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Kode COA</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Status</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <template v-if="hargaBiayas.data.length > 0">
                                <tr v-for="item in hargaBiayas.data" :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors">
                                    <!-- Kolom Nama Biaya -->
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-bold text-[#1E293B] uppercase tracking-tight">{{
                                            item.nama_biaya
                                            }}</span>
                                    </td>

                                    <!-- Kolom Cabang -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-[10px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 px-2 py-1 rounded uppercase italic border border-[#2DD4BF]/10">
                                            {{ item.cabang?.nama_cabang || 'Global' }}
                                        </span>
                                    </td>

                                    <!-- Kolom Kategori -->
                                    <td class="px-6 py-4">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase">
                                            {{ getLabel(kategoriOptions, item.kategori_biaya) }}
                                        </span>
                                    </td>

                                    <!-- Kolom Nominal -->
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-sm font-black text-[#1E293B]">{{ formatRupiah(item.nominal)
                                            }}</span>
                                    </td>

                                    <!-- Kolom Satuan -->
                                    <td class="px-6 py-4">
                                        <span class="text-[10px] text-slate-400 font-black uppercase italic">/ {{
                                            item.satuan ||
                                            'Pcs' }}</span>
                                    </td>

                                    <!-- Kolom COA -->
                                    <td class="px-6 py-4 text-center">
                                        <div v-if="item.is_coa" class="flex flex-col items-center">
                                            <span
                                                class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                                {{ item.kode_coa }}
                                            </span>
                                            <span class="text-[8px] text-slate-400 font-bold truncate max-w-[80px]">{{
                                                item.nama_coa
                                                }}</span>
                                        </div>
                                        <span v-else class="text-[10px] font-bold text-slate-300 italic">-</span>
                                    </td>

                                    <!-- Kolom Status -->
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10">
                                            {{ item.is_active ? 'Active' : 'Nonaktif' }}
                                        </span>
                                    </td>

                                    <!-- Kolom Aksi -->
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                        stroke-width="2.5" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                            <button @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                        stroke-width="2.5" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <h4
                                            class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                            No <span class="text-[#2DD4BF]">Cost Data</span> Found
                                        </h4>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                            Belum ada data harga biaya yang tersedia atau tidak ditemukan.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">

                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ hargaBiayas.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ hargaBiayas.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ hargaBiayas.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="hargaBiayas.links && hargaBiayas.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in hargaBiayas.links" :key="k">
                            <div v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label">
                            </div>
                            <Link v-else :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                }" v-html="link.label" preserve-scroll>
                            </Link>
                        </template>
                    </nav>

                    <!-- Placeholder jika benar-benar tidak ada navigasi (opsional) -->
                    <div v-else class="text-[10px] font-black text-slate-300 uppercase italic">
                        No additional pages
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Modal -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div
                class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-2xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4">
                    <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                        {{ isEdit ? 'Update' : 'Create' }} <span class="text-[#2DD4BF]">Cost Data</span>
                    </h3>
                    <button @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest">Close</button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cabang</label>
                            <select v-model="form.master_cabang_id"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                                <option value="">Global / Semua</option>
                                <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Kategori</label>
                            <select v-model="form.kategori_biaya"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                                <option value="">Pilih Kategori</option>
                                <option v-for="opt in kategoriOptions" :key="opt.value" :value="opt.value">{{ opt.label
                                    }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nama Biaya /
                            Part</label>
                        <input v-model="form.nama_biaya" type="text" placeholder="Masukkan nama biaya..."
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-1.5 col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nominal
                                (IDR)</label>
                            <input v-model="form.nominal" type="number"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Satuan</label>
                            <input v-model="form.satuan" type="text" placeholder="Ex: Lembar"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <!-- COA Section -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Hubungkan
                                dengan
                                COA</span>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" v-model="form.is_coa" class="peer sr-only" />
                                <div
                                    class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]">
                                </div>
                            </label>
                        </div>

                        <div v-if="form.is_coa" class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-2">
                            <input v-model="form.kode_coa" type="text" placeholder="Kode COA"
                                class="w-full px-4 py-2 bg-white border-none rounded-xl text-xs font-bold shadow-sm focus:ring-2 focus:ring-[#2DD4BF]/20" />
                            <input v-model="form.nama_coa" type="text" placeholder="Nama COA"
                                class="w-full px-4 py-2 bg-white border-none rounded-xl text-xs font-bold shadow-sm focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Status Aktif
                            Data</span>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div
                                class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]">
                            </div>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all">
                        {{ form.processing ? 'Saving...' : 'Execute Data' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <div
                    class="mx-auto w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mb-6 border border-rose-100">
                    <svg :class="{ 'animate-spin': isDeleting }" class="w-8 h-8" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path v-if="!isDeleting"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path v-else
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            fill="currentColor" opacity="0.75" />
                    </svg>
                </div>
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">Confirm <span
                        class="text-rose-500">Delete</span></h3>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">Data yang dihapus tidak
                    dapat
                    dikembalikan. Yakin?</p>
                <div class="flex gap-3">
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest">Batal</button>
                    <button @click="executeDelete" :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-500/30">Hapus</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>