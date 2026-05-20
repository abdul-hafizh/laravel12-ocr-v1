<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    cabangs: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            month: '',
            search: '',
            cabang_id: '',
        }),
    },
});

const search = ref(props.filters.search || '');
const month = ref(props.filters.month || '');
const cabangId = ref(props.filters.cabang_id || '');

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const sendWa = () => {
    if (!confirm('Kirim file Excel summary ini ke semua user Finance?')) {
        return;
    }

    router.post(
        route('summary.electricity.send-wa'),
        {
            search: search.value,
            month: month.value,
            cabang_id: cabangId.value,
        },
        {
            preserveScroll: true,
        }
    );
};

const applyFilter = () => {
    router.get(
        route('summary.electricity'),
        {
            search: search.value,
            month: month.value,
            cabang_id: cabangId.value,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const resetFilter = () => {
    search.value = '';
    cabangId.value = '';
    router.get(route('summary.electricity'), {
        month: month.value,
    });
};

const badgeClass = (status) => {
    if (status === 'Lengkap') {
        return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    }

    if (status === 'Perlu dicek') {
        return 'bg-amber-50 text-amber-600 border-amber-100';
    }

    return 'bg-rose-50 text-rose-600 border-rose-100';
};
</script>

<template>
    <Head title="Summary Token Listrik" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        Summary <span class="text-[#2DD4BF]">Token Listrik</span>
                    </h2>
                    <p class="text-sm text-slate-400 font-medium mt-1">
                        Perbandingan kWh awal dan akhir bulan untuk rekomendasi top-up bulan berikutnya.
                    </p>
                </div>

                <div class="flex items-center space-x-3">
                    <button
                        type="button"
                        @click="sendWa"
                        class="px-5 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-[#26bba8] transition-all shadow-sm"
                    >
                        Kirim WA Finance
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="relative md:col-span-1">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari cabang, meter, atau phone..."
                        class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                        @keyup.enter="applyFilter"
                    />
                </div>

                <div>
                    <input
                        v-model="month"
                        type="month"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />
                </div>

                <div>
                    <select
                        v-model="cabangId"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    >
                        <option value="">Semua Cabang</option>
                        <option
                            v-for="cabang in cabangs"
                            :key="cabang.cabang_id"
                            :value="cabang.cabang_id"
                        >
                            {{ cabang.kode_cabang }} - {{ cabang.nama_cabang }}
                        </option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="applyFilter"
                        class="flex-1 px-6 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:shadow-lg hover:shadow-teal-200 transition-all"
                    >
                        Filter
                    </button>

                    <button
                        type="button"
                        @click="resetFilter"
                        class="px-5 py-3 bg-white border border-slate-200 text-slate-500 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-50 transition-all"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Cabang</p>
                    <h3 class="text-3xl font-black text-[#1E293B] mt-2">{{ summary.length }}</h3>
                </div>

                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Pemakaian Estimasi</p>
                    <h3 class="text-3xl font-black text-[#1E293B] mt-2">
                        {{ formatRupiah(summary.reduce((total, item) => total + Number(item.estimasi_pemakaian_rupiah || 0), 0)) }}
                    </h3>
                </div>

                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Rekomendasi Top-up</p>
                    <h3 class="text-3xl font-black text-[#2DD4BF] mt-2">
                        {{ formatRupiah(summary.reduce((total, item) => total + Number(item.rekomendasi_topup_bulan_depan || 0), 0)) }}
                    </h3>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Cabang</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">kWh Awal</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">kWh Akhir</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Pemakaian</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Estimasi Rupiah</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Top-up Bulan Depan</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in summary"
                                :key="item.cabang_id"
                                class="hover:bg-slate-50/70 transition-all"
                            >
                                <td class="px-6 py-5">
                                    <div>
                                        <p class="font-bold text-[#1E293B]">{{ item.nama_cabang }}</p>
                                        <p class="text-xs text-slate-400 mt-1">
                                            {{ item.kode_cabang }} · Meter: {{ item.nomor_meter || '-' }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-1">
                                            Foto: {{ item.jumlah_foto }} · Periode: {{ item.periode }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-6 py-5 font-semibold text-slate-600">
                                    {{ formatNumber(item.kwh_awal) }}
                                </td>

                                <td class="px-6 py-5 font-semibold text-slate-600">
                                    {{ formatNumber(item.kwh_akhir) }}
                                </td>

                                <td class="px-6 py-5">
                                    <span class="font-black text-[#1E293B]">
                                        {{ formatNumber(item.pemakaian_kwh) }}
                                    </span>
                                    <span class="text-xs text-slate-400 ml-1">kWh</span>
                                </td>

                                <td class="px-6 py-5">
                                    <p class="font-bold text-slate-700">
                                        {{ formatRupiah(item.estimasi_pemakaian_rupiah) }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Harga/kWh: {{ formatRupiah(item.estimasi_harga_per_kwh) }}
                                    </p>
                                </td>

                                <td class="px-6 py-5">
                                    <p class="font-black text-[#2DD4BF]">
                                        {{ formatRupiah(item.rekomendasi_topup_bulan_depan) }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Sisa estimasi: {{ formatRupiah(item.estimasi_sisa_rupiah) }}
                                    </p>
                                </td>

                                <td class="px-6 py-5">
                                    <span
                                        :class="badgeClass(item.status_summary)"
                                        class="inline-flex px-3 py-1 rounded-full border text-xs font-bold"
                                    >
                                        {{ item.status_summary }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="summary.length === 0">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <p class="text-slate-400 font-semibold">
                                        Belum ada data summary token listrik pada periode ini.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>