<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    dayaListriks: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    daya: '',
    harga_per_kwh: 0,
    ppn_persen: 11,
    keterangan: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('master-daya-listrik.index'),
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
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();

    form.daya = item.daya || '';
    form.harga_per_kwh = item.harga_per_kwh || 0;
    form.keterangan = item.keterangan || '';
    form.is_active = Boolean(item.is_active);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-daya-listrik.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-daya-listrik.store'), {
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

    router.delete(route('master-daya-listrik.destroy', idToDelete.value), {
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

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};
</script>

<template>
    <Head title="Master Daya Listrik" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Daya Listrik</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="relative flex-1 max-w-xl">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari daya / harga / keterangan..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all"
                    />
                </div>

                <button
                    @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5 flex items-center"
                >
                    <span class="mr-2 text-sm">+</span> Tambah Daya
                </button>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Daya
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Harga per KWh
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Keterangan
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
                            <template v-if="dayaListriks.data.length > 0">
                                <tr
                                    v-for="item in dayaListriks.data"
                                    :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg tracking-widest">
                                            {{ item.daya }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[11px] font-black text-[#1E293B]">
                                            {{ formatRupiah(item.harga_per_kwh) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase">
                                            {{ item.keterangan || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10"
                                        >
                                            {{ item.is_active ? 'Active' : 'Nonactive' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="5" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                        No <span class="text-[#2DD4BF]">Daya Data</span> Found
                                    </h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing
                        <span class="text-[#1E293B]">{{ dayaListriks.from || 0 }}</span>
                        to
                        <span class="text-[#1E293B]">{{ dayaListriks.to || 0 }}</span>
                        of
                        <span class="text-[#2DD4BF]">{{ dayaListriks.total || 0 }}</span>
                        Data
                    </div>

                    <nav v-if="dayaListriks.links && dayaListriks.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in dayaListriks.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label"
                            ></div>

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
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4 text-slate-800">
                    <h3 class="text-xl font-black uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }}
                        <span class="text-[#2DD4BF]">Daya</span>
                    </h3>

                    <button
                        @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 uppercase text-[10px] font-black"
                    >
                        Close
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Daya VA
                        </label>

                        <input
                            v-model="form.daya"
                            type="text"
                            placeholder="Contoh: 1300 VA"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                        />

                        <div v-if="form.errors.daya" class="text-[10px] font-bold text-rose-500">
                            {{ form.errors.daya }}
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Harga Per KWh
                        </label>

                        <input
                            v-model="form.harga_per_kwh"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Contoh: 1444.70"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                        />

                        <div v-if="form.errors.harga_per_kwh" class="text-[10px] font-bold text-rose-500">
                            {{ form.errors.harga_per_kwh }}
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            PPN (%)
                        </label>

                        <input
                            v-model="form.ppn_persen"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Keterangan
                        </label>

                        <textarea
                            v-model="form.keterangan"
                            rows="2"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">
                            Active Status
                        </span>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]">
                            </div>
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#1E293B] transition-all"
                    >
                        {{ form.processing ? 'Processing...' : 'Execute Daya Data' }}
                    </button>
                </form>
            </div>
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">
                    Confirm <span class="text-rose-500">Delete</span>
                </h3>

                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">
                    Data daya listrik ini akan dihapus permanen dari sistem.
                </p>

                <div class="flex gap-3">
                    <button
                        @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest"
                    >
                        Batal
                    </button>

                    <button
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest"
                    >
                        {{ isDeleting ? '...' : 'Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>