<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps({
    skpds: Object,
    cabangs: Array,
    financeUsers: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_cabang_id: '',
    jenis: '',
    nomor_skpd: '',
    nominal_pajak: 0,
    keterangan: '',
    tanggal_jatuh_tempo: '',
    reminder_hari: [7, 14, 30],
    user_ids: [],
    foto: [],
    existing_foto_urls: [],
    is_active: true,
});

const userSearch = ref('');

const filteredFinanceUsers = computed(() => {
    const keyword = userSearch.value.toLowerCase();

    if (!keyword) return props.financeUsers;

    return props.financeUsers.filter((user) => {
        return (
            user.name?.toLowerCase().includes(keyword) ||
            user.phone?.toLowerCase().includes(keyword)
        );
    });
});

const generateNomorSkpd = () => {
    const randomNumber = Math.floor(100000 + Math.random() * 900000);
    return `SKPD-${randomNumber}`;
};

watch(search, (value) => {
    router.get(
        route('master-skpd.index'),
        { search: value },
        { preserveState: true, replace: true }
    );
});

const showImageModal = ref(false);
const selectedImageUrl = ref('');

const openImagePreview = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
};

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();

    form.master_cabang_id = '';
    form.jenis = '';
    form.keterangan = '';
    form.tanggal_jatuh_tempo = '';
    form.reminder_hari = [7, 14, 30];
    form.user_ids = [];
    form.foto = [];
    form.existing_foto_urls = [];
    form.nomor_skpd = generateNomorSkpd();
    form.nominal_pajak = 0;
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();

    form.master_cabang_id = item.master_cabang_id || '';
    form.nomor_skpd = item.nomor_skpd || '';
    form.nominal_pajak = item.nominal_pajak || 0;
    form.jenis = item.jenis || '';
    form.keterangan = item.keterangan || '';
    form.tanggal_jatuh_tempo = toDateInput(item.tanggal_jatuh_tempo);
    form.foto = [];
    form.existing_foto_urls = item.foto_urls || [];

    form.reminder_hari = Array.isArray(item.reminder_hari)
        ? item.reminder_hari
        : item.reminder_hari
            ? [Number(item.reminder_hari)]
            : [];

    form.user_ids = Array.isArray(item.user_ids)
        ? item.user_ids
        : [];

    form.is_active = Boolean(item.is_active);
    showModal.value = true;
};

const handleFotoChange = (event) => {
    form.foto = Array.from(event.target.files || []);
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const onKendaraanChange = () => {
    const kendaraan = props.kendaraans.find(
        (item) => String(item.id) === String(form.master_kendaraan_id)
    );
    if (kendaraan) {
        form.nomor_polisi = kendaraan.nomor_polisi || '';
        form.nama_pemilik = kendaraan.nama_pemilik || '';
    }
};

const submit = () => {
    form.transform((data) => {
        const payload = {
            ...data,
            _method: isEdit.value ? 'put' : undefined,
        };

        return payload;
    }).post(
        isEdit.value
            ? route('master-skpd.update', selectedId.value)
            : route('master-skpd.store'),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
        }
    );
};

