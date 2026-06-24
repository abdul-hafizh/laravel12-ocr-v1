<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    cabangs: Object,
    users: Array,
    filters: Object,
    flash: Object,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const showDetailModal = ref(false);
const detailContent = ref('');

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const flashMessage = computed(() => {
    if (props.flash?.success) {
        return { text: props.flash.success, type: 'success' };
    }

    if (props.flash?.error) {
        return { text: props.flash.error, type: 'error' };
    }

    return null;
});

const form = useForm({
    kode_cabang: '',
    nama_cabang: '',
    alamat: '',
    user_ids: [],
    keterangan: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('master-cabang.index'),
        {
            search: value,
            page: 1,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;

    form.reset();
    form.clearErrors();

    form.kode_cabang = '';
    form.nama_cabang = '';
    form.alamat = '';
    form.user_ids = [];
    form.keterangan = '';
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.kode_cabang = item.kode_cabang || '';
    form.nama_cabang = item.nama_cabang || '';
    form.alamat = item.alamat || '';
    form.user_ids = item.users?.map(user => user.id) || [];
    form.keterangan = item.keterangan || '';
    form.is_active = Boolean(item.is_active);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-cabang.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-cabang.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const openAddressDetail = (alamat) => {
    detailContent.value = alamat || 'Tidak ada alamat';
    showDetailModal.value = true;
};

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    isDeleting.value = true;

    router.delete(route('master-cabang.destroy', idToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            idToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Master Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Cabang</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="relative flex-1 max-w-sm">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari cabang..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400"
                    />
                </div>

                <button
                    @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all flex items-center shadow-lg shadow-black/5"
                >
                    <span class="mr-2 text-sm">+</span> Tambah Cabang
                </button>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Kode
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Nama Cabang
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Alamat
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    PIC
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    No. WA
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="cabangs.data.length > 0">
                                <tr
                                    v-for="item in cabangs.data"
                                    :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg uppercase tracking-tighter">
                                            {{ item.kode_cabang }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-sm font-bold text-[#1E293B] uppercase tracking-tight">
                                        {{ item.nama_cabang }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <div
                                            @click="openAddressDetail(item.alamat)"
                                            class="group/address cursor-pointer"
                                        >
                                            <p class="text-[11px] text-slate-400 font-medium line-clamp-1 max-w-[100px] transition-all duration-300">
                                                {{ item.alamat || '-' }}
                                            </p>
                                            <span class="block text-[8px] font-black uppercase tracking-tighter text-[#2DD4BF] opacity-0 group-hover/address:opacity-100 transition-opacity">
                                                Click to see full detail
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        <div v-if="item.users?.length">
                                            <div v-for="user in item.users" :key="user.id">
                                                {{ user.name }}
                                            </div>
                                        </div>
                                        <span v-else>-</span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600">
                                        <div v-if="item.users?.length">
                                            <div v-for="user in item.users" :key="user.id">
                                                {{ user.phone || '-' }}
                                            </div>
                                        </div>
                                        <span v-else>-</span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10"
                                        >
                                            {{ item.is_active ? 'Active' : 'Offline' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                        stroke-width="2.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </button>

                                            <button
                                                @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                        stroke-width="2.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2-2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </div>

                                        <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                            No Data <span class="text-[#2DD4BF]">Cabang</span> Found
                                        </h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ cabangs.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ cabangs.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ cabangs.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="cabangs.links && cabangs.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in cabangs.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label"
                            />

                            <Link
                                v-else
                                :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                }"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </template>
                    </nav>

                    <div v-else class="text-[10px] font-black text-slate-300 uppercase italic">
                        No additional pages
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <div class="mx-auto w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mb-6 border border-rose-100">
                    <svg :class="{ 'animate-spin': isDeleting }" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            v-if="!isDeleting"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path
                            v-else
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            fill="currentColor"
                            opacity="0.75"
                        />
                    </svg>
                </div>

                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">
                    Confirm <span class="text-rose-500">Delete</span>
                </h3>

                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">
                    Data yang dihapus tidak dapat dikembalikan.
                </p>

                <div class="flex gap-3">
                    <button
                        @click="showDeleteModal = false"
                        :disabled="isDeleting"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200"
                    >
                        Batal
                    </button>

                    <button
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-500/30 hover:bg-rose-600"
                    >
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showDetailModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-md p-8 shadow-2xl relative">
                <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-[#1E293B] uppercase italic tracking-tighter">
                        Full <span class="text-[#2DD4BF]">Address</span>
                    </h3>

                    <button
                        @click="showDetailModal = false"
                        class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest"
                    >
                        Close
                    </button>
                </div>

                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                    <p class="text-sm font-bold text-slate-600 leading-relaxed uppercase">
                        {{ detailContent }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div
                class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-xl shadow-2xl relative
                    max-h-[90vh] overflow-hidden flex flex-col"
            >
                <div class="flex justify-between items-center p-8 pb-4 border-b border-slate-100">
                    <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }}
                        <span class="text-[#2DD4BF]">Branch</span>
                    </h3>

                    <button
                        @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest"
                    >
                        Close
                    </button>
                </div>
                <div class="overflow-y-auto px-8 pb-8">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    Branch Code
                                </label>

                                <input
                                    v-model="form.kode_cabang"
                                    type="text"
                                    placeholder="EX: CBR001"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                />

                                <div v-if="form.errors.kode_cabang" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                    {{ form.errors.kode_cabang }}
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    Branch Name
                                </label>

                                <input
                                    v-model="form.nama_cabang"
                                    type="text"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                />

                                <div v-if="form.errors.nama_cabang" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                    {{ form.errors.nama_cabang }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                PIC Cabang
                            </label>

                            <div class="max-h-48 overflow-y-auto border rounded-xl p-3">
                                <label
                                    v-for="user in users"
                                    :key="user.id"
                                    class="flex items-center gap-3 py-2 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="user.id"
                                        v-model="form.user_ids"
                                    />

                                    <span class="text-sm">
                                        {{ user.name }} - {{ user.phone }}
                                    </span>
                                </label>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-3">
                                <span
                                    v-for="user in users.filter(u => form.user_ids.includes(u.id))"
                                    :key="user.id"
                                    class="px-3 py-1 bg-[#2DD4BF]/10 text-[#2DD4BF] rounded-full text-xs font-bold"
                                >
                                    {{ user.name }}
                                </span>
                            </div>

                            <div v-if="form.errors.user_ids" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.user_ids }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Address
                            </label>

                            <textarea
                                v-model="form.alamat"
                                rows="2"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 resize-none"
                            />

                            <div v-if="form.errors.alamat" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.alamat }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Keterangan
                            </label>

                            <textarea
                                v-model="form.keterangan"
                                rows="2"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 resize-none"
                            />

                            <div v-if="form.errors.keterangan" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.keterangan }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">
                                Active Branch Access
                            </span>

                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                                <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]" />
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Execute Data' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>