<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps({
    kendaraans: Object,
    cabangs: Array,
    financeUsers: Array,
    filters: Object,
    flash: Object,
});

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

watch([search, masterCabangId], ([searchValue, cabangValue]) => {
    router.get(
        route('master-kendaraan.index'),
        {
            search: searchValue,
            master_cabang_id: cabangValue,
            page: 1
        },
        { preserveState: true, replace: true }
    );
});

const financeSearch = ref('');

const filteredFinanceUsers = computed(() => {
    const keyword = financeSearch.value.toLowerCase();

    if (!keyword) return props.financeUsers;

    return props.financeUsers.filter((user) => {
        return (
            user.name?.toLowerCase().includes(keyword) ||
            user.phone?.toLowerCase().includes(keyword)
        );
    });
});

const selectedFinanceCount = computed(() => {
    return form.finance_user_ids.length;
});

const form = useForm({
    master_cabang_id: '',
    jenis_kendaraan: '',
    nomor_polisi: '',
    merk: '',
    tipe: '',
    tahun_pembelian: '',
    nama_pemilik: '',
    tanggal_jatuh_tempo: '',
    tanggal_ganti_kaleng: '',
    finance_user_ids: [],
    reminder_hari: [7, 14],
    reminder_ganti_kaleng_hari: [90, 60, 30],
    keterangan: '',
    is_active: true,
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;

    form.finance_user_ids = [];
    form.reminder_hari = [7, 14];
    form.reminder_ganti_kaleng_hari = [90, 60, 30];
    form.tanggal_ganti_kaleng = '';
    form.keterangan = '';
    form.is_active = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();
    form.master_cabang_id = item.master_cabang_id || '';
    form.jenis_kendaraan = item.jenis_kendaraan || '';
    form.nomor_polisi = item.nomor_polisi || '';
    form.merk = item.merk || '';
    form.tipe = item.tipe || '';
    form.tanggal_jatuh_tempo = item.tanggal_jatuh_tempo || '';
    form.tanggal_ganti_kaleng = item.tanggal_ganti_kaleng || '';
    form.reminder_ganti_kaleng_hari = Array.isArray(item.reminder_ganti_kaleng_hari)
        ? item.reminder_ganti_kaleng_hari
        : item.reminder_ganti_kaleng_hari
            ? [Number(item.reminder_ganti_kaleng_hari)]
            : [90, 60, 30];
    form.tahun_pembelian = item.tahun_pembelian || '';
    form.nama_pemilik = item.nama_pemilik || '';
    form.keterangan = item.keterangan || '';
    form.reminder_hari = Array.isArray(item.reminder_hari)
        ? item.reminder_hari
        : item.reminder_hari
            ? [Number(item.reminder_hari)]
            : [];

    form.finance_user_ids = Array.isArray(item.finance_user_ids)
        ? item.finance_user_ids
        : item.finance_user_id
            ? [item.finance_user_id]
            : [];
    form.is_active = Boolean(item.is_active);
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-kendaraan.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-kendaraan.store'), {
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
    router.delete(route('master-kendaraan.destroy', idToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            idToDelete.value = null;
        },
        onFinish: () => isDeleting.value = false,
    });
};
</script>

