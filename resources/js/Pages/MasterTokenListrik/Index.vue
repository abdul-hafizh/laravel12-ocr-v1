<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    tokenListriks: Object,
    cabangs: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_cabang_id: '',
    nomor_meter: '',
    nama_pelanggan: '',
    daya: '',
    nominal_default: 0,
    keterangan: '',
    is_active: true,
});

watch([search, masterCabangId], ([searchValue, cabangValue]) => {
    router.get(
        route('master-token-listrik.index'),
        {
            search: searchValue,
            master_cabang_id: cabangValue,
            page: 1
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
    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();
    form.master_cabang_id = item.master_cabang_id || '';
    form.nomor_meter = item.nomor_meter || '';
    form.nama_pelanggan = item.nama_pelanggan || '';
    form.daya = item.daya || '';
    form.nominal_default = item.nominal_default || 0;
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
        form.put(route('master-token-listrik.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-token-listrik.store'), {
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
    router.delete(route('master-token-listrik.destroy', idToDelete.value), {
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
    <Head title="Master Token Listrik" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Token Listrik</span>
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Action Bar -->
            <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="flex flex-1 gap-4 max-w-2xl">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3" stroke-linecap="round" />
                            </svg>
                        </span>
                        <input v-model="search" type="text" placeholder="Cari nomor meter / pelanggan..."
                            class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all" />
                    </div>
                    <select v-model="masterCabangId"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20">
                        <option value="">Semua Cabang</option>
                        <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                    </select>
                </div>

                <button @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5 flex items-center">
                    <span class="mr-2 text-sm">+</span> Tambah Token
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nomor Meter</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Pelanggan & Daya</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cabang</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Harga per KWh</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <template v-if="tokenListriks.data.length > 0">
                                <tr v-for="item in tokenListriks.data" :key="item.id" class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg tracking-widest">
                                            {{ item.nomor_meter }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-[#1E293B] uppercase tracking-tight">{{ item.nama_pelanggan || '-' }}</div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{{ item.daya || 'Daya Tidak Set' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase italic">
                                        {{ item.cabang?.nama_cabang || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[11px] font-black text-[#1E293B]">{{ formatRupiah(item.nominal_default) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10">
                                            {{ item.is_active ? 'Active' : 'Nonactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openEdit(item)" class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                            <button @click="confirmDelete(item.id)" class="p-2 text-slate-300 hover:text-rose-500 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">No <span class="text-[#2DD4BF]">Token Data</span> Found</h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ tokenListriks.from || 0 }}</span> to <span class="text-[#1E293B]">{{ tokenListriks.to || 0 }}</span> of <span class="text-[#2DD4BF]">{{ tokenListriks.total || 0 }}</span> Units
                    </div>
                    <nav v-if="tokenListriks.links && tokenListriks.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in tokenListriks.links" :key="k">
                            <div v-if="link.url === null" class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter" v-html="link.label"></div>
                            <Link v-else :href="link.url" class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{ 'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active, 'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active }" v-html="link.label" preserve-scroll />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Modal Form -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-2xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4 text-slate-800">
                    <h3 class="text-xl font-black uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }} <span class="text-[#2DD4BF]">Token</span>
                    </h3>
                    <button @click="closeModal" class="text-slate-300 hover:text-rose-500 uppercase text-[10px] font-black">Close</button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cabang Penempatan</label>
                        <select v-model="form.master_cabang_id" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                            <option value="">Pilih Cabang</option>
                            <option v-for="c in cabangs" :key="c.id" :value="c.id">{{ c.nama_cabang }}</option>
                        </select>
                        <div class="text-[9px] font-bold text-rose-500 uppercase mt-1 ml-1" v-if="form.errors.master_cabang_id">{{ form.errors.master_cabang_id }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nomor Meter</label>
                            <input v-model="form.nomor_meter" type="text" placeholder="Contoh: 1403..." class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                            <div class="text-[9px] font-bold text-rose-500 uppercase mt-1 ml-1" v-if="form.errors.nomor_meter">{{ form.errors.nomor_meter }}</div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Daya (VA)</label>
                            <input v-model="form.daya" type="text" placeholder="Contoh: 1300 VA" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nama Pelanggan</label>
                            <input v-model="form.nama_pelanggan" type="text" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Harga Per KWh</label>
                            <input v-model="form.nominal_default" type="number" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Keterangan</label>
                        <textarea v-model="form.keterangan" rows="2" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"></textarea>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Active Status</span>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]"></div>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#1E293B] transition-all">
                        {{ form.processing ? 'Processing...' : 'Execute Token Data' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">Confirm <span class="text-rose-500">Delete</span></h3>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">Data token ini akan dihapus permanen dari sistem.</p>
                <div class="flex gap-3">
                    <button @click="showDeleteModal = false" class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest">Batal</button>
                    <button @click="executeDelete" :disabled="isDeleting" class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest">
                        {{ isDeleting ? '...' : 'Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>