const toDateInput = (value) => {
    if (!value) return '';
    return String(value).slice(0, 10);
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
    router.delete(route('master-skpd.destroy', idToDelete.value), {
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
</script>

<template>

    <Head title="Master SKPD Pajak Reklame" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">SKPD Pajak Reklame</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="mx-auto w-full">
                <div class="bg-white sm:rounded-2xl overflow-hidden border border-slate-200">

                    <!-- Search & Action -->
                    <div class="p-6 border-b border-slate-50 bg-slate-50/30">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="relative">
                                <input v-model="search" type="text"
                                    placeholder="Cari nomor SKPD / pemilik / no polisi..."
                                    class="w-full rounded-xl border-slate-200 bg-white pl-4 pr-10 text-sm shadow-sm focus:border-[#2DD4BF] focus:ring-[#2DD4BF] md:w-96" />
                            </div>

                            <button type="button" @click="openCreate"
                                class="inline-flex items-center justify-center rounded-xl bg-[#1E293B] px-5 py-2.5 text-sm font-bold uppercase tracking-widest text-white transition-all hover:bg-slate-700 active:scale-95 shadow-lg shadow-slate-200">
                                <span class="mr-2 text-lg">+</span> Tambah SKPD Pajak Reklame
                            </button>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center w-16">
                                        No</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                        Nomor
                                        SKPD</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-right">
                                        Nominal Pajak</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                        Jatuh
                                        Tempo</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                        Status</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                        Foto</th>
                                    <th
                                        class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center">
                                        Aksi</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="(item, index) in skpds.data" :key="item.id"
                                    class="group transition-colors hover:bg-slate-50/50">
                                    <td class="px-6 py-4 text-center text-[11px] font-bold text-slate-400">
                                        {{ skpds.from + index }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-700">
                                        {{ item.nomor_skpd || '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm font-black text-[#1E293B] text-right">
                                        {{ formatRupiah(item.nominal_pajak) }}
                                        <div class="text-[11px] font-bold text-slate-600">
                                        {{ item.keterangan || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-[11px] font-bold text-slate-600">{{ item.tanggal_jatuh_tempo ||
                                            '-' }}
                                        </div>
                                        <div
                                            class="text-[9px] font-black text-[#2DD4BF] uppercase tracking-widest mt-0.5">
                                            Remind H-{{ item.reminder_hari }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="item.is_active"
                                            class="inline-flex rounded-lg bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-600 border border-emerald-100">
                                            Aktif
                                        </span>
                                        <span v-else
                                            class="inline-flex rounded-lg bg-rose-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-rose-600 border border-rose-100">
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="item.foto_urls?.length" class="flex gap-1">
                                            <button
                                                v-for="url in item.foto_urls.slice(0, 3)"
                                                :key="url"
                                                @click="openImagePreview(url)"
                                                type="button"
                                                class="block w-14 h-14 rounded-xl overflow-hidden border border-slate-200 bg-slate-50"
                                            >
                                                <img :src="url" class="w-full h-full object-cover" />
                                            </button>
                                        </div>

                                        <div v-else
                                            class="w-14 h-14 rounded-xl border border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-[9px] font-black text-slate-300 uppercase">
                                            No Foto
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
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
                                            <span class="text-slate-200">|</span>
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

                                <!-- No Data Found -->
                                <tr v-if="skpds.data.length === 0">
                                    <td colspan="8" class="px-6 py-24 text-center">
                                        <div class="flex flex-col items-center">
                                            <div
                                                class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4 text-2xl">
                                                📂</div>
                                            <div
                                                class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-300">
                                                Data SKPD Belum Tersedia
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Section (Identik Master Cabang) -->
                    <div class="px-6 py-5 bg-white border-t border-slate-100">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-5">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data
                                    Statistics</span>
                                <div class="text-[11px] font-bold text-[#1E293B] uppercase mt-1">
                                    Showing {{ skpds.from || 0 }} - {{ skpds.to || 0 }}
                                    <span class="text-[#2DD4BF] mx-1">/</span>
                                    Total {{ skpds.total || 0 }} SKPD
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <template v-for="(link, k) in skpds.links" :key="k">
                                    <div v-if="link.url === null"
                                        class="px-4 py-2 text-[10px] font-black text-slate-300 uppercase tracking-widest border border-slate-50 rounded-xl cursor-not-allowed"
                                        v-html="link.label" />
                                    <Link v-else :href="link.url"
                                        class="px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border duration-300"
                                        :class="{
                                            'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105': link.active,
                                            'bg-slate-50 text-slate-500 border-transparent hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                        }" v-html="link.label" preserve-scroll />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal (Identik Master Cabang) -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4">
            <div
                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-300">
                <div class="flex items-center justify-between border-b border-slate-50 px-8 py-6">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-[0.3em] text-[#2DD4BF]">Form Entry</span>
                        <h3 class="text-xl font-bold text-slate-800">{{ isEdit ? 'Update SKPD Pajak Reklame' : 'Create SKPD Pajak Reklame' }}
                        </h3>
                    </div>
                    <button type="button" @click="closeModal"
                        class="h-10 w-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Cabang
                            </label>
                            <multiselect v-model="form.master_cabang_id" :options="cabangs.map(c => c.id)"
                                :custom-label="id => cabangs.find(c => c.id == id)?.nama_cabang"
                                placeholder="Pilih Cabang">
                            </multiselect>

                            <div v-if="form.errors.master_cabang_id" class="text-[10px] font-bold text-rose-500">
                                {{ form.errors.master_cabang_id }}
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Nomor SKPD
                            </label>

                            <input v-model="form.nomor_skpd" type="text" placeholder="Masukkan nomor SKPD..."
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]" />
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Nominal Pajak
                            </label>

                            <input v-model="form.nominal_pajak" type="number" min="0" placeholder="0"
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]" />
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Jenis
                            </label>
                            <input v-model="form.jenis" type="text"
                                placeholder="Contoh: Pajak, Perizinan, Dokumen Cabang"
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]" />
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Jatuh Tempo
                            </label>
                            <input v-model="form.tanggal_jatuh_tempo" type="date"
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]" />
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Upload Foto
                            </label>

                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                @change="handleFotoChange"
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]"
                            />

                            <div
                                v-if="form.foto && form.foto.length"
                                class="mt-2 text-[10px] font-bold text-[#2DD4BF]"
                            >
                                {{ form.foto.length }} foto dipilih
                            </div>
                        </div>

                        <div v-if="isEdit && form.existing_foto_urls.length" class="md:col-span-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Foto Saat Ini
                            </label>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    v-for="url in form.existing_foto_urls"
                                    :key="url"
                                    type="button"
                                    @click="openImagePreview(url)"
                                    class="w-20 h-20 rounded-xl overflow-hidden border border-slate-200 bg-slate-50"
                                >
                                    <img :src="url" class="w-full h-full object-cover" />
                                </button>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">
                                Keterangan
                            </label>
                            <textarea v-model="form.keterangan" rows="3"
                                class="w-full rounded-xl border-slate-100 bg-slate-50 py-3 text-sm font-bold focus:border-[#2DD4BF] focus:ring-[#2DD4BF]"></textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">
                                Reminder
                            </label>

                            <div class="flex flex-wrap gap-3 rounded-2xl bg-slate-50 p-4">
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
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    User Penerima Reminder WA
                                </label>

                                <span class="text-[9px] font-black text-[#2DD4BF] uppercase tracking-widest">
                                    {{ form.user_ids.length }} Dipilih
                                </span>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50 overflow-hidden">
                                <div class="border-b border-slate-100 bg-white p-3">
                                    <input v-model="userSearch" type="text" placeholder="Cari nama / nomor HP user..."
                                        class="w-full rounded-xl border-none bg-slate-50 px-4 py-2 text-xs font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                                </div>

                                <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto p-3 md:grid-cols-2">
                                    <label v-for="user in filteredFinanceUsers" :key="user.id"
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-white p-3 transition-all hover:border-[#2DD4BF]/40"
                                        :class="form.user_ids.includes(user.id) ? 'border-[#2DD4BF] bg-[#2DD4BF]/5' : ''">
                                        <input type="checkbox" :value="user.id" v-model="form.user_ids"
                                            class="mt-1 rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />

                                        <div class="min-w-0">
                                            <div class="truncate text-[11px] font-black uppercase text-slate-700">
                                                {{ user.name }}
                                            </div>
                                            <div class="text-[10px] font-bold text-slate-400">
                                                {{ user.phone || 'No Phone' }}
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl">
                        <input type="checkbox" v-model="form.is_active"
                            class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                        <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">Status
                            Aktif</span>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-50 pt-8">
                        <button type="button" @click="closeModal"
                            class="px-6 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-rose-500 transition-all">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-xl bg-[#1E293B] px-8 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-white shadow-xl shadow-slate-200 hover:bg-slate-700 active:scale-95 transition-all disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
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