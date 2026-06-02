<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    measurements: Object,
    kets: Array,
    filters: Object,
    flash: Object,
});

const search = ref(props.filters?.search || '');
const periode = ref(props.filters?.periode || '');
const bulan = ref(props.filters?.bulan || '');
const tahun = ref(props.filters?.tahun || '');
const ket = ref(props.filters?.ket || '');

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const flashMessage = computed(() => {
    if (props.flash?.success) return { text: props.flash.success, type: 'success' };
    if (props.flash?.error) return { text: props.flash.error, type: 'error' };
    return null;
});

const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 8 }, (_, i) => currentYear - i);

const applyFilter = () => {
    router.get(
        route('employee-measurements.index'),
        {
            search: search.value,
            periode: periode.value,
            ket: ket.value,
            page: 1,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const resetFilter = () => {
    search.value = '';
    periode.value = '';
    ket.value = '';

    router.get(
        route('employee-measurements.index'),
        {},
        {
            preserveState: true,
            replace: true,
        }
    );
};

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    isDeleting.value = true;

    router.delete(route('employee-measurements.destroy', idToDelete.value), {
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

const formatDate = (date) => {
    if (!date) return '-';

    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const bmiClass = (bmi) => {
    const value = Number(bmi);

    if (value < 18.5) return 'bg-blue-50 text-blue-600';
    if (value < 25) return 'bg-emerald-50 text-emerald-600';
    if (value < 30) return 'bg-amber-50 text-amber-600';
    return 'bg-rose-50 text-rose-600';
};

const ketClass = (value) => {
    const text = String(value || '').toLowerCase();

    if (text.includes('normal') || text.includes('ideal')) return 'bg-emerald-50 text-emerald-600';
    if (text.includes('kurus')) return 'bg-blue-50 text-blue-600';
    if (text.includes('gemuk') || text.includes('over')) return 'bg-amber-50 text-amber-600';
    if (text.includes('obes')) return 'bg-rose-50 text-rose-600';

    return 'bg-slate-50 text-slate-600';
};
</script>

<template>
    <Head title="BMI Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                BMI <span class="text-[#2DD4BF]">Karyawan</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div
                v-if="flashMessage"
                :class="flashMessage.type === 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'"
                class="p-4 rounded-2xl border text-sm font-bold"
            >
                {{ flashMessage.text }}
            </div>

            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div class="relative md:col-span-2">
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
                            placeholder="Cari nama, NIK, WA, kategori..."
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400"
                        />
                    </div>

                    <input
                        v-model="periode"
                        type="date"
                        class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                    />
                    
                    <select
                        v-model="ket"
                        class="w-full md:w-72 px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-[#2DD4BF]/20"
                    >
                        <option value="">Semua Kategori</option>
                        <option
                            v-for="item in kets"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>

                    <div class="flex gap-2">
                        <button
                            @click="applyFilter"
                            class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5"
                        >
                            Filter
                        </button>

                        <button
                            @click="resetFilter"
                            class="px-5 py-2.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-slate-200 transition-all"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Karyawan
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    WA
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Perut
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Berat
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Tinggi
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    BMI
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Selisih
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Kategori
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Periode
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="measurements.data.length > 0">
                                <tr
                                    v-for="item in measurements.data"
                                    :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-black text-[#1E293B] uppercase tracking-tight">
                                                {{ item.employee_name || '-' }}
                                            </div>
                                            <div class="text-[10px] font-black text-[#2DD4BF] uppercase tracking-widest">
                                                {{ item.employee_id || '-' }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600">
                                        {{ item.phone || '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-center text-[11px] font-black text-slate-600">
                                        {{ item.waist_cm ?? '-' }} cm
                                    </td>

                                    <td class="px-6 py-4 text-center text-[11px] font-black text-slate-600">
                                        {{ item.weight_kg ?? '-' }} kg
                                    </td>

                                    <td class="px-6 py-4 text-center text-[11px] font-black text-slate-600">
                                        {{ item.height_cm ?? '-' }} cm
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="bmiClass(item.bmi)"
                                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border border-current/10"
                                        >
                                            {{ item.bmi ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center text-[11px] font-black text-slate-600">
                                        {{ item.selisih ?? '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="ketClass(item.ket)"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border border-current/10"
                                        >
                                            {{ item.ket || '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ formatDate(item.periode) }}
                                    </td>

                                    <td class="px-6 py-4 text-right">
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
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="10" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </div>

                                        <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                            No Data <span class="text-[#2DD4BF]">BMI</span> Found
                                        </h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ measurements.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ measurements.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ measurements.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="measurements.links && measurements.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in measurements.links" :key="k">
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
    </AuthenticatedLayout>
</template>