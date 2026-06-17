<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps({
    mesins: Object,
    cabangs: Array,
    vendors: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');
const masterVendorId = ref(props.filters.master_vendor_id || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

watch([search, masterCabangId, masterVendorId], ([searchValue, cabangValue, vendorValue]) => {
    router.get(
        route('master-mesin.index'),
        {
            search: searchValue,
            master_cabang_id: cabangValue,
            master_vendor_id: vendorValue,
            page: 1,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
});

const form = useForm({
    master_cabang_id: '',
    master_vendor_id: '',
    nama_mesin: '',
    merk: '',
    tipe: '',
    serial_number: '',
    minimum_charge_size: '',

    harga_color_a3: 0,
    harga_color_a4: 0,
    harga_bw_a3: 0,
    harga_bw_a4: 0,

    minimum_charge_click: 0,
    minimum_charge_nominal: 0,

    over_click_color_a3: 0,
    over_click_color_a4: 0,
    over_click_bw_a3: 0,
    over_click_bw_a4: 0,

    free_klik_percent: 0,

    keterangan: '',
    is_active: true,
    maintenance_parts: [],
});

const resetForm = () => {
    form.reset();
    form.clearErrors();

    form.master_cabang_id = '';
    form.master_vendor_id = '';
    form.nama_mesin = '';
    form.merk = '';
    form.tipe = '';
    form.serial_number = '';

    form.harga_color_a3 = 0;
    form.harga_color_a4 = 0;
    form.harga_bw_a3 = 0;
    form.harga_bw_a4 = 0;
    form.minimum_charge_size = '';

    form.free_klik_percent = 0;
    form.minimum_charge_click = 0;
    form.minimum_charge_nominal = 0;

    form.over_click_color_a3 = 0;
    form.over_click_color_a4 = 0;
    form.over_click_bw_a3 = 0;
    form.over_click_bw_a4 = 0;

    form.keterangan = '';
    form.is_active = true;
    form.maintenance_parts = [];
};

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;
    resetForm();
    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();

    form.master_cabang_id = item.master_cabang_id || '';
    form.master_vendor_id = item.master_vendor_id || '';
    form.nama_mesin = item.nama_mesin || '';
    form.merk = item.merk || '';
    form.tipe = item.tipe || '';
    form.serial_number = item.serial_number || '';
    form.minimum_charge_size = item.minimum_charge_size || '';

    form.harga_color_a3 = Number(item.harga_color_a3 || 0);
    form.harga_color_a4 = Number(item.harga_color_a4 || 0);
    form.harga_bw_a3 = Number(item.harga_bw_a3 || 0);
    form.harga_bw_a4 = Number(item.harga_bw_a4 || 0);

    form.minimum_charge_click = Number(item.minimum_charge_click || 0);
    form.minimum_charge_nominal = Number(item.minimum_charge_nominal || 0);

    form.over_click_color_a3 = Number(item.over_click_color_a3 || 0);
    form.over_click_color_a4 = Number(item.over_click_color_a4 || 0);
    form.over_click_bw_a3 = Number(item.over_click_bw_a3 || 0);
    form.over_click_bw_a4 = Number(item.over_click_bw_a4 || 0);

    form.free_klik_percent = Number(item.free_klik_percent || 0);

    form.keterangan = item.keterangan || '';
    form.is_active = Boolean(item.is_active);

    form.maintenance_parts = (item.maintenance_parts || []).map((part) => ({
        nama_part: part.nama_part || '',
    }));

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    resetForm();
};

const addPart = () => {
    form.maintenance_parts.push({
        nama_part: '',
    });
};

const removePart = (index) => {
    form.maintenance_parts.splice(index, 1);
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-mesin.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-mesin.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    idToDelete.value = null;
};

const executeDelete = () => {
    isDeleting.value = true;

    router.delete(route('master-mesin.destroy', idToDelete.value), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>

    <Head title="Master Mesin" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Mesin</span>
            </h2>
        </template>

        <div class="space-y-6 w-full">
            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="flex flex-col md:flex-row flex-1 gap-4 max-w-3xl min-w-0">
                    <div class="relative flex-1 min-w-0">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3"
                                    stroke-linecap="round" />
                            </svg>
                        </span>

                        <input v-model="search" type="text"
                            placeholder="Cari nama mesin / merk / tipe / serial number..."
                            class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all" />
                    </div>

                    <select v-model="masterCabangId"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20">
                        <option value="">Semua Cabang</option>
                        <option v-for="c in cabangs" :key="c.id" :value="c.id">
                            {{ c.nama_cabang }}
                        </option>
                    </select>

                    <select v-model="masterVendorId"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20">
                        <option value="">Semua Vendor</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">
                            {{ v.nama_vendor }}
                        </option>
                    </select>
                </div>

                <button @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5 flex items-center justify-center">
                    <span class="mr-2 text-sm">+</span> Tambah Mesin
                </button>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm w-full overflow-hidden">
                <div class="w-full overflow-x-auto">
                    <table class="min-w-[900px] w-full text-left border-collapse table-fixed">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th rowspan="2"
                                    class="sticky left-0 z-20 bg-slate-50 w-[200px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Mesin & No Seri</th>
                                <th rowspan="2"
                                    class="w-[150px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Vendor & Cabang</th>                                
                                <th colspan="4"
                                    class="px-2 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b">
                                    HPP / KLIK [Rp]</th>
                                <th rowspan="2"
                                    class="w-[100px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Min Charge</th>
                                <th rowspan="2"
                                    class="w-[80px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Free Klik</th>
                                <th rowspan="2"
                                    class="w-[100px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Status</th>
                                <th rowspan="2"
                                    class="w-[100px] px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi</th>
                            </tr>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-2 py-2 text-[9px] font-black text-slate-500 uppercase text-right">Color A3
                                </th>
                                <th class="px-2 py-2 text-[9px] font-black text-slate-500 uppercase text-right">Color A4
                                </th>
                                <th class="px-2 py-2 text-[9px] font-black text-slate-500 uppercase text-right">BW A3
                                </th>
                                <th class="px-2 py-2 text-[9px] font-black text-slate-500 uppercase text-right">BW A4
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="mesins.data.length > 0">
                                <tr v-for="item in mesins.data" :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors">
                                    <td
                                        class="sticky left-0 z-10 bg-white group-hover:bg-slate-50/50 px-6 py-4 truncate border-r border-slate-100">
                                        <div class="text-sm font-bold text-[#1E293B] uppercase tracking-tight">{{
                                            item.nama_mesin }}</div>
                                        <span
                                            class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg uppercase tracking-tighter">
                                            {{ item.serial_number || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-[11px] font-black text-[#1E293B] uppercase truncate">{{
                                            item.vendor?.nama_vendor || '-' }}</div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase truncate">{{
                                            item.cabang?.nama_cabang || '-' }}</div>
                                    </td>
                                    <td class="px-2 py-4 text-[10px] font-black text-right">{{
                                        Math.round(item.harga_color_a3) }}</td>
                                    <td class="px-2 py-4 text-[10px] font-black text-right">{{
                                        Math.round(item.harga_color_a4) }}</td>
                                    <td class="px-2 py-4 text-[10px] font-black text-right">{{
                                        Math.round(item.harga_bw_a3) }}</td>
                                    <td class="px-2 py-4 text-[10px] font-black text-right">{{
                                        Math.round(item.harga_bw_a4) }}</td>
                                    <td class="px-6 py-4 text-[10px] font-black text-right">{{
                                            item.minimum_charge_click
                                                ? Number(item.minimum_charge_click).toLocaleString('id-ID') + (item.minimum_charge_size ? ' ' + item.minimum_charge_size : '')
                                                : '-'
                                        }}
                                        </td>
                                    <td class="px-6 py-4 text-[10px] font-black text-right">{{
                                        Math.round(item.free_klik_percent) }}%</td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10">
                                            {{ item.is_active ? 'Active' : 'Nonactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors"><svg
                                                    class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg></button>
                                            <button @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors"><svg
                                                    class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="11" class="px-6 py-20 text-center">No Data Mesin Found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing
                        <span class="text-[#1E293B]">{{ mesins.from || 0 }}</span>
                        to
                        <span class="text-[#1E293B]">{{ mesins.to || 0 }}</span>
                        of
                        <span class="text-[#2DD4BF]">{{ mesins.total || 0 }}</span>
                        Units
                    </div>

                    <nav v-if="mesins.links && mesins.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in mesins.links" :key="k">
                            <div v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label" />

                            <Link v-else :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active,
                                }" v-html="link.label" preserve-scroll />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div
                class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-4xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4 text-slate-800">
                    <h3 class="text-xl font-black uppercase italic tracking-tighter">
                        {{ isEdit ? 'Edit' : 'Tambah' }}
                        <span class="text-[#2DD4BF]">Mesin</span>
                    </h3>

                    <button type="button" @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 uppercase text-[10px] font-black">
                        Close
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Cabang Penempatan
                        </label>

                        <multiselect v-model="form.master_cabang_id" :options="cabangs.map(c => c.id)"
                            :custom-label="id => cabangs.find(c => c.id == id)?.nama_cabang" placeholder="Pilih Cabang">
                        </multiselect>

                        <div v-if="form.errors.master_cabang_id" class="text-[10px] font-bold text-rose-500">
                            {{ form.errors.master_cabang_id }}
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Vendor
                        </label>

                        <select v-model="form.master_vendor_id"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                            <option value="">Pilih Vendor</option>
                            <option v-for="v in vendors" :key="v.id" :value="v.id">
                                {{ v.nama_vendor }}
                            </option>
                        </select>

                        <div v-if="form.errors.master_vendor_id" class="text-[10px] font-bold text-rose-500">
                            {{ form.errors.master_vendor_id }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Nama Mesin
                            </label>

                            <input v-model="form.nama_mesin" type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />

                            <div v-if="form.errors.nama_mesin" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.nama_mesin }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Serial Number
                            </label>

                            <input v-model="form.serial_number" type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-[#2DD4BF]/20" />

                            <div v-if="form.errors.serial_number" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.serial_number }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Merk
                            </label>

                            <input v-model="form.merk" type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Tipe
                            </label>

                            <input v-model="form.tipe" type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-3xl p-5 space-y-4">
                        <h4 class="text-[11px] font-black text-[#1E293B] uppercase tracking-widest">
                            Harga Dasar Per Klik
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Color A3</label>
                                <input v-model="form.harga_color_a3" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Color A4</label>
                                <input v-model="form.harga_color_a4" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">BW A3</label>
                                <input v-model="form.harga_bw_a3" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">BW A4</label>
                                <input v-model="form.harga_bw_a4" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-3xl p-5 space-y-4">
                        <h4 class="text-[11px] font-black text-[#1E293B] uppercase tracking-widest">
                            Minimum Charge
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Ukuran</label>
                                <select v-model="form.minimum_charge_size"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold">
                                    <option value="">Tanpa Ukuran</option>
                                    <option value="A4">A4</option>
                                    <option value="A3">A3</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Minimum Charge</label>
                                <input v-model="form.minimum_charge_click" type="number" min="0"
                                    placeholder="Contoh: 30000"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Minimum Nominal</label>
                                <input v-model="form.minimum_charge_nominal" type="number" min="0"
                                    placeholder="Contoh: 1350000"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Free Klik %</label>
                                <input v-model="form.free_klik_percent" type="number" min="0" step="0.0001"
                                    placeholder="Contoh: 1"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-3xl p-5 space-y-4">
                        <h4 class="text-[11px] font-black text-[#1E293B] uppercase tracking-widest">
                            Harga Setelah Melebihi Minimum
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Over Color A3</label>
                                <input v-model="form.over_click_color_a3" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Over Color A4</label>
                                <input v-model="form.over_click_color_a4" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Over BW A3</label>
                                <input v-model="form.over_click_bw_a3" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>

                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase">Over BW A4</label>
                                <input v-model="form.over_click_bw_a4" type="number" min="0"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Keterangan
                        </label>

                        <textarea v-model="form.keterangan" rows="3"
                            class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                            placeholder="Opsional" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Maintenance Part
                            </label>

                            <button type="button" @click="addPart"
                                class="px-3 py-2 bg-slate-900 text-white rounded-xl text-[9px] font-black uppercase hover:bg-[#2DD4BF] transition-all">
                                + Tambah Part
                            </button>
                        </div>

                        <div v-if="form.maintenance_parts.length === 0"
                            class="p-4 bg-slate-50 rounded-2xl text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                            Belum ada maintenance part.
                        </div>

                        <div v-for="(part, index) in form.maintenance_parts" :key="index"
                            class="grid grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-2xl">
                            <div class="col-span-12 md:col-span-5">
                                <input v-model="part.nama_part" type="text" placeholder="Nama Part"
                                    class="w-full px-4 py-2 bg-white border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                            </div>

                            <div class="col-span-3 md:col-span-2 text-right">
                                <button type="button" @click="removePart(index)"
                                    class="w-full px-3 py-2 bg-rose-500 text-white rounded-xl text-[9px] font-black uppercase">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">
                            Active Status
                        </span>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div
                                class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]">
                            </div>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#4f46e5] transition-all disabled:opacity-60">
                        {{ form.processing ? 'Saving...' : 'Simpan Data Mesin' }}
                    </button>
                </form>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">
                    Confirm <span class="text-rose-500">Delete</span>
                </h3>

                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">
                    Data mesin ini akan dihapus permanen.
                </p>

                <div class="flex gap-3">
                    <button @click="closeDeleteModal"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest">
                        Batal
                    </button>

                    <button @click="executeDelete" :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-60">
                        {{ isDeleting ? 'Menghapus...' : 'Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>