<template>

    <Head title="Master Kendaraan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Kendaraan</span>
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Header Action Bar -->
            <div
                class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="flex flex-1 gap-4 max-w-2xl">
                    <!-- Search -->
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3"
                                    stroke-linecap="round" />
                            </svg>
                        </span>
                        <input v-model="search" type="text" placeholder="Cari No. Polisi / Pemilik..."
                            class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all" />
                    </div>
                    <!-- Filter Cabang -->
                    <select v-model="masterCabangId"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20">
                        <option value="">Semua Cabang</option>
                        <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                    </select>
                </div>

                <button @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5 flex items-center">
                    <span class="mr-2 text-sm">+</span> Tambah Kendaraan
                </button>
            </div>

            <!-- Table Data Container -->
            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    No. Polisi
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Merk &
                                    Tipe</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Jenis</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Cabang
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Pemilik
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Jatuh Tempo</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Ganti Kaleng</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Finance</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <template v-if="kendaraans.data.length > 0">
                                <tr v-for="item in kendaraans.data" :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors">
                                    <!-- Kolom No Polisi -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg uppercase tracking-tighter">
                                            {{ item.nomor_polisi }}
                                        </span>
                                    </td>

                                    <!-- Kolom Merk & Tipe -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-[#1E293B] uppercase tracking-tight">
                                            {{ item.merk }}
                                        </div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">
                                            {{ item.tipe }}
                                        </div>
                                    </td>

                                    <!-- Kolom Jenis -->
                                    <td class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase">
                                        {{ item.jenis_kendaraan }}
                                    </td>

                                    <!-- Kolom Cabang -->
                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ item.cabang?.nama_cabang || '-' }}
                                    </td>

                                    <!-- Kolom Pemilik -->
                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase italic">
                                        {{ item.nama_pemilik || '-' }}
                                    </td>

                                    <!-- Kolom Jatuh Tempo -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-[11px] font-black text-[#1E293B]">{{
                                            formatDate(item.tanggal_jatuh_tempo) }}</div>
                                        <div class="text-[8px] font-black text-rose-500 uppercase tracking-tighter">
                                            H-{{ Array.isArray(item.reminder_hari) ? item.reminder_hari.join(', H-') :
                                            item.reminder_hari }} Alert
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="text-[11px] font-black text-[#1E293B]">
                                            {{ formatDate(item.tanggal_ganti_kaleng) }}
                                        </div>
                                        <div class="text-[8px] font-black text-orange-500 uppercase tracking-tighter">
                                            H-{{
                                                Array.isArray(item.reminder_ganti_kaleng_hari)
                                                    ? item.reminder_ganti_kaleng_hari.join(', H-')
                                                    : item.reminder_ganti_kaleng_hari
                                            }} Alert
                                        </div>
                                    </td>

                                    <!-- Kolom Status -->
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10">
                                            {{ item.is_active ? 'Active' : 'Offline' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ item.finance_user?.name || '-' }}
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
                            <!-- No Data Found Section -->
                            <tr v-else>
                                <td colspan="10" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2-2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <h4
                                            class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                            No Data <span class="text-[#2DD4BF]">Kendaraan</span> Found
                                        </h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Section -->
                <div
                    class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ kendaraans.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ kendaraans.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ kendaraans.total || 0 }}</span> Units
                    </div>
                    <nav v-if="kendaraans.links && kendaraans.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in kendaraans.links" :key="k">
                            <div v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label"></div>
                            <Link v-else :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                }" v-html="link.label" preserve-scroll />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Modal Form -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div
                class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-2xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4 text-slate-800">
                    <h3 class="text-xl font-black uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }} <span class="text-[#2DD4BF]">Vehicle</span>
                    </h3>
                    <button @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 uppercase text-[10px] font-black">Close</button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cabang
                                Penempatan</label>
                            <multiselect v-model="form.master_cabang_id" :options="cabangs.map(c => c.id)"
                                :custom-label="id => cabangs.find(c => c.id == id)?.nama_cabang"
                                placeholder="Pilih Cabang">
                            </multiselect>

                            <div v-if="form.errors.master_cabang_id" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.master_cabang_id }}
                            </div>
                        </div>
                        <div class="space-y-2 col-span-2">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    User Finance Penerima Reminder
                                </label>

                                <span class="text-[9px] font-black text-[#2DD4BF] uppercase tracking-widest">
                                    {{ selectedFinanceCount }} Dipilih
                                </span>
                            </div>

                            <div class="bg-slate-50 rounded-2xl border border-slate-100 overflow-hidden">
                                <div class="p-3 border-b border-slate-100 bg-white">
                                    <input v-model="financeSearch" type="text"
                                        placeholder="Cari nama / nomor HP finance..."
                                        class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 placeholder:text-slate-400" />
                                </div>

                                <div class="max-h-64 overflow-y-auto p-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <label v-for="u in filteredFinanceUsers" :key="u.id"
                                        class="flex items-start gap-3 p-3 rounded-xl bg-white border border-slate-100 cursor-pointer hover:border-[#2DD4BF]/40 transition-all"
                                        :class="form.finance_user_ids.includes(u.id) ? 'border-[#2DD4BF] bg-[#2DD4BF]/5' : ''">
                                        <input type="checkbox" :value="u.id" v-model="form.finance_user_ids"
                                            class="mt-1 rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />

                                        <div class="min-w-0">
                                            <div class="text-[11px] font-black text-slate-700 uppercase truncate">
                                                {{ u.name }}
                                            </div>
                                            <div class="text-[10px] font-bold text-slate-400">
                                                {{ u.phone || 'No Phone' }}
                                            </div>
                                        </div>
                                    </label>

                                    <div v-if="filteredFinanceUsers.length === 0"
                                        class="col-span-2 text-center py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        User finance tidak ditemukan.
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.errors.finance_user_ids" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.finance_user_ids }}
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Jenis
                                Kendaraan</label>
                            <select v-model="form.jenis_kendaraan"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                                <option value="motor">Motor</option>
                                <option value="mobil">Mobil</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-1.5 col-span-1">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">No.
                                Polisi</label>
                            <input v-model="form.nomor_polisi" type="text" placeholder="B 1234 ABC"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-1.5 col-span-1">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Merk</label>
                            <input v-model="form.merk" type="text" placeholder="Toyota"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-1.5 col-span-1">
                            <label
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Tipe</label>
                            <input v-model="form.tipe" type="text" placeholder="Avanza"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pemilik
                                (STNK)</label>
                            <input v-model="form.nama_pemilik" type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Tahun
                                Beli</label>
                            <input v-model="form.tahun_pembelian" type="number"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Jatuh
                                Tempo
                                Pajak</label>
                            <input v-model="form.tanggal_jatuh_tempo" type="date"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Reminder
                            </label>

                            <div class="flex gap-3 bg-slate-50 rounded-2xl p-4">
                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="7" v-model="form.reminder_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-7 Hari
                                </label>

                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="14" v-model="form.reminder_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-14 Hari
                                </label>
                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="30" v-model="form.reminder_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-30 Hari
                                </label>
                            </div>

                            <div v-if="form.errors.reminder_hari" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.reminder_hari }}
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Keterangan
                            </label>

                            <textarea v-model="form.keterangan" rows="3" placeholder="Masukkan keterangan kendaraan..."
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 resize-none"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Tanggal Ganti Kaleng
                            </label>

                            <input v-model="form.tanggal_ganti_kaleng" type="date"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />

                            <div v-if="form.errors.tanggal_ganti_kaleng" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.tanggal_ganti_kaleng }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Reminder Ganti Kaleng
                            </label>

                            <div class="flex flex-wrap gap-3 bg-slate-50 rounded-2xl p-4">
                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="30" v-model="form.reminder_ganti_kaleng_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-30 Hari
                                </label>

                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="60" v-model="form.reminder_ganti_kaleng_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-60 Hari
                                </label>

                                <label
                                    class="flex items-center gap-2 text-[11px] font-black text-slate-600 uppercase cursor-pointer">
                                    <input type="checkbox" :value="90" v-model="form.reminder_ganti_kaleng_hari"
                                        class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                                    H-90 Hari
                                </label>
                            </div>

                            <div v-if="form.errors.reminder_ganti_kaleng_hari"
                                class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.reminder_ganti_kaleng_hari }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Active Status</span>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div
                                class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]">
                            </div>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all">
                        {{ form.processing ? 'Saving...' : 'Execute Vehicle Data' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Modal Delete Confirmation -->
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
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">Unit ini akan dihapus
                    permanen
                    dari sistem.</p>
                <div class="flex gap-3">
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest">Batal</button>
                    <button @click="executeDelete" :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-500/30">Ya,
                        Hapus</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>